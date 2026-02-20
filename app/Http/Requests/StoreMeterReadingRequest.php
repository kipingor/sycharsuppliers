<?php

namespace App\Http\Requests;

use App\Models\Meter;
use App\Models\MeterReading;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store Meter Reading Request
 *
 * Validates meter reading creation with business rules.
 *
 * @package App\Http\Requests
 */
class StoreMeterReadingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', MeterReading::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'meter_id' => [
                'required',
                'integer',
                'exists:meters,id',
            ],
            'reading_value' => [
                'required',
                'numeric',
                'min:0',
            ],
            'reading_date' => [
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
                'required',
                Rule::in(['actual', 'estimated', 'calculated']),
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
            // Validate meter exists and is active
            if ($this->meter_id) {
                $this->validateMeter($validator);
            }

            // Validate reading value
            if ($this->reading !== null && $this->meter_id) {
                $this->validateReadingValue($validator);
            }

            // Validate reading date
            if ($this->reading_date && $this->meter_id) {
                $this->validateReadingDate($validator);
            }

            // Validate reading type
            if ($this->reading_type === 'estimated') {
                $this->validateEstimatedReading($validator);
            }

            // Validate calculated readings are from bulk meters
            if ($this->reading_type === 'calculated') {
                $this->validateCalculatedReading($validator);
            }
        });
    }

    /**
     * Validate meter
     */
    protected function validateMeter($validator): void
    {
        $meter = Meter::find($this->meter_id);

        if (!$meter) {
            $validator->errors()->add('meter_id', 'Meter not found');
            return;
        }

        // Check if meter is active
        if (!$meter->isActive()) {
            $validator->errors()->add('meter_id', 'Cannot record reading for inactive meter');
        }

        // Check if meter's account is active
        if (!$meter->account->isActive()) {
            $validator->errors()->add('meter_id', 'Cannot record reading - account is inactive');
        }
    }

    /**
     * Validate reading value
     */
    protected function validateReadingValue($validator): void
    {
        $meter = Meter::find($this->meter_id);

        if (!$meter) {
            return;
        }

        // Get previous reading
        $previousReading = MeterReading::where('meter_id', $meter->id)
            ->where('reading_date', '<', $this->reading_date)
            ->latest('reading_date')
            ->first();

        if ($previousReading) {
            // Current reading should not be less than previous (unless meter reset)
            if ($this->reading < $previousReading->reading) {
                // Check if this is a reasonable reset scenario
                $difference = $previousReading->reading - $this->reading;

                // If difference is huge, likely meter was reset
                if ($difference > 1000) {
                    $validator->warnings()->add(
                        'reading_value',
                        'Reading is lower than previous reading. This appears to be a meter reset. Please confirm.'
                    );
                } else {
                    $validator->errors()->add(
                        'reading_value',
                        sprintf(
                            'Reading (%s) is lower than previous reading (%s). Please verify.',
                            $this->reading,
                            $previousReading->reading
                        )
                    );
                }
            }

            // Check for unreasonably high consumption
            $consumption = $this->reading - $previousReading->reading;
            $avgConsumption = $meter->getAverageMonthlyConsumption();

            if ($avgConsumption > 0 && $consumption > ($avgConsumption * 5)) {
                $validator->warnings()->add(
                    'reading_value',
                    sprintf(
                        'Consumption (%s units) is significantly higher than average (%s units). Please verify.',
                        round($consumption, 2),
                        round($avgConsumption, 2)
                    )
                );
            }
        }

        // Check for unreasonably high reading value
        if ($this->reading > 999999) {
            $validator->errors()->add('reading_value', 'Reading value seems unreasonably high. Please verify.');
        }
    }

    /**
     * Validate reading date
     */
    protected function validateReadingDate($validator): void
    {
        $meter = Meter::find($this->meter_id);

        if (!$meter) {
            return;
        }

        // Check for duplicate readings on same date
        $existingReading = MeterReading::where('meter_id', $meter->id)
            ->whereDate('reading_date', $this->reading_date)
            ->first();

        if ($existingReading) {
            $validator->errors()->add(
                'reading_date',
                'A reading already exists for this meter on this date'
            );
        }

        // Check if reading date is too far in the past
        $maxBackdate = config('billing.generation.max_backdate_months', 3);
        $cutoffDate = now()->subMonths($maxBackdate);

        if ($this->reading_date < $cutoffDate->toDateString()) {
            $validator->errors()->add(
                'reading_date',
                "Cannot record readings more than {$maxBackdate} months in the past"
            );
        }

        // Get most recent reading
        $latestReading = MeterReading::where('meter_id', $meter->id)
            ->latest('reading_date')
            ->first();

        if ($latestReading && $this->reading_date < $latestReading->reading_date->toDateString()) {
            $validator->warnings()->add(
                'reading_date',
                'Reading date is earlier than the most recent reading. This will be treated as a historical reading.'
            );
        }
    }

    /**
     * Validate estimated reading
     */
    protected function validateEstimatedReading($validator): void
    {
        // Check if user has permission to create estimated readings
        if (!$this->user()->can('createEstimated', MeterReading::class)) {
            $validator->errors()->add(
                'reading_type',
                'You do not have permission to create estimated readings'
            );
        }

        // Estimated readings should have notes explaining estimation
        if (empty($this->notes)) {
            $validator->warnings()->add(
                'notes',
                'Please provide notes explaining why this reading is estimated'
            );
        }
    }

    /**
     * Validate calculated reading
     */
    protected function validateCalculatedReading($validator): void
    {
        $meter = Meter::find($this->meter_id);

        if (!$meter) {
            return;
        }

        // Calculated readings should only be for sub-meters
        if (!$meter->isSubMeter()) {
            $validator->errors()->add(
                'reading_type',
                'Calculated readings can only be created for sub-meters'
            );
        }

        // Should have parent_reading_id (though not in this form)
        // This would be set by the bulk distribution process
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'meter_id.required' => 'Please select a meter',
            'meter_id.exists' => 'The selected meter does not exist',
            'reading.required' => 'Reading value is required',
            'reading.numeric' => 'Reading must be a number',
            'reading.min' => 'Reading cannot be negative',
            'reading_date.required' => 'Reading date is required',
            'reading_date.date' => 'Invalid date format',
            'reading_date.before_or_equal' => 'Reading date cannot be in the future',
            'reading_type.required' => 'Reading type is required',
            'reading_type.in' => 'Invalid reading type',
            'notes.max' => 'Notes cannot exceed 500 characters',
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        // Set reader_id to current user if not provided
        if (!$this->has('reader_id')) {
            $this->merge(['reader_id' => $this->user()->id]);
        }

        // Default reading type to actual
        if (!$this->has('reading_type') || $this->reading_type === 'regular') {
            $this->merge(['reading_type' => 'actual']);
        }

        // Set reading date to today if not provided
        if (!$this->has('reading_date')) {
            $this->merge(['reading_date' => now()->toDateString()]);
        }
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
