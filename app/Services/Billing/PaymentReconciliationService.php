<?php

namespace App\Services\Billing;

use App\DTOs\ReconciliationResult;
use App\Events\Billing\BillPaid;
use App\Events\Billing\CarryForwardCreated;
use App\Events\Billing\PaymentReconciled;
use App\Models\Account;
use App\Models\Billing;
use App\Models\CarryForwardBalance;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\Audit\AuditService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Payment Reconciliation Service
 *
 * FIXED: Event dispatch now passes ReconciliationResult object instead of separate parameters
 */
class PaymentReconciliationService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Reconcile a payment to outstanding bills
     *
     * ✅ FIXED: Creates ReconciliationResult before dispatching event
     */
    public function reconcilePayment(
        Payment $payment,
        ?array $manualAllocations = null
    ): ReconciliationResult {
        if ($payment->reconciliation_status === 'reconciled') {
            throw new \InvalidArgumentException(
                "Payment #{$payment->id} is already fully reconciled"
            );
        }

        $account = $payment->account;

        if (!$account) {
            throw new \InvalidArgumentException(
                "Payment #{$payment->id} has no associated account"
            );
        }

        return DB::transaction(function () use ($payment, $account, $manualAllocations) {
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
                    'carry_forward' => $carryForward?->id ?? null,
                    'balance_snapshot' => $balanceSnapshot,
                ]
            );

            // ✅ FIXED: Create ReconciliationResult FIRST
            $result = new ReconciliationResult(
                payment: $payment->fresh(),
                allocations: $allocations,
                totalAllocated: $totalAllocated,
                remainingAmount: $remainingAmount,
                carryForward: $carryForward,
                updatedBills: $updatedBills,
                balanceSnapshot: $balanceSnapshot
            );

            // ✅ FIXED: Dispatch event with correct parameters (Payment and ReconciliationResult)
            event(new PaymentReconciled($payment, $result));

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

            return $result;
        });
    }

    /**
     * Allocate payment to oldest bills first (FIFO)
     */
    protected function allocateToOldestBills(Payment $payment): Collection
    {
        $allocations = collect();
        $remainingAmount = $payment->amount;

        // Get outstanding bills for this account, oldest first
        $bills = Billing::where('account_id', $payment->account_id)
            ->where('status', '!=', 'paid')
            ->where('amount_due', '>', 0)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        foreach ($bills as $bill) {
            if ($remainingAmount <= config('reconciliation.minimum_allocation', 0.01)) {
                break;
            }

            $amountToAllocate = min($remainingAmount, $bill->amount_due);

            $allocation = PaymentAllocation::create([
                'payment_id' => $payment->id,
                'billing_id' => $bill->id,
                'allocated_amount' => $amountToAllocate,
                'allocation_date' => now(),
            ]);

            $allocations->push($allocation);

            // Update bill
            // $bill->paid_amount += $amountToAllocate;
            $bill->amount_due -= $amountToAllocate;

            if ($bill->amount_due <= config('reconciliation.minimum_allocation', 0.01)) {
                $bill->amount_due = 0;
                $bill->status = 'paid';
                $bill->paid_at = now();
            } else {
                $bill->status = 'partially_paid';
            }

            $bill->save();

            $remainingAmount -= $amountToAllocate;

            Log::info("Allocated payment to bill", [
                'payment_id' => $payment->id,
                'bill_id' => $bill->id,
                'amount' => $amountToAllocate,
                'bill_balance_remaining' => $bill->amount_due,
            ]);
        }

        return $allocations;
    }

    /**
     * Apply manual allocations specified by user
     */
    protected function applyManualAllocations(
        Payment $payment,
        array $manualAllocations,
        float $remainingAmount
    ): Collection {
        $allocations = collect();

        foreach ($manualAllocations as $allocationData) {
            $bill = Billing::findOrFail($allocationData['billing_id']);

            // Validate bill belongs to payment's account
            if ($bill->account_id !== $payment->account_id) {
                throw new \InvalidArgumentException(
                    "Bill #{$bill->id} does not belong to the same account as payment #{$payment->id}"
                );
            }

            // Validate allocation amount
            $amount = $allocationData['amount'];
            if ($amount > $bill->amount_due) {
                throw new \InvalidArgumentException(
                    "Cannot allocate {$amount} to bill #{$bill->id} - balance is only {$bill->amount_due}"
                );
            }

            if ($amount > $remainingAmount) {
                throw new \InvalidArgumentException(
                    "Cannot allocate {$amount} - only {$remainingAmount} remaining in payment"
                );
            }

            // Create allocation
            $allocation = PaymentAllocation::create([
                'payment_id' => $payment->id,
                'billing_id' => $bill->id,
                'allocated_amount' => $amount,
                'allocated_at' => now(),
            ]);

            $allocations->push($allocation);

            // Update bill
            // $bill->paid_amount += $amount;
            $bill->amount_due -= $amount;

            if ($bill->amount_due <= config('reconciliation.minimum_allocation', 0.01)) {
                $bill->amount_due = 0;
                $bill->status = 'paid';
                $bill->paid_at = now();
            } else {
                $bill->status = 'partially_paid';
            }

            $bill->save();

            $remainingAmount -= $amount;
        }

        return $allocations;
    }

    /**
     * Handle overpayment by creating carry-forward balance
     */
    protected function handleOverpayment(Payment $payment, float $amount): ?CarryForwardBalance
    {
        if ($amount <= config('reconciliation.minimum_allocation', 0.01)) {
            return null;
        }

        $carryForward = CarryForwardBalance::create([
            'account_id' => $payment->account_id,
            'payment_id' => $payment->id,
            'amount' => $amount,
            'amount_due' => $amount,
            'status' => 'active',
            'created_at' => now(),
        ]);

        Log::info("Created carry-forward balance", [
            'payment_id' => $payment->id,
            'amount' => $amount,
            'carry_forward_id' => $carryForward->id,
        ]);

        return $carryForward;
    }

    /**
     * Get current account balance
     */
    protected function getAccountBalance(Account $account): float
    {
        $totalBilled = Billing::where('account_id', $account->id)
            ->whereNotIn('status', ['voided'])
            ->sum('total_amount');

        $totalPaid = Payment::where('account_id', $account->id)
            ->where('status', 'completed')
            ->sum('amount');

        return $totalBilled - $totalPaid;
    }

    /**
     * Generate reconciliation report for a payment
     */
    public function generateReconciliationReport(Payment $payment): array
    {
        $payment->load(['account', 'allocations.billing.details']);
        $allocations = $payment->allocations;
        $totalAllocated = $allocations->sum('allocated_amount');
        $remainingAmount = $payment->amount - $totalAllocated;
        $carryForward = CarryForwardBalance::where('payment_id', $payment->id)->first();

        $allocationDetails = $allocations->map(function ($allocation) {
            $billing = $allocation->billing;
            return [
                'allocation_id' => $allocation->id,
                'billing_id' => $billing->id,
                'billing_period' => $billing->billing_period,
                'billing_total' => $billing->total_amount,
                'billing_status' => $billing->status,
                'allocated_amount' => $allocation->allocated_amount,
                'allocation_date' => $allocation->allocation_date ?? $allocation->created_at,
            ];
        })->toArray();

        $accountBalance = $this->getAccountBalance($payment->account);

        return [
            'payment' => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'payment_date' => $payment->payment_date->format('Y-m-d'),
                'method' => $payment->method,
                'reference' => $payment->reference,
                'status' => $payment->status,
                'reconciliation_status' => $payment->reconciliation_status,
            ],
            'account' => [
                'id' => $payment->account->id,
                'name' => $payment->account->name,
                'account_number' => $payment->account->account_number,
                'current_balance' => $accountBalance,
            ],
            'allocation_summary' => [
                'total_payment' => $payment->amount,
                'total_allocated' => $totalAllocated,
                'remaining_amount' => $remainingAmount,
                'allocation_count' => $allocations->count(),
                'fully_allocated' => $remainingAmount <= 0.01,
            ],
            'allocations' => $allocationDetails,
            'carry_forward' => $carryForward ? [
                'id' => $carryForward->id,
                'amount' => $carryForward->amount,
                'balance' => $carryForward->balance,
                'status' => $carryForward->status,
            ] : null,
        ];
    }

    /**
     * Reverse a payment reconciliation
     */
    public function reverseReconciliation(Payment $payment): void
    {
        if ($payment->reconciliation_status !== 'reconciled') {
            throw new \InvalidArgumentException(
                "Cannot reverse - payment is not reconciled"
            );
        }

        DB::transaction(function () use ($payment) {
            // Get all allocations
            $allocations = PaymentAllocation::where('payment_id', $payment->id)->get();

            foreach ($allocations as $allocation) {
                $bill = $allocation->billing;

                // Reverse the allocation
                // $bill->paid_amount -= $allocation->allocated_amount;
                $bill->amount_due += $allocation->allocated_amount;

                // Update bill status
                if ($bill->status === 'paid') {
                    $bill->status = $bill->amount_due >= $bill->total_amount ? 'pending' : 'partially_paid';
                    $bill->paid_at = null;
                }

                $bill->save();

                // Delete allocation
                $allocation->delete();
            }

            // Handle carry-forward reversal
            $carryForward = CarryForwardBalance::where('payment_id', $payment->id)->first();
            if ($carryForward) {
                $carryForward->delete();
            }

            // Update payment
            $payment->update([
                'reconciliation_status' => 'pending',
                'reconciled_at' => null,
                'reconciled_by' => null,
            ]);

            // Log audit
            $this->auditService->logPaymentAction(
                'reconciliation_reversed',
                $payment,
                [
                    'allocations_reversed' => $allocations->count(),
                    'reversed_by' => Auth::id(),
                ]
            );

            Log::info("Reversed reconciliation for payment #{$payment->id}");
        });
    }
}
