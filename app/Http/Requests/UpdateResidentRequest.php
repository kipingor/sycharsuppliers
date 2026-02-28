<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Resident;

class UpdateResidentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $resident = $this->route('resident');
        return $this->user()->can('update', $resident);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $resident = $this->route('resident');
        
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|lowercase|email|max:255|unique:residents,email,' . $resident->id,
            'phone' => 'required|string|unique:residents,phone,' . $resident->id,
            'address' => 'nullable|string|max:255',
        ];
    }
}
