<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $employee = $this->route('employee');
        return $this->user()->can('update', $employee);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');

        return [
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'phone' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('employees', 'phone')->ignore($employee->id)],
            'idnumber' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('employees', 'idnumber')->ignore($employee->id)],
            'position' => ['sometimes', 'required', 'string', 'max:255'],
            'salary' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999.99'],
            'hire_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'terminated'])],
        ];
    }
}
