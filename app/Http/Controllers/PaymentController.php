<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Requests\ReconcilePaymentRequest;
use App\Models\Payment;
use App\Models\Account;
use App\Models\PaymentAllocation;
use App\Services\Billing\PaymentReconciliationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Auth;
use App\Mail\PaymentReceiptMail;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Payment Controller
 *
 * Handles payment management following thin controller pattern.
 * All business logic delegated to services.
 *
 * @package App\Http\Controllers
 */
class PaymentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PaymentReconciliationService $reconciliationService
    ) {
    }

    /**
     * Display a listing of payments
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::with(['account', 'allocations.billing'])
            ->latest('payment_date');

        // Apply filters
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('transaction_id', 'like', "%{$search}%")
                    ->orWhereHas('account', function ($accountQuery) use ($search) {
                        $accountQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('account_number', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($reconciliationStatus = $request->input('reconciliation_status')) {
            $query->where('reconciliation_status', $reconciliationStatus);
        }

        if ($method = $request->input('method')) {
            $query->where('method', $method);
        }

        if ($accountId = $request->input('account_id')) {
            $query->where('account_id', $accountId);
        }

        $payments = $query->paginate(15)->withQueryString();

        return Inertia::render('payments/payments', [
            'payments' => $payments,
            'filters' => $request->only(['search', 'status', 'reconciliation_status', 'method', 'account_id']),
            'accounts' => Account::select('id', 'name', 'account_number')->get(),
            'can' => [
                'create' => Auth::user()->can('create', Payment::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new payment
     */
    public function create(): Response
    {
        $this->authorize('create', Payment::class);

        return Inertia::render('payments/create', [
            'accounts' => Account::active()
                ->with(['billings' => function ($q) {
                    $q->where('status', '!=', 'paid');
                }])
                ->get()
                ->map(fn ($acc) => [
                    'id' => $acc->id,
                    'name' => $acc->name,
                    'account_number' => $acc->account_number,
                    'outstanding_balance' => $acc->getCurrentBalance()
                ]),
            'paymentMethods' => $this->getPaymentMethods(),
            'can' => [
                'create' => Auth::user()->can('create', Payment::class),
            ],
        ]);
    }

    /**
     * Store a newly created payment
     */
    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $payment = Payment::create($request->validated());

        // Auto-reconcile if configured
        if (config('reconciliation.auto_reconcile') && $payment->status === 'completed') {
            try {
                $result = $this->reconciliationService->reconcilePayment($payment);

                return redirect()->route('payments.show', $payment)
                    ->with('success', 'Payment recorded and reconciled successfully')
                    ->with('reconciliation_result', $result->getSummary());
            } catch (\Exception $e) {
                return redirect()->route('payments.show', $payment)
                    ->with('warning', 'Payment recorded but reconciliation failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('payments.show', $payment)
            ->with('success', 'Payment recorded successfully');
    }

    /**
     * Display the specified payment
     */
    public function show(Payment $payment): Response
    {
        $this->authorize('view', $payment);

        $payment->load([
            'account',
            'allocations.billing',
            'audits' => function ($query) {
                $query->latest()->limit(20);
            }
        ]);

        return Inertia::render('payments/show', [
            'payment' => $payment->load('account'),
            'allocations' => $payment->allocations()
                ->with('billing')
                ->get(),
            // 'payment' => [
            //     ...$payment->toArray(),
            //     'summary' => $payment->getSummary(),
            //     'reconciliation_summary' => $payment->getReconciliationSummary(),
            //     'can_be_reconciled' => $payment->canBeReconciled(),
            //     'can_be_reversed' => $payment->canBeReversed(),
            // ],
            // 'accountBalance' => $this->reconciliationService->getAccountBalance($payment->account),
            'can' => [
                'update' => Auth::user()->can('update', $payment),
                'delete' => Auth::user()->can('delete', $payment),
                'reconcile' => Auth::user()->can('reconcile', $payment),
                // 'reverseReconciliation' => Auth::user()->can('reverseReconciliation', $payment),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified payment
     */
    public function edit(Payment $payment): Response
    {
        $this->authorize('update', $payment);

        return Inertia::render('payments/edit', [
            'payment' => $payment->load('account'),
            'accounts' => Account::active()
                ->select('id', 'name', 'account_number')
                ->get(),
            'paymentMethods' => $this->getPaymentMethods(),
            'can' => [
                'update' => Auth::user()->can('update', $payment),
            ],
        ]);
    }

    /**
     * Update the specified payment
     */
    public function update(UpdatePaymentRequest $request, Payment $payment): RedirectResponse
    {
        $originalAmount = $payment->amount;
        if ($originalAmount != $request->amount) {
            // Remove existing allocations
            $payment->allocations()->delete();

            // Recalculate allocations
            $this->allocatePayment($payment);

            return redirect()->route('payments.show', $payment)
                ->with('info', 'Payment updated successfully');
        } else {
            $payment->update($request->validated());

            return redirect()->route('payments.show', $payment)
                ->with('success', 'Payment updated successfully');
        }
    }

    /**
     * Remove the specified payment
     */
    public function destroy(Payment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);

        // Check if payment can be safely deleted
        if ($payment->allocations()->exists()) {
            return back()->with('error', 'Cannot delete payment that has been allocated to bills. Please reverse reconciliation first.');
        }

        $payment->delete();

        return redirect()->route('payments.index')
            ->with('success', 'Payment deleted successfully');
    }

    /**
     * Reconcile a payment
     */
    public function reconcile(ReconcilePaymentRequest $request, Payment $payment): RedirectResponse
    {
        $this->authorize('reconcile', $payment);

        try {
            $manualAllocations = $request->input('manual_allocations');

            $result = $this->reconciliationService->reconcilePayment(
                $payment,
                $manualAllocations
            );

            return redirect()->route('payments.show', $payment)
                ->with('success', $result->getSummary())
                ->with('reconciliation_result', $result->toArray());
        } catch (\Exception $e) {
            return back()->with('error', 'Reconciliation failed: ' . $e->getMessage());
        }
    }

    /**
     * Show reconciliation report for a payment
     */
    public function reconciliationReport(Payment $payment): Response
    {
        $this->authorize('view', $payment);

        $report = $this->reconciliationService->generateReconciliationReport($payment);

        return Inertia::render('payments/reconciliationReport', [
            'payment' => $payment,
            'report' => $report,
        ]);
    }

    /**
     * Reverse a payment reconciliation
     */
    public function reverseReconciliation(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('reverseReconciliation', $payment);

        $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);

        try {
            $this->reconciliationService->reverseReconciliation(
                $payment,
                $request->input('reason')
            );

            return redirect()->route('payments.show', $payment)
                ->with('success', 'Payment reconciliation reversed successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Reversal failed: ' . $e->getMessage());
        }
    }

    /**
     * Bulk reconcile pending payments
     */
    public function bulkReconcile(Request $request): RedirectResponse
    {
        $this->authorize('reconcile', Payment::class);

        $request->validate([
            'payment_ids' => 'required|array|min:1',
            'payment_ids.*' => 'exists:payments,id',
        ]);

        $successCount = 0;
        $failureCount = 0;
        $errors = [];

        foreach ($request->input('payment_ids') as $paymentId) {
            try {
                $payment = Payment::findOrFail($paymentId);

                if ($payment->canBeReconciled()) {
                    $this->reconciliationService->reconcilePayment($payment);
                    $successCount++;
                }
            } catch (\Exception $e) {
                $failureCount++;
                $errors[] = "Payment #{$paymentId}: " . $e->getMessage();
            }
        }

        $message = "Reconciled {$successCount} payment(s)";
        if ($failureCount > 0) {
            $message .= ", {$failureCount} failed";
        }

        return back()->with('success', $message)
            ->with('errors', $errors);
    }

    /**
     * Export payments to CSV
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::with(['account', 'allocations.billing']);

        // Apply same filters as index
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('transaction_id', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($reconciliationStatus = $request->input('reconciliation_status')) {
            $query->where('reconciliation_status', $reconciliationStatus);
        }

        $payments = $query->get();

        $csv = $this->generatePaymentsCsv($payments);

        return response()->streamDownload(
            function () use ($csv) {
                echo $csv;
            },
            'payments_' . now()->format('Y-m-d_His') . '.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    // In PaymentController@store or a dedicated service
    public function allocatePayment(Payment $payment): void
    {
        $account = $payment->account;
        $remainingAmount = $payment->amount;

        // Get outstanding bills ordered by oldest first
        $outstandingBills = $account->billings()
            ->whereIn('status', ['pending', 'overdue', 'partially_paid'])
            ->orderBy('due_date', 'asc')
            ->get();

        foreach ($outstandingBills as $bill) {
            if ($remainingAmount <= 0) {
                break;
            }

            $billBalance = $bill->total_amount - $bill->paid_amount;
            $allocationAmount = min($remainingAmount, $billBalance);

            // Create allocation
            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'billing_id' => $bill->id,
                'allocated_amount' => $allocationAmount,
            ]);

            // Update bill paid amount
            $bill->increment('paid_amount', $allocationAmount);

            // Update bill status
            if ($bill->paid_amount >= $bill->total_amount) {
                $bill->update(['status' => 'paid', 'paid_at' => now()]);
            } elseif ($bill->paid_amount > 0) {
                $bill->update(['status' => 'partially_paid']);
            }

            $remainingAmount -= $allocationAmount;
        }
    }

    // In PaymentController
    public function downloadReceipt(Payment $payment)
    {
        $this->authorize('view', $payment);
        

        $pdf = Pdf::loadView('receipts.payment', [
            'payment' => $payment->load('account', 'allocations.billing'),
        ]);

        return $pdf->download("payment-receipt-{$payment->id}.pdf");
    }

    public function sendReceipt(Request $request, Payment $payment)
    {
        $this->authorize('view', $payment);

        $request->validate([
            'email' => 'required|email',
        ]);

        Mail::to($request->email)->send(
            new PaymentReceiptMail($payment)
        );

        return back()->with('success', 'Receipt sent successfully');
    }

    /**
     * Get available payment methods
     */
    protected function getPaymentMethods(): array
    {
        return [
            ['value' => 'Cash', 'label' => 'Cash'],
            ['value' => 'Bank Transfer', 'label' => 'Bank Transfer'],
            ['value' => 'M-Pesa', 'label' => 'M-Pesa'],
            ['value' => 'Card', 'label' => 'Card'],
            ['value' => 'Cheque', 'label' => 'Cheque'],
        ];
    }

    /**
     * Generate CSV from payments collection
     */
    protected function generatePaymentsCsv($payments): string
    {
        $output = fopen('php://temp', 'r+');

        // Headers
        fputcsv($output, [
            'Payment ID',
            'Date',
            'Account Number',
            'Account Name',
            'Amount',
            'Method',
            'Reference',
            'Transaction ID',
            'Status',
            'Reconciliation Status',
            'Allocated Amount',
            'Unallocated Amount',
        ]);

        // Data
        foreach ($payments as $payment) {
            fputcsv($output, [
                $payment->id,
                $payment->payment_date->format('Y-m-d'),
                $payment->account->account_number,
                $payment->account->name,
                $payment->amount,
                $payment->method,
                $payment->reference,
                $payment->transaction_id,
                $payment->status,
                $payment->reconciliation_status,
                $payment->allocated_amount,
                $payment->unallocated_amount,
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
