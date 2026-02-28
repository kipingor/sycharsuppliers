<?php

namespace App\DTOs;

use App\Models\Billing;
use App\Models\CarryForwardBalance as CarryForwardBalanceModel;
use App\Models\Payment;
use Illuminate\Support\Collection;

/**
 * Carry Forward Balance DTO
 *
 * Encapsulates the complete result of a payment reconciliation operation.
 * Contains all data about how a payment was allocated to bills.
 *
 * @package App\DTOs
 */
class CarryForwardBalance
{
    /**
     * Create a new carry forward balance
     *
     * @param Payment $payment The payment that was reconciled
     * @param Collection<\App\Models\PaymentAllocation> $allocations Collection of payment allocations created
     * @param float $totalAllocated Total amount allocated to bills
     * @param float $remainingAmount Amount remaining after allocation (overpayment)
     * @param CarryForwardBalance|null $carryForward Carry-forward balance if overpayment occurred
     * @param Collection<Billing> $updatedBills Collection of bills that were updated
     * @param float $balanceSnapshot Current account balance after reconciliation
     */
    public function __construct(
        public Payment $payment,
        public Collection $allocations,
        public float $totalAllocated,
        public float $remainingAmount,
        public ?CarryForwardBalanceModel $carryForwardModel,
        public Collection $updatedBills,
        public float $balanceSnapshot
    ) {
    }

    /**
     * Check if payment was fully allocated
     */
    public function isFullyAllocated(): bool
    {
        return $this->remainingAmount <= 0.01;
    }

    /**
     * Check if payment resulted in overpayment
     */
    public function hasOverpayment(): bool
    {
        return $this->remainingAmount > 0.01;
    }

    /**
     * Check if any bills were fully paid
     */
    public function hasFullyPaidBills(): bool
    {
        return $this->updatedBills->contains(fn ($bill) => $bill->status === 'paid');
    }

    /**
     * Get count of bills affected
     */
    public function getAffectedBillsCount(): int
    {
        return $this->updatedBills->count();
    }

    /**
     * Get count of allocations made
     */
    public function getAllocationsCount(): int
    {
        return $this->allocations->count();
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'payment_amount' => $this->payment->amount,
            'allocations' => $this->allocations->map(fn ($a) => [
                'billing_id' => $a->billing_id,
                'allocated_amount' => $a->allocated_amount,
            ])->toArray(),
            'total_allocated' => $this->totalAllocated,
            'remaining_amount' => $this->remainingAmount,
            'carry_forward_id' => $this->carryForwardModel?->id,
            'carry_forward_amount' => $this->carryForwardModel?->balance,
            'updated_bills_count' => $this->updatedBills->count(),
            'fully_paid_bills' => $this->updatedBills
                ->where('status', 'paid')
                ->pluck('id')
                ->toArray(),
            'balance_snapshot' => $this->balanceSnapshot,
            'fully_allocated' => $this->isFullyAllocated(),
            'has_overpayment' => $this->hasOverpayment(),
        ];
    }

    /**
     * Get summary message
     */
    public function getSummary(): string
    {
        $message = "Payment #{$this->payment->id} of {$this->payment->amount} reconciled. ";
        $message .= "Allocated {$this->totalAllocated} to {$this->getAllocationsCount()} bill(s). ";
        
        if ($this->hasOverpayment()) {
            $message .= "Overpayment of {$this->remainingAmount} carried forward. ";
        }
        
        if ($this->hasFullyPaidBills()) {
            $fullyPaidCount = $this->updatedBills->where('status', 'paid')->count();
            $message .= "{$fullyPaidCount} bill(s) fully paid.";
        }
        
        return $message;
    }
}
