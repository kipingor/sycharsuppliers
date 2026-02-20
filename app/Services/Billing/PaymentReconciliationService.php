<?php

namespace App\Services\Billing;

use App\Models\Account;
use App\Models\Billing;
use App\Models\CarryForwardBalance;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\Audit\AuditService;
use App\Events\Billing\PaymentReconciled;
use App\Events\Billing\BillPaid;
use App\Events\Billing\CarryForwardCreated;
use App\DTOs\ReconciliationResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Payment Reconciliation Service
 * 
 * Handles all payment reconciliation logic including:
 * - Automatic payment allocation to bills (FIFO)
 * - Partial payment handling
 * - Overpayment and carry-forward creation
 * - Balance calculations
 * - Audit trail logging
 * 
 * @package App\Services\Billing
 */
class PaymentReconciliationService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Reconcile a payment against account bills
     * 
     * @param Payment $payment Payment to reconcile
     * @param array|null $manualAllocations Optional manual allocation instructions
     * @return ReconciliationResult
     * @throws \Exception
     */
    public function reconcilePayment(
        Payment $payment,
        ?array $manualAllocations = null
    ): ReconciliationResult {
        if ($payment->reconciliation_status === 'reconciled') {
            throw new InvalidArgumentException(
                "Payment #{$payment->id} is already reconciled"
            );
        }

        return DB::transaction(function () use ($payment, $manualAllocations) {
            Log::info("Starting reconciliation for payment #{$payment->id}", [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'account_id' => $payment->account_id,
            ]);

            // Load account with outstanding bills
            $account = Account::with([
                'billings' => function ($query) {
                    $query->whereIn('status', ['pending', 'partially_paid'])
                        ->orderBy('due_date', 'asc')
                        ->orderBy('created_at', 'asc');
                }
            ])->findOrFail($payment->account_id);

            $allocations = collect();
            $remainingAmount = $payment->amount;

            // Apply manual allocations if provided, otherwise use FIFO
            if ($manualAllocations) {
                $allocations = $this->applyManualAllocations(
                    $payment,
                    $manualAllocations,
                    $remainingAmount
                );
            } else {
                $allocations = $this->allocateToOldestBills($payment);
            }

            $totalAllocated = $allocations->sum('allocated_amount');
            $remainingAmount = $payment->amount - $totalAllocated;

            // Handle overpayment with carry-forward
            $carryForward = null;
            if ($remainingAmount > config('reconciliation.minimum_allocation', 0.01)) {
                $carryForward = $this->handleOverpayment($payment, $remainingAmount);
            }

            // Update payment reconciliation status
            $payment->update([
                'reconciliation_status' => $remainingAmount > 0.01 ? 'partially_reconciled' : 'reconciled',
                'reconciled_at' => now(),
                'reconciled_by' => Auth::id(),
            ]);

            // Get updated bills
            $updatedBills = Billing::whereIn(
                'id',
                $allocations->pluck('billing_id')
            )->get();

            // Get current balance snapshot
            $balanceSnapshot = $this->getAccountBalance($account);

            // Log audit trail
            $this->auditService->logPaymentAction(
                'reconciled',
                $payment,
                [
                    'allocations' => $allocations->toArray(),
                    'total_allocated' => $totalAllocated,
                    'carry_forward' => $carryForward->id ?? null,
                    'balance_snapshot' => $balanceSnapshot,
                ]
            );

            // Dispatch events
            event(new PaymentReconciled($payment, $allocations, $carryForward));

            if ($carryForward) {
                event(new CarryForwardCreated($carryForward, $account));
            }

            // Check if any bills are now fully paid
            foreach ($updatedBills as $bill) {
                if ($bill->status === 'paid') {
                    event(new BillPaid($bill));
                }
            }

            Log::info("Completed reconciliation for payment #{$payment->id}", [
                'allocations_count' => $allocations->count(),
                'total_allocated' => $totalAllocated,
                'carry_forward_amount' => $carryForward?->amount ?? 0,
            ]);

            return new ReconciliationResult(
                payment: $payment->fresh(),
                allocations: $allocations,
                totalAllocated: $totalAllocated,
                remainingAmount: $remainingAmount,
                carryForward: $carryForward,
                updatedBills: $updatedBills,
                balanceSnapshot: $balanceSnapshot
            );
        });
    }

    /**
     * Allocate payment to oldest bills (FIFO strategy)
     * 
     * @param Payment $payment
     * @return Collection Collection of PaymentAllocation
     */
    public function allocateToOldestBills(Payment $payment): Collection
    {
        $allocations = collect();
        $remainingAmount = $payment->amount;

        // Apply any existing carry-forward credits first
        $carryForwardCredit = CarryForwardBalance::where('account_id', $payment->account_id)
            ->where('type', 'credit')
            ->where('balance', '>', 0)
            ->sum('balance');

        $effectivePaymentAmount = $payment->amount + $carryForwardCredit;

        // Get outstanding bills ordered by due date (FIFO)
        $outstandingBills = Billing::where('account_id', $payment->account_id)
            ->whereIn('status', ['pending', 'partially_paid'])
            ->orderBy('due_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($outstandingBills as $bill) {
            if ($remainingAmount <= config('reconciliation.minimum_allocation', 0.01)) {
                break;
            }

            $billBalance = $this->getBillBalance($bill);
            $allocationAmount = min($remainingAmount, $billBalance);

            if ($allocationAmount > config('reconciliation.minimum_allocation', 0.01)) {
                $allocation = $this->allocateToSpecificBill(
                    $payment,
                    $bill,
                    $allocationAmount
                );

                $allocations->push($allocation);
                $remainingAmount -= $allocationAmount;

                // Update bill status
                $this->updateBillStatus($bill);
            }
        }

        // Consume carry-forward credits
        if ($carryForwardCredit > 0) {
            $this->consumeCarryForwardCredits($payment->account_id, $payment->amount);
        }

        return $allocations;
    }

    /**
     * Allocate payment to specific bill
     * 
     * @param Payment $payment
     * @param Billing $billing
     * @param float $amount
     * @return PaymentAllocation
     */
    public function allocateToSpecificBill(
        Payment $payment,
        Billing $billing,
        float $amount
    ): PaymentAllocation {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Allocation amount must be greater than zero');
        }

        $allocation = PaymentAllocation::create([
            'payment_id' => $payment->id,
            'billing_id' => $billing->id,
            'allocated_amount' => $amount,
            'allocation_date' => now(),
            'notes' => "Auto-allocated via FIFO reconciliation",
        ]);

        Log::debug("Allocated payment to bill", [
            'payment_id' => $payment->id,
            'billing_id' => $billing->id,
            'amount' => $amount,
        ]);

        return $allocation;
    }

    /**
     * Handle partial payment scenario
     * 
     * @param Payment $payment
     * @param Billing $billing
     * @return void
     */
    public function handlePartialPayment(Payment $payment, Billing $billing): void
    {
        if ($payment->amount >= $this->getBillBalance($billing)) {
            return; // Not a partial payment
        }

        $allocation = $this->allocateToSpecificBill(
            $payment,
            $billing,
            $payment->amount
        );

        $billing->update([
            'status' => 'partially_paid',
        ]);

        Log::info("Handled partial payment", [
            'payment_id' => $payment->id,
            'billing_id' => $billing->id,
            'amount' => $payment->amount,
            'bill_balance' => $this->getBillBalance($billing),
        ]);
    }

    /**
     * Handle overpayment scenario by creating carry-forward
     * 
     * @param Payment $payment
     * @param float $excessAmount
     * @return CarryForwardBalance
     */
    public function handleOverpayment(
        Payment $payment,
        float $excessAmount
    ): CarryForwardBalance {
        $carryForward = $this->createCarryForward(
            Account::findOrFail($payment->account_id),
            $excessAmount,
            "Overpayment from payment #{$payment->reference}"
        );

        Log::info("Created carry-forward for overpayment", [
            'payment_id' => $payment->id,
            'excess_amount' => $excessAmount,
            'carry_forward_id' => $carryForward->id,
        ]);

        return $carryForward;
    }

    /**
     * Create carry-forward balance
     * 
     * @param Account $account
     * @param float $amount
     * @param string $reason
     * @return CarryForwardBalance
     */
    public function createCarryForward(
        Account $account,
        float $amount,
        string $reason
    ): CarryForwardBalance {
        $carryForward = CarryForwardBalance::create([
            'account_id' => $account->id,
            'billing_period' => now()->format('Y-m'),
            'balance' => $amount,
            'type' => 'credit',
            'description' => $reason,
            'status' => 'active',
        ]);

        $this->auditService->logBillingAction(
            'carry_forward_created',
            null,
            [
                'account_id' => $account->id,
                'amount' => $amount,
                'reason' => $reason,
                'carry_forward_id' => $carryForward->id,
            ]
        );

        return $carryForward;
    }

    /**
     * Get current account balance with detailed breakdown
     * 
     * @param Account $account
     * @return array
     */
    public function getAccountBalance(Account $account): array
    {
        $totalDue = Billing::where('account_id', $account->id)
            ->whereIn('status', ['pending', 'partially_paid'])
            ->sum('total_amount');

        $totalPaid = PaymentAllocation::whereHas('payment', function ($query) use ($account) {
            $query->where('account_id', $account->id)
                ->where('status', 'completed');
        })->sum('allocated_amount');

        $carryForwardCredit = CarryForwardBalance::where('account_id', $account->id)
            ->where('type', 'credit')
            ->where('status', 'active')
            ->sum('balance');

        $currentBalance = $totalDue - $totalPaid;
        $netBalance = $currentBalance - $carryForwardCredit;

        $outstandingBills = Billing::where('account_id', $account->id)
            ->whereIn('status', ['pending', 'partially_paid'])
            ->with('details')
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function ($bill) {
                return [
                    'id' => $bill->id,
                    'billing_period' => $bill->billing_period,
                    'total_amount' => $bill->total_amount,
                    'paid_amount' => $this->getBillPaidAmount($bill),
                    'balance' => $this->getBillBalance($bill),
                    'due_date' => $bill->due_date,
                    'status' => $bill->status,
                ];
            });

        $recentPayments = Payment::where('account_id', $account->id)
            ->where('status', 'completed')
            ->with('allocations.billing')
            ->orderBy('payment_date', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'payment_date' => $payment->payment_date,
                    'method' => $payment->method,
                    'reference' => $payment->reference,
                    'allocations' => $payment->allocations->map(function ($allocation) {
                        return [
                            'billing_id' => $allocation->billing_id,
                            'billing_period' => $allocation->billing->billing_period,
                            'allocated_amount' => $allocation->allocated_amount,
                        ];
                    }),
                ];
            });

        return [
            'total_due' => round($totalDue, 2),
            'total_paid' => round($totalPaid, 2),
            'current_balance' => round($currentBalance, 2),
            'carry_forward_credit' => round($carryForwardCredit, 2),
            'net_balance' => round($netBalance, 2),
            'outstanding_bills' => $outstandingBills,
            'recent_payments' => $recentPayments,
            'calculated_at' => now()->toISOString(),
        ];
    }

    /**
     * Generate reconciliation report for a payment
     * 
     * @param Payment $payment
     * @return array
     */
    public function generateReconciliationReport(Payment $payment): array
    {
        $allocations = PaymentAllocation::where('payment_id', $payment->id)
            ->with('billing')
            ->get();

        $carryForward = CarryForwardBalance::where('account_id', $payment->account_id)
            ->where('description', 'like', "%payment #{$payment->reference}%")
            ->first();

        return [
            'payment' => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'payment_date' => $payment->payment_date,
                'method' => $payment->method,
                'reference' => $payment->reference,
                'reconciliation_status' => $payment->reconciliation_status,
                'reconciled_at' => $payment->reconciled_at,
                'reconciled_by' => $payment->reconciled_by,
            ],
            'allocations' => $allocations->map(function ($allocation) {
                return [
                    'billing_id' => $allocation->billing_id,
                    'billing_period' => $allocation->billing->billing_period,
                    'allocated_amount' => $allocation->allocated_amount,
                    'allocation_date' => $allocation->allocation_date,
                    'bill_status' => $allocation->billing->status,
                ];
            }),
            'total_allocated' => $allocations->sum('allocated_amount'),
            'carry_forward' => $carryForward ? [
                'id' => $carryForward->id,
                'amount' => $carryForward->balance,
                'status' => $carryForward->status,
            ] : null,
            'account_balance' => $this->getAccountBalance(
                Account::findOrFail($payment->account_id)
            ),
        ];
    }

    /**
     * Reverse reconciliation (for corrections)
     * 
     * @param Payment $payment
     * @param string $reason
     * @return void
     */
    public function reverseReconciliation(Payment $payment, string $reason): void
    {
        if ($payment->reconciliation_status !== 'reconciled') {
            throw new InvalidArgumentException(
                "Payment #{$payment->id} is not reconciled"
            );
        }

        DB::transaction(function () use ($payment, $reason) {
            // Get all allocations
            $allocations = PaymentAllocation::where('payment_id', $payment->id)->get();

            // Reverse each allocation
            foreach ($allocations as $allocation) {
                $billing = Billing::findOrFail($allocation->billing_id);
                
                // Update bill status
                $newPaidAmount = $this->getBillPaidAmount($billing) - $allocation->allocated_amount;
                
                if ($newPaidAmount <= 0) {
                    $billing->update(['status' => 'pending']);
                } else {
                    $billing->update(['status' => 'partially_paid']);
                }

                // Delete allocation
                $allocation->delete();
            }

            // Remove any carry-forward created by this payment
            CarryForwardBalance::where('account_id', $payment->account_id)
                ->where('description', 'like', "%payment #{$payment->reference}%")
                ->delete();

            // Update payment status
            $payment->update([
                'reconciliation_status' => 'pending',
                'reconciled_at' => null,
                'reconciled_by' => null,
            ]);

            // Log audit trail
            $this->auditService->logPaymentAction(
                'reconciliation_reversed',
                $payment,
                [
                    'reason' => $reason,
                    'reversed_by' => Auth::id(),
                    'reversed_at' => now(),
                ]
            );

            Log::warning("Reversed reconciliation for payment #{$payment->id}", [
                'reason' => $reason,
                'allocations_count' => $allocations->count(),
            ]);
        });
    }

    /**
     * Apply manual allocations
     * 
     * @param Payment $payment
     * @param array $manualAllocations
     * @param float &$remainingAmount
     * @return Collection
     */
    protected function applyManualAllocations(
        Payment $payment,
        array $manualAllocations,
        float &$remainingAmount
    ): Collection {
        $allocations = collect();

        foreach ($manualAllocations as $allocationData) {
            $billing = Billing::findOrFail($allocationData['billing_id']);
            $amount = min($allocationData['amount'], $remainingAmount);

            if ($amount > 0) {
                $allocation = $this->allocateToSpecificBill($payment, $billing, $amount);
                $allocations->push($allocation);
                $remainingAmount -= $amount;
                $this->updateBillStatus($billing);
            }
        }

        return $allocations;
    }

    /**
     * Update bill status based on payments
     * 
     * @param Billing $billing
     * @return void
     */
    protected function updateBillStatus(Billing $billing): void
    {
        $paidAmount = $this->getBillPaidAmount($billing);
        $balance = $billing->total_amount - $paidAmount;

        if ($balance <= config('reconciliation.minimum_allocation', 0.01)) {
            $billing->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        } elseif ($paidAmount > 0) {
            $billing->update(['status' => 'partially_paid']);
        }
    }

    /**
     * Get bill paid amount from allocations
     * 
     * @param Billing $billing
     * @return float
     */
    protected function getBillPaidAmount(Billing $billing): float
    {
        return PaymentAllocation::where('billing_id', $billing->id)
            ->whereHas('payment', function ($query) {
                $query->where('status', 'completed');
            })
            ->sum('allocated_amount');
    }

    /**
     * Get bill balance (total - paid)
     * 
     * @param Billing $billing
     * @return float
     */
    protected function getBillBalance(Billing $billing): float
    {
        return $billing->total_amount - $this->getBillPaidAmount($billing);
    }

    /**
     * Consume carry-forward credits
     * 
     * @param int $accountId
     * @param float $amountUsed
     * @return void
     */
    protected function consumeCarryForwardCredits(int $accountId, float $amountUsed): void
    {
        $credits = CarryForwardBalance::where('account_id', $accountId)
            ->where('type', 'credit')
            ->where('status', 'active')
            ->where('balance', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        $remainingToConsume = $amountUsed;

        foreach ($credits as $credit) {
            if ($remainingToConsume <= 0) {
                break;
            }

            $consumeAmount = min($credit->balance, $remainingToConsume);
            $credit->balance -= $consumeAmount;
            
            if ($credit->balance <= 0) {
                $credit->status = 'consumed';
            }
            
            $credit->save();
            $remainingToConsume -= $consumeAmount;
        }
    }
}