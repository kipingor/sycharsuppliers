<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $expense = $this->route('expense');
        return $this->user()->can('update', $expense);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:500'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999.99'],
            'expense_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'receipt_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
