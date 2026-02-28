<?php

namespace App\DTOs;

/**
 * Payment Allocation DTO
 *
 * Represents a single allocation of payment amount to a billing.
 * Used when distributing a payment across multiple outstanding bills.
 *
 * @package App\DTOs
 */
class PaymentAllocation
{
    /**
     * Create a new payment allocation DTO
     *
     * @param int $paymentId ID of the payment being allocated
     * @param int $billingId ID of the billing receiving the allocation
     * @param float $allocatedAmount Amount allocated from payment to this bill
     * @param \Carbon\Carbon $allocatedAt When the allocation was made
     * @param string|null $notes Optional notes about this allocation
     */
    public function __construct(
        public int $paymentId,
        public int $billingId,
        public float $allocatedAmount,
        public \Carbon\Carbon $allocatedAt,
        public ?string $notes = null
    ) {
    }

    /**
     * Create from model instance
     */
    public static function fromModel(\App\Models\PaymentAllocation $allocation): self
    {
        return new self(
            paymentId: $allocation->payment_id,
            billingId: $allocation->billing_id,
            allocatedAmount: $allocation->allocated_amount,
            allocatedAt: $allocation->allocated_at,
            notes: $allocation->notes ?? null
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'billing_id' => $this->billingId,
            'allocated_amount' => $this->allocatedAmount,
            'allocated_at' => $this->allocatedAt->toIso8601String(),
            'notes' => $this->notes,
        ];
    }
}
