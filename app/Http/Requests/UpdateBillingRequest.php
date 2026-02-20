<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Billing;

class UpdateBillingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $bill = Billing::find($this->route('billing'));
        return $bill && $this->user()->can('update', $bill);
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
            'billing_period' => 'required|date_format:Y-m',
            'units_used' => 'required|integer|min:1',
            'amount_due' => 'required|numeric|min:0',
            'status' => 'required|in:pending,paid,overdue',
        ];
    }
}
