<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
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
            'meter_id' => ['required', 'exists:meters,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => ['required', 'string', 'in:M-Pesa,Bank Transfer,Cash'],
            'transaction_id' => ['required', 'string'],
            'payment_date' => ['required', 'date'],
            'status' => ['required', 'string', 'in:completed,pending,failed'],
        ];
    }
}
