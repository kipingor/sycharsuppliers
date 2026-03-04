<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Expense::class);
    }

    public function rules(): array
    {
        return [
            'category'       => ['required', 'string', 'max:255'],
            'description'    => ['required', 'string', 'max:500'],
            'amount'         => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'expense_date'   => ['required', 'date', 'before_or_equal:today'],
            'receipt_number' => ['nullable', 'string', 'max:255'],
            'receipt_file'   => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], // 5 MB
        ];
    }
}
