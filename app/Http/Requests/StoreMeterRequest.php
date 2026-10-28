<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\Meter;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store Meter Request
 * 
 * Validates meter creation including bulk meter validation.
 * 
 * @package App\Http\Requests
 */
class StoreMeterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Meter::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_id' => [
                'required',
                'integer',
                'exists:accounts,id',
            ],
            'resident_id' => [
                'nullable',
                'integer',
                'exists:residents,id',
            ],
            'meter_number' => [
                'required',
                'string',
                'max:50',
                'unique:meters,meter_number',
            ],
            'meter_name' => [
                'required',
                'string',
                'max:100',
            ],
            'location' => [
                'required',
                'string',
                'max:255',
            ],
            'type' => [
                'required',
                Rule::in(['water', 'sewer']),
            ],
            'meter_type' => [
                'required',
                Rule::in(['individual', 'bulk']),
            ],
            'parent_meter_id' => [
                'nullable',
                'integer',
                'exists:meters,id',
                'different:id',
            ],
            'allocation_percentage' => [
                'nullable',
                'numeric',
                'min:0.01',
                'max:100',
                'required_with:parent_meter_id',
            ],
            'status' => [
                'required',
                Rule::in(['active', 'inactive', 'faulty']),
            ],
            'installed_at' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],
            'initial_reading' => [
                'required',
                'numeric',
                'min:0',
                'max:999999',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate account is active
            if ($this->account_id) {
                $account = Account::find($this->account_id);
                
                if (!$account || !$account->isActive()) {
                    $validator->errors()->add('account_id', 'Account must be active to add meters');
                }

                if ($this->resident_id) {
                    $resident = Resident::find($this->resident_id);

                    if ($resident && !$resident->status) {
                        $validator->errors()->add('resident_id', 'Resident must be active');
                    } elseif ($resident && $resident->account_number !== $account?->account_number) {
                        $validator->errors()->add('resident_id', 'Resident must belong to the selected account');
                    }
                }
            }

            // Validate parent meter relationship
            if ($this->parent_meter_id) {
                $this->validateParentMeter($validator);
            }

            // Validate bulk meter cannot have parent
            if ($this->meter_type === 'bulk' && $this->parent_meter_id) {
                $validator->errors()->add('meter_type', 'Bulk meters cannot have a parent meter');
            }

            // Validate individual meter without parent doesn't have allocation
            if ($this->meter_type === 'individual' && !$this->parent_meter_id && $this->allocation_percentage) {
                $validator->errors()->add('allocation_percentage', 'Only sub-meters can have allocation percentages');
            }
        });
    }

    /**
     * Validate parent meter relationship
     */
    protected function validateParentMeter($validator): void
    {
        $parentMeter = Meter::find($this->parent_meter_id);

        if (!$parentMeter) {
            $validator->errors()->add('parent_meter_id', 'Parent meter not found');
            return;
        }

        // Parent must be bulk meter
        if (!$parentMeter->isBulkMeter()) {
            $validator->errors()->add('parent_meter_id', 'Parent meter must be a bulk meter');
        }

        // Parent must be active
        if (!$parentMeter->isActive()) {
            $validator->errors()->add('parent_meter_id', 'Parent meter must be active');
        }

        // Parent must be same account
        if ($parentMeter->account_id !== $this->account_id) {
            $validator->errors()->add('parent_meter_id', 'Parent meter must belong to the same account');
        }

        // Check if adding this allocation would exceed 100%
        if ($this->allocation_percentage) {
            $currentTotal = $parentMeter->getTotalSubMeterAllocation();
            $newTotal = $currentTotal + $this->allocation_percentage;

            if ($newTotal > 100.01) { // Allow small rounding error
                $validator->errors()->add('allocation_percentage', 
                    sprintf(
                        'Total allocation would exceed 100%% (current: %.2f%%, adding: %.2f%%)',
                        $currentTotal,
                        $this->allocation_percentage
                    )
                );
            }
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'Please select an account',
            'account_id.exists' => 'The selected account does not exist',
            'meter_number.required' => 'Meter number is required',
            'meter_number.unique' => 'This meter number is already in use',
            'meter_name.required' => 'Meter name is required',
            'location.required' => 'Meter location is required',
            'type.required' => 'Please select a service (water/sewer)',
            'meter_type.required' => 'Please specify if this is an individual or bulk meter',
            'allocation_percentage.required_with' => 'Allocation percentage is required for sub-meters',
            'allocation_percentage.min' => 'Allocation must be at least 0.01%',
            'allocation_percentage.max' => 'Allocation cannot exceed 100%',
            'installed_at.before_or_equal' => 'Installation date cannot be in the future',
            'initial_reading.required' => 'An initial meter reading is required',
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        // Set default status if not provided
        if (!$this->has('status')) {
            $this->merge(['status' => 'active']);
        }

        // Ensure allocation_percentage is null if no parent
        if (!$this->parent_meter_id) {
            $this->merge(['allocation_percentage' => null]);
        }
    }

    /**
     * Get validated data with transformations
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Round allocation percentage to 2 decimals
        if (isset($validated['allocation_percentage'])) {
            $validated['allocation_percentage'] = round($validated['allocation_percentage'], 2);
        }

        return $validated;
    }
}