<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('expense'));
    }

    public function rules(): array
    {
        return [
            'category'       => ['sometimes', 'required', 'string', 'max:255'],
            'description'    => ['sometimes', 'required', 'string', 'max:500'],
            'amount'         => ['sometimes', 'required', 'numeric', 'min:0.01', 'max:999999.99'],
            'expense_date'   => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'receipt_number' => ['nullable', 'string', 'max:255'],
            'receipt_file'   => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
