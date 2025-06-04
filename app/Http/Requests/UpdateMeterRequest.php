<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeterRequest extends FormRequest
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
            'meter_number' => 'required|string|unique:meters,meter_number,' . $this->meter->id,
            'meter_name' => 'required|string',
            'location' => 'nullable|string|max:255',
            'installation_date' => 'nullable|date',
            'status' => 'required|in:active,inactive,replaced',
        ];
    }
}
