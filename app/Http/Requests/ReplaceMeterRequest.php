<?php

namespace App\Http\Requests;

use App\Models\Meter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReplaceMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('meter'));
    }

    public function rules(): array
    {
        return [
            'meter_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('meters', 'meter_number'),
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
            'final_reading' => [
                'required',
                'numeric',
                'min:0',
                'max:999999',
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Meter $meter */
            $meter = $this->route('meter');

            if (!$meter->isFaulty()) {
                $validator->errors()->add('meter', 'Only faulty meters can be replaced');
                return;
            }

            if ($meter->replaced_by_meter_id) {
                $validator->errors()->add('meter', 'This meter has already been replaced');
            }

            if (!$meter->account?->isActive()) {
                $validator->errors()->add('meter', 'The account must be active to replace its meter');
            }

            if ($meter->parent_meter_id && !$meter->parentMeter?->isActive()) {
                $validator->errors()->add('meter', 'The parent meter must be active to replace this sub-meter');
            }

            if (!is_numeric($this->input('final_reading'))) {
                return;
            }

            $latestReading = $meter->readings()->latest('reading_date')->first();

            if ($latestReading && $latestReading->reading_date->gt(today())) {
                $validator->errors()->add('final_reading', 'A future reading exists for this meter');
            } elseif ($latestReading && (float) $this->input('final_reading') < (float) $latestReading->reading_value) {
                $validator->errors()->add(
                    'final_reading',
                    "The final reading cannot be lower than the meter's latest recorded reading"
                );
            }

            $todayReading = $meter->readings()
                ->whereDate('reading_date', today())
                ->first();

            if (
                $todayReading
                && round((float) $this->input('final_reading'), 2) !== round((float) $todayReading->reading_value, 2)
            ) {
                $validator->errors()->add(
                    'final_reading',
                    'A reading already exists today; the final reading must match it'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'meter_number.unique' => 'This meter number is already in use',
            'final_reading.required' => "Enter the faulty meter's final reading",
            'initial_reading.required' => "Enter the replacement meter's initial reading",
        ];
    }
}
