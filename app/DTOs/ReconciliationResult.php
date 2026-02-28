<?php

namespace App\DTOs;

use App\Models\Billing;
use App\Models\CarryForwardBalance;
use App\Models\Payment;
use Illuminate\Support\Collection;

/**
 * Reconciliation Result Data Transfer Object
 * 
 * Encapsulates the result of a payment reconciliation operation
 * 
 * @package App\DTOs
 */
readonly class ReconciliationResult
{
    public function __construct(
        public Payment $payment,
        public Collection $allocations, // Collection of PaymentAllocation
        public float $totalAllocated,
        public float $remainingAmount,
        public ?CarryForwardBalance $carryForward,
        public Collection $updatedBills, // Collection of Billing
        public float $balanceSnapshot
    ) {}

    /**
     * Check if payment was fully reconciled
     */
    public function isFullyReconciled(): bool
    {
        return $this->remainingAmount < 0.01;
    }

    /**
     * Check if there was an overpayment
     */
    public function hasOverpayment(): bool
    {
        return $this->carryForward !== null;
    }

    /**
     * Get count of allocations
     */
    public function getAllocationCount(): int
    {
        return $this->allocations->count();
    }

    /**
     * Get count of bills affected
     */
    public function getAffectedBillsCount(): int
    {
        return $this->updatedBills->count();
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'payment_amount' => $this->payment->amount,
            'total_allocated' => round($this->totalAllocated, 2),
            'remaining_amount' => round($this->remainingAmount, 2),
            'is_fully_reconciled' => $this->isFullyReconciled(),
            'allocations' => $this->allocations->map(function ($allocation) {
                return [
                    'id' => $allocation->id,
                    'billing_id' => $allocation->billing_id,
                    'allocated_amount' => $allocation->allocated_amount,
                    'allocation_date' => $allocation->allocation_date?->toISOString(),
                ];
            })->toArray(),
            'carry_forward' => $this->carryForward ? [
                'id' => $this->carryForward->id,
                'amount' => $this->carryForward->balance,
                'billing_period' => $this->carryForward->billing_period,
                'status' => $this->carryForward->status,
            ] : null,
            'updated_bills' => $this->updatedBills->map(function ($bill) {
                return [
                    'id' => $bill->id,
                    'billing_period' => $bill->billing_period,
                    'total_amount' => $bill->total_amount,
                    'status' => $bill->status,
                ];
            })->toArray(),
            'balance_snapshot' => $this->balanceSnapshot,
            'reconciled_at' => $this->payment->reconciled_at?->toISOString(),
        ];
    }

    /**
     * Get summary string
     */
    public function getSummary(): string
    {
        $summary = sprintf(
            "Payment #%s (%.2f) reconciled: %.2f allocated to %d bill(s)",
            $this->payment->reference ?? $this->payment->id,
            $this->payment->amount,
            $this->totalAllocated,
            $this->getAllocationCount()
        );

        if ($this->hasOverpayment()) {
            $summary .= sprintf(
                ", %.2f carry-forward created",
                $this->carryForward->balance
            );
        }

        return $summary;
    }
}