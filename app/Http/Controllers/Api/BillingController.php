<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateBillRequest;
use App\Http\Resources\BillingResource;
use App\Models\Billing;
use App\Models\Account;
use App\Services\Billing\BillingService;
use App\Services\Billing\BalanceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * API Billing Controller
 *
 * RESTful API for billing operations.
 * Returns JSON responses for integration with external systems.
 *
 * @package App\Http\Controllers\Api
 */
class BillingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected BillingService $billingService,
        protected BalanceResolver $balanceResolver
    ) {
    }
    
    /**
     * Get all billings with pagination
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Billing::class);

        $query = Billing::with(['account', 'details.meter']);

        // Filters
        if ($accountId = $request->input('account_id')) {
            $query->where('account_id', $accountId);
        }

        if ($period = $request->input('billing_period')) {
            $query->where('billing_period', $period);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($request->boolean('overdue_only')) {
            $query->where('status', 'overdue');
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'issued_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min($request->input('per_page', 15), 100);
        $billings = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => BillingResource::collection($billings),
            'meta' => [
                'total' => $billings->total(),
                'per_page' => $billings->perPage(),
                'current_page' => $billings->currentPage(),
                'last_page' => $billings->lastPage(),
            ],
        ]);
    }

    /**
     * Get specific billing
     *
     * @param Billing $billing
     * @return JsonResponse
     */
    public function show(Billing $billing): JsonResponse
    {
        $this->authorize('view', $billing);

        $billing->load([
            'account',
            'details.meter',
            'payments.allocations',
        ]);

        return response()->json([
            'success' => true,
            'data' => new BillingResource($billing),
        ]);
    }

    /**
     * Generate new bill
     *
     * @param GenerateBillRequest $request
     * @return JsonResponse
     */
    public function store(GenerateBillRequest $request): JsonResponse
    {
        $account = Account::findOrFail($request->input('account_id'));
        $billingPeriod = $request->input('billing_period');

        try {
            $billing = $this->billingService->generateForAccount($account, $billingPeriod);

            return response()->json([
                'success' => true,
                'message' => 'Bill generated successfully',
                'data' => new BillingResource($billing->load(['account', 'details.meter'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate bill',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Void a billing
     *
     * @param Request $request
     * @param Billing $billing
     * @return JsonResponse
     */
    public function void(Request $request, Billing $billing): JsonResponse
    {
        $this->authorize('void', $billing);

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $billing->update([
                'status' => 'voided',
                'voided_at' => now(),
                'void_reason' => $request->input('reason'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bill voided successfully',
                'data' => new BillingResource($billing),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to void bill',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get billing summary for account
     *
     * @param Account $account
     * @return JsonResponse
     */
    public function accountSummary(Account $account): JsonResponse
    {
        $this->authorize('viewAny', Billing::class);

        $summary = $this->balanceResolver->getAccountBalance($account);
        $outstandingBills = $this->balanceResolver->getOutstandingBillsSummary($account);

        return response()->json([
            'success' => true,
            'data' => [
                'account_id' => $account->id,
                'account_number' => $account->account_number,
                'balance' => $summary,
                'outstanding_bills' => $outstandingBills,
            ],
        ]);
    }

    /**
     * Get billing statistics for period
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function periodStatistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Billing::class);

        $request->validate([
            'billing_period' => 'required|string|date_format:Y-m',
        ]);

        $period = $request->input('billing_period');
        
        $stats = [
            'period' => $period,
            'total_bills' => Billing::where('billing_period', $period)->count(),
            'total_amount' => Billing::where('billing_period', $period)->sum('total_amount'),
            'total_paid' => Billing::where('billing_period', $period)->sum('paid_amount'),
            'by_status' => [],
        ];

        $byStatus = Billing::where('billing_period', $period)
            ->selectRaw('status, COUNT(*) as count, SUM(total_amount) as total, SUM(paid_amount) as paid')
            ->groupBy('status')
            ->get();

        foreach ($byStatus as $row) {
            $stats['by_status'][$row->status] = [
                'count' => $row->count,
                'total_amount' => $row->total,
                'paid_amount' => $row->paid,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get overdue bills
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function overdue(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Billing::class);

        $query = Billing::where('status', 'overdue')
            ->with(['account', 'details.meter']);

        if ($accountId = $request->input('account_id')) {
            $query->where('account_id', $accountId);
        }

        $perPage = min($request->input('per_page', 15), 100);
        $billings = $query->orderBy('due_date', 'asc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => BillingResource::collection($billings),
            'meta' => [
                'total' => $billings->total(),
                'total_overdue_amount' => Billing::where('status', 'overdue')->sum('total_amount'),
            ],
        ]);
    }
}
