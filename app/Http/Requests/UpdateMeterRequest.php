<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\Meter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update Meter Request
 * 
 * Validates meter updates including allocation changes.
 * 
 * @package App\Http\Requests
 */
class UpdateMeterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $meter = $this->route('meter');
        return $this->user()->can('update', $meter);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $meter = $this->route('meter');

        return [
            'account_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:accounts,id',
            ],
            'resident_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:residents,id',
            ],
            'meter_number' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('meters', 'meter_number')->ignore($meter->id),
            ],
            'meter_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'type' => [
                'sometimes',
                'required',
                Rule::in(['water', 'sewer']),
            ],
            'meter_type' => [
                'sometimes',
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
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['active', 'inactive', 'faulty']),
            ],
            'installed_at' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $meter = $this->route('meter');

            // Validate account change
            if ($this->has('account_id') && $this->account_id !== $meter->account_id) {
                $this->validateAccountChange($validator, $meter);
            }

            // Validate meter type change
            if ($this->has('meter_type') && $this->meter_type !== $meter->meter_type) {
                $this->validateMeterTypeChange($validator, $meter);
            }

            // Validate parent meter changes
            if ($this->has('parent_meter_id') && $this->parent_meter_id !== $meter->parent_meter_id) {
                $this->validateParentMeterChange($validator, $meter);
            }

            // Validate allocation percentage changes
            if ($this->has('allocation_percentage') && $this->allocation_percentage !== $meter->allocation_percentage) {
                $this->validateAllocationChange($validator, $meter);
            }

            // Validate status change
            if ($this->has('status') && $this->status !== $meter->status) {
                $this->validateStatusChange($validator, $meter);
            }
        });
    }

    /**
     * Validate account change
     */
    protected function validateAccountChange($validator, Meter $meter): void
    {
        // Check if meter has readings
        if ($meter->readings()->exists()) {
            $validator->errors()->add('account_id', 'Cannot change account for meter with existing readings');
            return;
        }

        // Check if meter has billing history
        if ($meter->billingDetails()->exists()) {
            $validator->errors()->add('account_id', 'Cannot change account for meter with billing history');
            return;
        }

        // Validate new account is active
        $account = Account::find($this->account_id);
        if (!$account || !$account->isActive()) {
            $validator->errors()->add('account_id', 'New account must be active');
        }
    }

    /**
     * Validate meter type change (individual/bulk)
     */
    protected function validateMeterTypeChange($validator, Meter $meter): void
    {
        // Cannot change bulk to individual if has sub-meters
        if ($meter->meter_type === 'bulk' && $this->meter_type === 'individual') {
            if ($meter->hasSubMeters()) {
                $validator->errors()->add('meter_type', 'Cannot change bulk meter to individual while it has sub-meters');
            }
        }

        // Cannot change individual to bulk if has parent
        if ($meter->meter_type === 'individual' && $this->meter_type === 'bulk') {
            if ($meter->parent_meter_id) {
                $validator->errors()->add('meter_type', 'Cannot change sub-meter to bulk meter');
            }
        }

        // Check if meter has readings - type change could affect billing
        if ($meter->readings()->exists()) {
            $validator->errors()->add('meter_type', 'Cannot change meter type for meter with existing readings');
        }
    }

    /**
     * Validate parent meter change
     */
    protected function validateParentMeterChange($validator, Meter $meter): void
    {
        // If removing parent (setting to null)
        if ($this->parent_meter_id === null) {
            // OK to remove parent
            return;
        }

        $parentMeter = Meter::find($this->parent_meter_id);

        if (!$parentMeter) {
            $validator->errors()->add('parent_meter_id', 'Parent meter not found');
            return;
        }

        // Parent must be bulk meter
        if (!$parentMeter->isBulkMeter()) {
            $validator->errors()->add('parent_meter_id', 'Parent meter must be a bulk meter');
        }

        // Parent must be same account
        $accountId = $this->account_id ?? $meter->account_id;
        if ($parentMeter->account_id !== $accountId) {
            $validator->errors()->add('parent_meter_id', 'Parent meter must belong to the same account');
        }

        // Current meter cannot be bulk
        $meterType = $this->meter_type ?? $meter->meter_type;
        if ($meterType === 'bulk') {
            $validator->errors()->add('parent_meter_id', 'Bulk meters cannot have a parent');
        }
    }

    /**
     * Validate allocation percentage change
     */
    protected function validateAllocationChange($validator, Meter $meter): void
    {
        // Must have parent meter
        if (!$meter->parent_meter_id && !$this->parent_meter_id) {
            $validator->errors()->add('allocation_percentage', 'Only sub-meters can have allocation percentages');
            return;
        }

        $parentMeterId = $this->parent_meter_id ?? $meter->parent_meter_id;
        $parentMeter = Meter::find($parentMeterId);

        if (!$parentMeter) {
            return; // Will be caught by parent validation
        }

        // Calculate new total allocation
        $currentTotal = $parentMeter->getTotalSubMeterAllocation();
        $oldAllocation = $meter->allocation_percentage ?? 0;
        $newAllocation = $this->allocation_percentage ?? 0;
        
        $newTotal = $currentTotal - $oldAllocation + $newAllocation;

        if ($newTotal > 100.01) { // Allow small rounding error
            $validator->errors()->add('allocation_percentage', 
                sprintf(
                    'Total allocation would exceed 100%% (current: %.2f%%, change: %.2f%% → %.2f%%)',
                    $currentTotal,
                    $oldAllocation,
                    $newAllocation
                )
            );
        }
    }

    /**
     * Validate status change
     */
    protected function validateStatusChange($validator, Meter $meter): void
    {
        // If deactivating a bulk meter
        if ($this->status === 'inactive' && $meter->isBulkMeter()) {
            $activeSubMeters = $meter->subMeters()->active()->count();
            if ($activeSubMeters > 0) {
                $validator->errors()->add('status', 
                    "Cannot deactivate bulk meter with {$activeSubMeters} active sub-meter(s)"
                );
            }
        }

        // If activating a sub-meter, parent must be active
        if ($this->status === 'active' && $meter->parent_meter_id) {
            $parentMeter = $meter->parentMeter;
            if (!$parentMeter || !$parentMeter->isActive()) {
                $validator->errors()->add('status', 'Cannot activate sub-meter when parent meter is inactive');
            }
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'account_id.exists' => 'The selected account does not exist',
            'meter_number.unique' => 'This meter number is already in use',
            'allocation_percentage.min' => 'Allocation must be at least 0.01%',
            'allocation_percentage.max' => 'Allocation cannot exceed 100%',
            'installed_at.before_or_equal' => 'Installation date cannot be in the future',
        ];
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
