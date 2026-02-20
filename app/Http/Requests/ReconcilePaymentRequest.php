<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reconcile Payment Request
 * 
 * Validates manual payment reconciliation with optional allocation instructions.
 * 
 * @package App\Http\Requests
 */
class ReconcilePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $payment = $this->route('payment');
        return $this->user()->can('reconcile', $payment);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $payment = $this->route('payment');

        return [
            'manual_allocations' => [
                'nullable',
                'array',
                'min:1',
            ],
            'manual_allocations.*.billing_id' => [
                'required',
                'integer',
                Rule::exists('billings', 'id')
                    ->where('account_id', $payment->account_id)
                    ->whereIn('status', ['pending', 'partially_paid']),
            ],
            'manual_allocations.*.amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'manual_allocations.array' => 'Manual allocations must be an array.',
            'manual_allocations.min' => 'At least one allocation is required for manual reconciliation.',
            'manual_allocations.*.billing_id.required' => 'Each allocation must specify a bill.',
            'manual_allocations.*.billing_id.exists' => 'One or more selected bills are invalid or already paid.',
            'manual_allocations.*.amount.required' => 'Each allocation must specify an amount.',
            'manual_allocations.*.amount.min' => 'Allocation amount must be at least 0.01.',
        ];
    }

    /**
     * Get custom attribute names
     */
    public function attributes(): array
    {
        return [
            'manual_allocations' => 'allocations',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $payment = $this->route('payment');

            // Check if payment can be reconciled
            if (!$payment->canBeReconciled()) {
                $validator->errors()->add(
                    'payment',
                    'This payment cannot be reconciled (status: ' . $payment->reconciliation_status . ')'
                );
                return;
            }

            // If manual allocations provided, validate total doesn't exceed payment amount
            if ($this->has('manual_allocations')) {
                $totalAllocated = collect($this->manual_allocations)
                    ->sum('amount');

                if ($totalAllocated > $payment->amount) {
                    $validator->errors()->add(
                        'manual_allocations',
                        sprintf(
                            'Total allocated amount (%.2f) exceeds payment amount (%.2f)',
                            $totalAllocated,
                            $payment->amount
                        )
                    );
                }

                // Validate each allocation doesn't exceed bill balance
                foreach ($this->manual_allocations as $index => $allocation) {
                    $billing = \App\Models\Billing::find($allocation['billing_id']);
                    
                    if ($billing) {
                        $billBalance = $billing->balance;
                        
                        if ($allocation['amount'] > $billBalance) {
                            $validator->errors()->add(
                                "manual_allocations.{$index}.amount",
                                sprintf(
                                    'Allocation amount (%.2f) exceeds bill balance (%.2f)',
                                    $allocation['amount'],
                                    $billBalance
                                )
                            );
                        }
                    }
                }

                // Check for duplicate billing_ids
                $billingIds = collect($this->manual_allocations)->pluck('billing_id');
                if ($billingIds->count() !== $billingIds->unique()->count()) {
                    $validator->errors()->add(
                        'manual_allocations',
                        'Cannot allocate to the same bill multiple times'
                    );
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // If no manual allocations provided, set to null for auto-allocation
        if (!$this->has('manual_allocations') || empty($this->manual_allocations)) {
            $this->merge([
                'manual_allocations' => null,
            ]);
        }
    }
}
