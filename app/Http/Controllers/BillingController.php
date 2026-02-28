<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBillingRequest;
use App\Http\Requests\UpdateBillingRequest;
use App\Http\Requests\GenerateBillRequest;
use App\Models\Billing;
use App\Models\Account;
use App\Models\Meter;
use App\Services\Billing\BillingService;
use App\Services\Billing\BillingOrchestrator;
use App\Services\Billing\RebillingService;
use App\Services\Billing\StatementGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

/**
 * Billing Controller
 *
 * Handles billing operations following thin controller pattern.
 * All business logic delegated to services.
 *
 * @package App\Http\Controllers
 */
class BillingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected BillingService $billingService,
        protected BillingOrchestrator $billingOrchestrator,
        protected RebillingService $rebillingService,
        protected StatementGenerator $statementGenerator
    ) {}

    /**
     * Display a listing of bills
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Billing::class);

        $query = Billing::with('account');

        // Apply filters
        if ($search = $request->input('search')) {
            $query->whereHas('account', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($period = $request->input('period')) {
            $query->where('billing_period', $period);
        }

        if ($accountId = $request->input('account_id')) {
            $query->where('account_id', $accountId);
        }

        // Date range filter
        if ($fromDate = $request->input('from_date')) {
            $query->where('issued_at', '>=', $fromDate);
        }

        if ($toDate = $request->input('to_date')) {
            $query->where('issued_at', '<=', $toDate);
        }

        $bills = $query->latest('issued_at')->paginate(15);

        return Inertia::render('billing/billing', [
            'bills' => $bills,
            'filters' => $request->only(['search', 'status', 'period', 'account_id', 'from_date', 'to_date']),
            'accounts' => Account::select('id', 'name', 'account_number')->get(),
            'availablePeriods' => $this->getAvailablePeriods(),
            'can' => [
                'create' => Auth::user()->can('create', Billing::class),
                'generate' => Auth::user()->can('generate', Billing::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new bill
     */
    public function create(): Response
    {
        $this->authorize('create', Billing::class);

        $currentMonth = now()->format('Y-m');

        $accounts = Account::active()
            ->whereHas('meters', function ($query) {
                $query->where('status', 'active');
            })
            ->whereDoesntHave('billings', function ($query) use ($currentMonth) {
                $query->where('billing_period', $currentMonth)
                    ->whereNotIn('status', ['voided']);
            })
            ->with(['meters' => function ($query) {
                $query->where('status', 'active');
            }])
            ->select('id', 'name', 'account_number')
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'account_number' => $account->account_number,
                    'active_meters_count' => $account->meters->count(),
                    'meters' => $account->meters->map(fn($m) => [
                        'meter_number' => $m->meter_number,
                        'meter_name' => $m->meter_name,
                    ])->toArray(),
                ];
            });

        return Inertia::render('billing/create', [
            'accounts' => $accounts,
            'current_month' => $currentMonth,
            'total_accounts' => Account::active()->count(),
            'unbilled_accounts' => $accounts->count(),
            'can' => [
                'create' => Auth::user()->can('create', Billing::class),
                'generate' => Auth::user()->can('generate', Billing::class),
            ],
        ]);
    }

    /**
     * Generate a new bill for an account
     */
    public function store(GenerateBillRequest $request): RedirectResponse
    {
        try {
            $billing = $this->billingService->generateForAccount(
                Account::findOrFail($request->input('account_id')),
                $request->input('billing_period')
            );

            return redirect()->route('billings.show', $billing)
                ->with('success', 'Bill generated successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Bill generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified bill
     */
    public function show(Billing $billing): Response
    {
        $this->authorize('view', $billing);

        $billing->load([
            'account',
            'details.meter',
            'allocations.payment',
            'creditNotes.createdBy',
            'creditNotes.previousAccount',
            'audits' => function ($query) {
                $query->latest()->limit(20);
            }
        ]);

        return Inertia::render('billing/show', [
            'bill' => [
                'id' => $billing->id,
                'account_id' => $billing->account_id,
                'billing_period' => $billing->billing_period,
                'total_amount' => $billing->total_amount,
                'status' => $billing->status,
                'issued_at' => $billing->issued_at,
                'due_date' => $billing->due_date,
                'paid_at' => $billing->paid_at,
                'created_at' => $billing->created_at,
                'updated_at' => $billing->updated_at,

                // Relationships - explicitly map
                'account' => $billing->account,
                'details' => $billing->details->map(function ($detail) {
                    return [
                        'id' => $detail->id,
                        'billing_id' => $detail->billing_id,
                        'meter_id' => $detail->meter_id,
                        'previous_reading' => $detail->previous_reading_value,
                        'current_reading' => $detail->current_reading_value,
                        'units' => $detail->units_used,
                        'rate' => $detail->rate,
                        'amount' => $detail->amount,
                        'description' => $detail->description,
                        'meter' => $detail->meter ? [
                            'id' => $detail->meter->id,
                            'meter_number' => $detail->meter->meter_number,
                            'meter_name' => $detail->meter->meter_name,
                            'status' => $detail->meter->status,
                        ] : null,
                    ];
                }),
                'allocations' => $billing->allocations->map(function ($allocation) {
                    return [
                        'id' => $allocation->id,
                        'payment_id' => $allocation->payment_id,
                        'billing_id' => $allocation->billing_id,
                        'allocated_amount' => $allocation->allocated_amount,
                        'allocation_date' => $allocation->allocation_date,
                        'payment' => $allocation->payment ? [
                            'id' => $allocation->payment->id,
                            'amount' => $allocation->payment->amount,
                            'payment_date' => $allocation->payment->payment_date,
                            'method' => $allocation->payment->method,
                            'reference' => $allocation->payment->reference,
                        ] : null,
                    ];
                }),

                'credit_notes' => $billing->creditNotes->map(function ($note) {
                    return [
                        'id' => $note->id,
                        'reference' => $note->reference,
                        'type' => $note->type,
                        'amount' => $note->amount,
                        'reason' => $note->reason,
                        'status' => $note->status,
                        'void_reason' => $note->void_reason,
                        'voided_at' => $note->voided_at,
                        'created_by' => $note->createdBy ? [
                            'id' => $note->createdBy->id,
                            'name' => $note->createdBy->name,
                            'email' => $note->createdBy->email,
                        ] : null,
                        'previous_account' => $note->previousAccount ? [
                            'id' => $note->previousAccount->id,
                            'name' => $note->previousAccount->name,
                            'account_number' => $note->previousAccount->account_number,
                        ] : null,
                    ];
                }),

                // Computed properties — exposed directly for frontend convenience
                'paid_amount' => $billing->paid_amount,
                'balance' => $billing->balance,
                'notes' => $billing->notes ?? null,
                'summary' => $billing->getSummary(),
                'can_be_modified' => $billing->canBeModified(),
                'is_overdue' => $billing->isOverdue(),
                'days_until_due' => $billing->getDaysUntilDue(),
                'days_overdue' => $billing->getDaysOverdue(),
            ],
            'can' => [
                'update' => Auth::user()->can('update', $billing),
                'delete' => Auth::user()->can('delete', $billing),
                'void' => Auth::user()->can('void', $billing),
                'rebill' => Auth::user()->can('rebill', $billing),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified bill
     */
    public function edit(Billing $billing): Response|RedirectResponse
    {
        $this->authorize('update', $billing);

        if (!$billing->canBeModified()) {
            return redirect()->route('billings.show', $billing)
                ->with('error', 'This bill cannot be modified (status: ' . $billing->status . ')');
        }

        return Inertia::render('billing/edit', [
            'bill' => $billing->load('account', 'details'),
            'meters' => Meter::where('account_id', $billing->account_id)
                ->select('id', 'meter_number', 'meter_name')
                ->get(),
            'can' => [
                'update' => Auth::user()->can('update', $billing),
            ],
        ]);
    }

    /**
     * Update the specified bill
     */
    public function update(UpdateBillingRequest $request, Billing $billing): RedirectResponse
    {
        $billing->update($request->validated());

        // Recalculate total if details were modified
        if ($request->has('details')) {
            $billing->recalculateTotal();
        }

        return redirect()->route('billings.show', $billing)
            ->with('success', 'Bill updated successfully');
    }

    /**
     * Remove the specified bill
     */
    public function destroy(Billing $billing): RedirectResponse
    {
        $this->authorize('delete', $billing);

        // Check if bill can be safely deleted
        if ($billing->allocations()->exists()) {
            return back()->with('error', 'Cannot delete bill that has received payments');
        }

        if (!$billing->canBeModified()) {
            return back()->with('error', 'Cannot delete bill with status: ' . $billing->status);
        }

        $billing->delete();

        return redirect()->route('billings.index')
            ->with('success', 'Bill deleted successfully');
    }

    /**
     * Void a bill
     */
    public function void(Request $request, Billing $billing): RedirectResponse
    {
        $this->authorize('void', $billing);

        $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);

        try {
            $this->rebillingService->rebillWithAdjustments($billing, [], $request->input('reason'));

            return redirect()->route('billings.show', $billing)
                ->with('success', 'Bill voided successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to void bill: ' . $e->getMessage());
        }
    }

    /**
     * Rebill an account for a period
     */
    public function rebill(Request $request, Billing $billing): RedirectResponse
    {
        $this->authorize('rebill', $billing);

        $request->validate([
            'reason' => 'required|string|min:10|max:500',
            'adjustments' => 'nullable|array',
        ]);

        try {
            $newBilling = $this->rebillingService->rebillWithAdjustments(
                $billing,
                $request->input('adjustments', []),
                $request->input('reason')
            );

            return redirect()->route('billings.show', $newBilling)
                ->with('success', 'Bill regenerated successfully. Previous bill has been voided.');
        } catch (\Exception $e) {
            return back()->with('error', 'Rebilling failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate bills for all active accounts
     */
    public function generateAll(Request $request): RedirectResponse
    {
        $this->authorize('generate', Billing::class);

        $request->validate([
            'billing_period' => 'required|date_format:Y-m',
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'exists:accounts,id',
        ]);

        try {
            $result = $this->billingOrchestrator->generateForAccounts(
                $request->input('account_ids'),
                $request->input('billing_period')
            );

            return back()->with('success', "Generated {$result['success']} bills successfully. {$result['failed']} failed.");
        } catch (\Exception $e) {
            return back()->with('error', 'Bulk generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Download bill statement
     */
    public function downloadStatement(Billing $billing): HttpResponse|RedirectResponse
    {
        $this->authorize('view', $billing);

        try {
            return $this->statementGenerator->generateBillStatement($billing)->download("bill_statement_{$billing->id}.pdf");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to generate statement: ' . $e->getMessage());
        }
    }

    /**
     * Send bill statement via email
     */
    public function sendStatement(Request $request, Billing $billing): RedirectResponse
    {
        $this->authorize('view', $billing);

        $request->validate([
            'email' => 'nullable|email',
        ]);

        try {
            $email = $request->input('email') ?? $billing->account->email;

            if (!$email) {
                return back()->with('error', 'No email address available for this account');
            }

            $this->statementGenerator->sendBillStatement($billing, $email);

            return back()->with('success', 'Statement sent successfully to ' . $email);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send statement: ' . $e->getMessage());
        }
    }

    /**
     * Export bills to CSV
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Billing::class);

        $query = Billing::with(['account', 'details']);

        // Apply same filters as index
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($period = $request->input('period')) {
            $query->where('billing_period', $period);
        }

        $bills = $query->get();

        $csv = $this->generateBillsCsv($bills);

        return response()->streamDownload(
            function () use ($csv) {
                echo $csv;
            },
            'bills_' . now()->format('Y-m-d_His') . '.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    /**
     * Get available billing periods
     */
    protected function getAvailablePeriods(): array
    {
        return Billing::select('billing_period')
            ->distinct()
            ->orderBy('billing_period', 'desc')
            ->limit(12)
            ->pluck('billing_period')
            ->toArray();
    }

    /**
     * Generate CSV from bills collection
     */
    protected function generateBillsCsv($bills): string
    {
        $output = fopen('php://temp', 'r+');

        // Headers
        fputcsv($output, [
            'Bill ID',
            'Billing Period',
            'Account Number',
            'Account Name',
            'Total Amount',
            'Paid Amount',
            'Balance',
            'Status',
            'Issued Date',
            'Due Date',
            'Days Overdue',
        ]);

        // Data
        foreach ($bills as $bill) {
            fputcsv($output, [
                $bill->id,
                $bill->billing_period,
                $bill->account->account_number,
                $bill->account->name,
                $bill->total_amount,
                $bill->paid_amount,
                $bill->balance,
                $bill->status,
                $bill->issued_at->format('Y-m-d'),
                $bill->due_date->format('Y-m-d'),
                $bill->getDaysOverdue(),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
