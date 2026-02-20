<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Employee::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'phone' => ['required', 'string', 'max:20', 'unique:employees,phone'],
            'idnumber' => ['required', 'string', 'max:20', 'unique:employees,idnumber'],
            'position' => ['required', 'string', 'max:255'],
            'salary' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'hire_date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'terminated'])],
        ];
    }
}
