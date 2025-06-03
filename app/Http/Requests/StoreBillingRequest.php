<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBillingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'meter_id' => 'required|exists:meters,id',
            // 'reading_value' => 'required|numeric|min:0',
            'reading_value' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) {
                    $lastReading = MeterReading::where('meter_id', $this->input('meter_id'))
                        ->latest('reading_date')
                        ->value('reading_value');
    
                    if ($lastReading !== null && $value <= $lastReading) {
                        $fail("The new reading must be greater than the last reading ({$lastReading}).");
                    }
                },
            ],
        ];
    }
}
