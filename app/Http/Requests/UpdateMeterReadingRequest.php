<?php

namespace App\Http\Requests;

use App\Models\MeterReading;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update Meter Reading Request
 * 
 * Validates meter reading updates with strict business rules.
 * 
 * @package App\Http\Requests
 */
class UpdateMeterReadingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $reading = $this->route('meterReading');
        return $this->user()->can('update', $reading);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'reading_value' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
            ],
            'reading_date' => [
                'sometimes',
                'required',
                'date',
                'before_or_equal:today',
            ],
            'reader_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'reading_type' => [
                'sometimes',
                'required',
                Rule::in(['actual', 'estimated', 'calculated']),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $reading = $this->route('meterReading');

            // Check if reading has been used in billing
            if ($this->hasChangesToCriticalFields()) {
                $this->validateNotUsedInBilling($validator, $reading);
            }

            // Check if reading has been distributed (for bulk meters)
            if ($reading->is_distributed) {
                $validator->errors()->add('reading_value', 
                    'Cannot update reading that has been distributed to sub-meters'
                );
            }

            // Validate reading value changes
            if ($this->has('reading_value') && $this->reading_value !== $reading->reading_value) {
                $this->validateReadingChange($validator, $reading);
            }

            // Validate date changes
            if ($this->has('reading_date') && $this->reading_date !== $reading->reading_date->toDateString()) {
                $this->validateDateChange($validator, $reading);
            }

            // Validate type changes
            if ($this->has('reading_type') && $this->reading_type !== $reading->reading_type) {
                $this->validateTypeChange($validator, $reading);
            }
        });
    }

    /**
     * Check if request has changes to critical fields
     */
    protected function hasChangesToCriticalFields(): bool
    {
        return $this->has('reading_value') || $this->has('reading_date') || $this->has('reading_type');
    }

    /**
     * Validate reading is not used in billing
     */
    protected function validateNotUsedInBilling($validator, MeterReading $reading): void
    {
        $hasBeenBilled = $reading->meter->billingDetails()
            ->where(function ($query) use ($reading) {
                $query->where('previous_reading_value', $reading->reading_value)
                    ->orWhere('current_reading_value', $reading->reading_value);
            })
            ->exists();

        if ($hasBeenBilled) {
            // Only admins can update billed readings
            if (!$this->user()->can('update', $reading)) {
                $validator->errors()->add('reading_value', 
                    'Cannot update reading that has been used in billing'
                );
            } else {
                $validator->errors()->add('reading_value', 
                    'Warning: This reading has been used in billing. Updating it may affect bills.'
                );
            }
        }
    }

    /**
     * Validate reading value change
     */
    protected function validateReadingChange($validator, MeterReading $reading): void
    {
        $meter = $reading->meter;
        $newReading = $this->reading_value;

        // Check against previous reading
        $previousReading = MeterReading::where('meter_id', $meter->id)
            ->where('reading_date', '<', $reading->reading_date)
            ->latest('reading_date')
            ->first();

        if ($previousReading && $newReading < $previousReading->reading_value) {
            $difference = $previousReading->reading_value - $newReading;
            
            if ($difference > 1000) {
                $validator->errors()->add('reading_value', 
                    'New reading is significantly lower than previous reading. Appears to be a meter reset.'
                );
            } else {
                $validator->errors()->add('reading_value', 
                    sprintf(
                        'New reading (%s) is lower than previous reading (%s)',
                        $newReading,
                        $previousReading->reading_value
                    )
                );
            }
        }

        // Check against next reading
        $nextReading = MeterReading::where('meter_id', $meter->id)
            ->where('reading_date', '>', $reading->reading_date)
            ->oldest('reading_date')
            ->first();

        if ($nextReading && $newReading > $nextReading->reading_value) {
            $validator->errors()->add('reading_value', 
                sprintf(
                    'New reading (%s) is higher than next reading (%s)',
                    $newReading,
                    $nextReading->reading_value
                )
            );
        }

        // Check for unreasonable consumption change
        if ($previousReading) {
            $oldConsumption = $reading->reading_value - $previousReading->reading_value;
            $newConsumption = $newReading - $previousReading->reading_value;
            $change = abs($newConsumption - $oldConsumption);

            if ($change > ($meter->getAverageMonthlyConsumption() * 2)) {
                $validator->errors()->add('reading_value', 
                    sprintf(
                        'Reading change affects consumption significantly (old: %s, new: %s units)',
                        round($oldConsumption, 2),
                        round($newConsumption, 2)
                    )
                );
            }
        }
    }

    /**
     * Validate reading date change
     */
    protected function validateDateChange($validator, MeterReading $reading): void
    {
        $meter = $reading->meter;
        $newDate = $this->reading_date;

        // Check for duplicate on new date
        $existingOnDate = MeterReading::where('meter_id', $meter->id)
            ->where('id', '!=', $reading->id)
            ->whereDate('reading_date', $newDate)
            ->first();

        if ($existingOnDate) {
            $validator->errors()->add('reading_date', 
                'Another reading already exists for this date'
            );
        }

        // Check if new date creates sequence issues
        $previousReading = MeterReading::where('meter_id', $meter->id)
            ->where('id', '!=', $reading->id)
            ->where('reading_date', '<', $newDate)
            ->latest('reading_date')
            ->first();

        $nextReading = MeterReading::where('meter_id', $meter->id)
            ->where('id', '!=', $reading->id)
            ->where('reading_date', '>', $newDate)
            ->oldest('reading_date')
            ->first();

        // Validate reading value still makes sense with new date
        if ($previousReading && $reading->reading_value < $previousReading->reading_value) {
            $validator->errors()->add('reading_date', 
                'Date change places this reading after a higher reading'
            );
        }

        if ($nextReading && $reading->reading_value > $nextReading->reading_value) {
            $validator->errors()->add('reading_date', 
                'Date change places this reading before a lower reading'
            );
        }
    }

    /**
     * Validate reading type change
     */
    protected function validateTypeChange($validator, MeterReading $reading): void
    {
        $newType = $this->reading_type;

        // Cannot change to calculated unless it's a sub-meter
        if ($newType === 'calculated' && !$reading->meter->isSubMeter()) {
            $validator->errors()->add('reading_type', 
                'Only sub-meter readings can be marked as calculated'
            );
        }

        // Cannot change from calculated if distributed
        if ($reading->reading_type === 'calculated' && $reading->parent_reading_id) {
            $validator->errors()->add('reading_type', 
                'Cannot change type of distributed reading'
            );
        }

        // Warn if changing from actual to estimated
        if ($reading->reading_type === 'actual' && $newType === 'estimated') {
            $validator->errors()->add('reading_type', 
                'Changing from actual to estimated. Please provide reason in notes.'
            );
        }

        // Check permission for estimated readings
        if ($newType === 'estimated' && !$this->user()->can('createEstimated', MeterReading::class)) {
            $validator->errors()->add('reading_type', 
                'You do not have permission to mark readings as estimated'
            );
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'reading_value.numeric' => 'Reading must be a number',
            'reading_value.min' => 'Reading cannot be negative',
            'reading_date.date' => 'Invalid date format',
            'reading_date.before_or_equal' => 'Reading date cannot be in the future',
            'reading_type.in' => 'Invalid reading type',
            'notes.max' => 'Notes cannot exceed 500 characters',
        ];
    }

    /**
     * Get validated data with transformations
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Round reading to 2 decimal places
        if (isset($validated['reading_value'])) {
            $validated['reading_value'] = round($validated['reading_value'], 2);
        }

        return $validated;
    }
}