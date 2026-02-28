<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Billing Resource
 * 
 * Transforms Billing model for API responses.
 * 
 * @package App\Http\Resources
 */
class BillingResource extends JsonResource
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
            'billing_period' => $this->billing_period,
            'total_amount' => (float) $this->total_amount,
            'paid_amount' => (float) $this->paid_amount,
            'balance' => (float) $this->balance,
            'late_fee' => $this->late_fee ? (float) $this->late_fee : null,
            'status' => $this->status,
            'issued_at' => $this->issued_at->toIso8601String(),
            'due_date' => $this->due_date->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'is_paid' => $this->isPaid(),
            'is_partially_paid' => $this->isPartiallyPaid(),
            'is_overdue' => $this->isOverdue(),
            'days_overdue' => $this->getDaysOverdue(),
            
            // Include details if loaded
            'details' => $this->when($this->relationLoaded('details'), function () {
                return $this->details->map(function ($detail) {
                    return [
                        'id' => $detail->id,
                        'meter_id' => $detail->meter_id,
                        'meter' => [
                            'number' => $detail->meter->meter_number,
                            'name' => $detail->meter->meter_name,
                        ],
                        'previous_reading' => (float) $detail->previous_reading,
                        'current_reading' => (float) $detail->current_reading,
                        'consumption' => (float) $detail->units,
                        'rate' => (float) $detail->rate,
                        'amount' => (float) $detail->amount,
                        'description' => $detail->description,
                    ];
                });
            }),

            // Include payments if loaded
            'payments' => $this->when($this->relationLoaded('payments'), function () {
                return $this->payments->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => (float) $payment->amount,
                        'method' => $payment->method,
                        'reference' => $payment->reference,
                        'payment_date' => $payment->payment_date->toIso8601String(),
                    ];
                });
            }),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
