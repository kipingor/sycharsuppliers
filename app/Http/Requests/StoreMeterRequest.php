<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeterRequest extends FormRequest
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
            'resident_id' => 'required|exists:residents,id',
            'meter_number' => 'required|string|unique:meters,meter_number',
            'meter_name' => 'required|string',
            'location' => 'nullable|string',
            'installation_date' => 'nullable|date',
            'status' => 'required|in:active,inactive,replaced',
        ];
    }
}
