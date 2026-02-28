<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Generate Bill Request
 * 
 * Validates bill generation requests with duplicate prevention.
 * 
 * @package App\Http\Requests
 */
class GenerateBillRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('generate', \App\Models\Billing::class);
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
            'billing_period' => [
                'required',
                'date_format:Y-m',
                'before_or_equal:' . now()->format('Y-m'),
            ],
            'force_regenerate' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'Please select an account.',
            'account_id.exists' => 'The selected account must be active.',
            'billing_period.required' => 'Billing period is required.',
            'billing_period.date_format' => 'Billing period must be in YYYY-MM format.',
            'billing_period.before_or_equal' => 'Cannot generate bills for future periods.',
        ];
    }

    /**
     * Get custom attribute names
     */
    public function attributes(): array
    {
        return [
            'account_id' => 'account',
            'billing_period' => 'billing period',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $account = \App\Models\Account::find($this->account_id);

            if (!$account) {
                return;
            }

            // Check if account has active meters
            if ($account->meters()->where('status', 'active')->count() === 0) {
                $validator->errors()->add(
                    'account_id',
                    'This account has no active meters. Please add meters before generating bills.'
                );
            }

            // Check for duplicate bill (unless force_regenerate is true)
            if (!$this->force_regenerate) {
                $existingBill = \App\Models\Billing::where('account_id', $this->account_id)
                    ->where('billing_period', $this->billing_period)
                    ->whereNotIn('status', ['voided'])
                    ->first();

                if ($existingBill) {
                    $validator->errors()->add(
                        'billing_period',
                        "A bill already exists for this account and period (Bill #{$existingBill->id}). " .
                        "Set force_regenerate=true to override."
                    );
                }
            }

            // Validate period is not too far in the past
            $maxBackdateMonths = config('billing.period.max_backdate_months', 3);
            $periodDate = \Carbon\Carbon::createFromFormat('Y-m', $this->billing_period);
            $oldestAllowed = now()->subMonths($maxBackdateMonths)->startOfMonth();

            if ($periodDate->lt($oldestAllowed)) {
                $validator->errors()->add(
                    'billing_period',
                    "Cannot generate bills for periods older than {$maxBackdateMonths} months."
                );
            }

            // Check for meter readings in the period
            $hasReadings = \App\Models\MeterReading::whereHas('meter', function ($query) {
                $query->where('account_id', $this->account_id);
            })
            ->whereYear('reading_date', $periodDate->year)
            ->whereMonth('reading_date', $periodDate->month)
            ->exists();

            if (!$hasReadings) {
                $validator->errors()->add(
                    'billing_period',
                    'No meter readings found for this period. Bill may use estimated readings.'
                );
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Default force_regenerate to false
        if (!$this->has('force_regenerate')) {
            $this->merge([
                'force_regenerate' => false,
            ]);
        }

        // Default billing_period to current month if not provided
        if (!$this->has('billing_period')) {
            $this->merge([
                'billing_period' => now()->format('Y-m'),
            ]);
        }
    }
}