<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Account;
use App\Services\Billing\PaymentReconciliationService;
use App\Services\Billing\BalanceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * API Payment Controller
 * 
 * RESTful API for payment operations.
 * Handles payment creation, reconciliation, and queries.
 * 
 * @package App\Http\Controllers\Api
 */
class PaymentController extends Controller
{
    use AuthorizesRequests;
    
    public function __construct(
        protected PaymentReconciliationService $reconciliationService,
        protected BalanceResolver $balanceResolver
    ) {}

    /**
     * Get all payments with pagination
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::with(['account', 'allocations.billing']);

        // Filters
        if ($accountId = $request->input('account_id')) {
            $query->where('account_id', $accountId);
        }

        if ($status = $request->input('reconciliation_status')) {
            $query->where('reconciliation_status', $status);
        }

        if ($method = $request->input('method')) {
            $query->where('method', $method);
        }

        if ($startDate = $request->input('start_date')) {
            $query->where('payment_date', '>=', $startDate);
        }

        if ($endDate = $request->input('end_date')) {
            $query->where('payment_date', '<=', $endDate);
        }

        if ($request->boolean('unreconciled_only')) {
            $query->where('reconciliation_status', 'pending');
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'payment_date');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min($request->input('per_page', 15), 100);
        $payments = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => PaymentResource::collection($payments),
            'meta' => [
                'total' => $payments->total(),
                'per_page' => $payments->perPage(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }

    /**
     * Get specific payment
     * 
     * @param Payment $payment
     * @return JsonResponse
     */
    public function show(Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment);

        $payment->load([
            'account',
            'allocations.billing',
        ]);

        return response()->json([
            'success' => true,
            'data' => new PaymentResource($payment),
        ]);
    }

    /**
     * Create new payment
     * 
     * @param StorePaymentRequest $request
     * @return JsonResponse
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = Payment::create($request->validated());

        // Auto-reconcile if configured
        if (config('reconciliation.auto_reconcile', true)) {
            try {
                $result = $this->reconciliationService->reconcilePayment($payment);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Payment created and reconciled successfully',
                    'data' => new PaymentResource($payment->fresh(['account', 'allocations.billing'])),
                    'reconciliation' => [
                        'bills_paid' => $result->billsPaid,
                        'total_allocated' => $result->totalAllocated,
                        'remaining_amount' => $result->remainingAmount,
                        'has_credit' => $result->hasCredit,
                    ],
                ], 201);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment created but reconciliation failed',
                    'data' => new PaymentResource($payment->fresh(['account', 'allocations.billing'])),
                    'error' => $e->getMessage(),
                ], 201);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment created successfully',
            'data' => new PaymentResource($payment->fresh(['account', 'allocations.billing'])),
        ], 201);
    }

    /**
     * Reconcile a payment
     * 
     * @param Payment $payment
     * @return JsonResponse
     */
    public function reconcile(Payment $payment): JsonResponse
    {
        $this->authorize('reconcile', $payment);

        if ($payment->isReconciled()) {
            return response()->json([
                'success' => false,
                'message' => 'Payment is already reconciled',
            ], 422);
        }

        try {
            $result = $this->reconciliationService->reconcilePayment($payment);

            return response()->json([
                'success' => true,
                'message' => 'Payment reconciled successfully',
                'data' => new PaymentResource($payment->fresh(['account', 'allocations.billing'])),
                'reconciliation' => [
                    'bills_paid' => $result->billsPaid,
                    'total_allocated' => $result->totalAllocated,
                    'remaining_amount' => $result->remainingAmount,
                    'has_credit' => $result->hasCredit,
                    'credit_balance_id' => $result->creditBalanceId,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment reconciliation failed',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reverse payment reconciliation
     * 
     * @param Payment $payment
     * @return JsonResponse
     */
    public function reverseReconciliation(Payment $payment): JsonResponse
    {
        $this->authorize('reverseReconciliation', $payment);

        if (!$payment->isReconciled()) {
            return response()->json([
                'success' => false,
                'message' => 'Payment is not reconciled',
            ], 422);
        }

        try {
            $this->reconciliationService->reverseReconciliation($payment);

            return response()->json([
                'success' => true,
                'message' => 'Payment reconciliation reversed successfully',
                'data' => new PaymentResource($payment->fresh(['account', 'allocations.billing'])),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reverse reconciliation',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get account payment history
     * 
     * @param Account $account
     * @return JsonResponse
     */
    public function accountHistory(Account $account): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $payments = $account->payments()
            ->with(['allocations.billing'])
            ->latest('payment_date')
            ->limit(20)
            ->get();

        $history = $this->balanceResolver->getPaymentHistory($account, 20);

        return response()->json([
            'success' => true,
            'data' => [
                'account_id' => $account->id,
                'account_number' => $account->account_number,
                'payments' => PaymentResource::collection($payments),
                'history' => $history,
            ],
        ]);
    }

    /**
     * Get payment statistics
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $query = Payment::query();

        if ($startDate = $request->input('start_date')) {
            $query->where('payment_date', '>=', $startDate);
        }

        if ($endDate = $request->input('end_date')) {
            $query->where('payment_date', '<=', $endDate);
        }

        $stats = [
            'total_payments' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'by_method' => [],
            'by_reconciliation_status' => [],
        ];

        // By payment method
        $byMethod = (clone $query)
            ->selectRaw('method, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('method')
            ->get();

        foreach ($byMethod as $row) {
            $stats['by_method'][$row->method] = [
                'count' => $row->count,
                'total' => (float) $row->total,
            ];
        }

        // By reconciliation status
        $byStatus = (clone $query)
            ->selectRaw('reconciliation_status, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('reconciliation_status')
            ->get();

        foreach ($byStatus as $row) {
            $stats['by_reconciliation_status'][$row->reconciliation_status] = [
                'count' => $row->count,
                'total' => (float) $row->total,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get unreconciled payments
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function unreconciled(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::where('reconciliation_status', 'pending')
            ->with(['account', 'allocations.billing']);

        if ($accountId = $request->input('account_id')) {
            $query->where('account_id', $accountId);
        }

        $perPage = min($request->input('per_page', 15), 100);
        $payments = $query->orderBy('payment_date', 'asc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => PaymentResource::collection($payments),
            'meta' => [
                'total' => $payments->total(),
                'total_unreconciled_amount' => Payment::where('reconciliation_status', 'pending')->sum('amount'),
            ],
        ]);
    }

    /**
     * Bulk reconcile payments
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkReconcile(Request $request): JsonResponse
    {
        $this->authorize('bulkReconcile', Payment::class);

        $request->validate([
            'payment_ids' => 'required|array|min:1',
            'payment_ids.*' => 'required|integer|exists:payments,id',
        ]);

        $paymentIds = $request->input('payment_ids');
        $payments = Payment::whereIn('id', $paymentIds)
            ->where('reconciliation_status', 'pending')
            ->get();

        $results = [
            'total' => $payments->count(),
            'reconciled' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($payments as $payment) {
            try {
                $this->reconciliationService->reconcilePayment($payment);
                $results['reconciled']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Reconciled {$results['reconciled']} of {$results['total']} payments",
            'data' => $results,
        ]);
    }
}
