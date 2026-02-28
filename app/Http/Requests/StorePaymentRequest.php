<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store Payment Request
 *
 * Validates payment creation with comprehensive rules.
 *
 * @package App\Http\Requests
 */
class StorePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Payment::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where('status', 'active'),
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:9999999.99',
            ],
            'payment_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'method' => [
                'required',
                'string',
                Rule::in(['Cash', 'Bank Transfer', 'M-Pesa', 'Card', 'Cheque']),
            ],
            'reference' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('payments', 'reference')->whereNull('deleted_at'),
            ],
            'transaction_id' => [
                'nullable',
                'string',
                'max:255',
            ],
            'status' => [
                'required',
                'string',
                Rule::in(['pending', 'completed', 'failed']),
            ],
            
            // Optional: specify meter (deprecated but supported)
            'meter_id' => [
                'nullable',
                'integer',
                'exists:meters,id',
            ],
            
            // Optional: specify billing (deprecated but supported)
            'billing_id' => [
                'nullable',
                'integer',
                'exists:billings,id',
            ],
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'Please select an account for this payment.',
            'account_id.exists' => 'The selected account must be active.',
            'amount.required' => 'Payment amount is required.',
            'amount.min' => 'Payment amount must be at least 0.01.',
            'amount.max' => 'Payment amount cannot exceed 9,999,999.99.',
            'payment_date.required' => 'Payment date is required.',
            'payment_date.before_or_equal' => 'Payment date cannot be in the future.',
            'method.required' => 'Payment method is required.',
            'method.in' => 'Invalid payment method selected.',
            'reference.unique' => 'This payment reference has already been used.',
        ];
    }

    /**
     * Get custom attribute names
     */
    public function attributes(): array
    {
        return [
            'account_id' => 'account',
            'payment_date' => 'payment date',
            'transaction_id' => 'transaction ID',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that account has outstanding balance if auto-reconciliation is enabled
            if (config('reconciliation.auto_reconcile') && $this->status === 'completed') {
                $account = \App\Models\Account::find($this->account_id);
                
                if ($account && $account->getCurrentBalance() <= 0) {
                    $validator->errors()->add(
                        'account_id',
                        'This account has no outstanding balance. Payment will create a credit.'
                    );
                }
            }

            // Validate that if meter_id is provided, it belongs to the account
            if ($this->meter_id && $this->account_id) {
                $meter = \App\Models\Meter::find($this->meter_id);
                if ($meter && $meter->account_id !== $this->account_id) {
                    $validator->errors()->add(
                        'meter_id',
                        'The selected meter does not belong to the specified account.'
                    );
                }
            }

            // Validate that if billing_id is provided, it belongs to the account
            if ($this->billing_id && $this->account_id) {
                $billing = \App\Models\Billing::find($this->billing_id);
                if ($billing && $billing->account_id !== $this->account_id) {
                    $validator->errors()->add(
                        'billing_id',
                        'The selected bill does not belong to the specified account.'
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
        // Set default status if not provided
        if (!$this->has('status')) {
            $this->merge([
                'status' => 'completed',
            ]);
        }

        // Generate reference if not provided
        if (!$this->has('reference') || empty($this->reference)) {
            $this->merge([
                'reference' => 'PAY-' . now()->format('YmdHis') . '-' . rand(1000, 9999),
            ]);
        }

        // Set reconciliation status
        $this->merge([
            'reconciliation_status' => 'pending',
        ]);
    }
}
