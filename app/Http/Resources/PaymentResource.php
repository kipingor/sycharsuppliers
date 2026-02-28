<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payment Resource
 * 
 * Transforms Payment model for API responses.
 * 
 * @package App\Http\Resources
 */
class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'account' => [
                'id' => $this->account->id,
                'account_number' => $this->account->account_number,
                'name' => $this->account->name,
            ],
            'amount' => (float) $this->amount,
            'method' => $this->method,
            'reference' => $this->reference,
            'payment_date' => $this->payment_date->toIso8601String(),
            'reconciliation_status' => $this->reconciliation_status,
            'reconciled_at' => $this->reconciled_at?->toIso8601String(),
            'is_reconciled' => $this->isReconciled(),
            'allocated_amount' => (float) $this->allocated_amount,
            'unallocated_amount' => (float) $this->unallocated_amount,
            
            // Include allocations if loaded
            'allocations' => $this->when($this->relationLoaded('allocations'), function () {
                return $this->allocations->map(function ($allocation) {
                    return [
                        'id' => $allocation->id,
                        'billing_id' => $allocation->billing_id,
                        'billing_period' => $allocation->billing->billing_period,
                        'allocated_amount' => (float) $allocation->allocated_amount,
                        'full_payment' => $allocation->fullPaysBill(),
                    ];
                });
            }),

            'notes' => $this->notes,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
