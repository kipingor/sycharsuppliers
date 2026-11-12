<?php

namespace App\Http\Requests\Settings;

use App\Models\SystemSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', SystemSetting::class) ?? false;
    }

    public function rules(): array
    {
        $group = $this->route('group');

        return match ($group) {
            'email' => [
                'mailer' => ['required', Rule::in(['smtp', 'resend', 'mailersend'])],
                'host' => ['required_if:mailer,smtp', 'nullable', 'string', 'max:255'],
                'port' => ['required_if:mailer,smtp', 'nullable', 'integer', 'between:1,65535'],
                'username' => ['nullable', 'string', 'max:255'],
                'password' => ['nullable', 'string', 'max:4096'],
                'api_key' => ['nullable', 'string', 'max:4096'],
                'encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])],
                'from_address' => ['required', 'email', 'max:255'],
                'from_name' => ['required', 'string', 'max:255'],
            ],
            'banking' => [
                'bank_accounts' => ['required', 'array', 'min:1', 'max:10'],
                'bank_accounts.*.bank_name' => ['required', 'string', 'max:120'],
                'bank_accounts.*.account_name' => ['required', 'string', 'max:120'],
                'bank_accounts.*.account_number' => ['required', 'string', 'max:60'],
                'bank_accounts.*.branch' => ['nullable', 'string', 'max:120'],
                'bank_accounts.*.swift' => ['nullable', 'string', 'max:20'],
                'bank_accounts.*.currency' => ['required', 'string', 'size:3'],
            ],
            'mpesa' => [
                'paybill_number' => ['nullable', 'string', 'max:32'],
                'account_number' => ['nullable', 'string', 'max:64'],
                'till_number' => ['nullable', 'string', 'max:32'],
                'consumer_key' => ['nullable', 'string', 'max:4096'],
                'consumer_secret' => ['nullable', 'string', 'max:4096'],
                'passkey' => ['nullable', 'string', 'max:4096'],
                'environment' => ['required', Rule::in(['sandbox', 'production'])],
                'shortcode' => ['nullable', 'string', 'max:32'],
                'initiator_name' => ['nullable', 'string', 'max:120'],
                'security_credential' => ['nullable', 'string', 'max:4096'],
            ],
            'general' => [
                'company_name' => ['required', 'string', 'max:255'],
                'logo' => ['nullable', 'string', 'max:2048'],
                'address' => ['nullable', 'string', 'max:1000'],
                'phone' => ['nullable', 'string', 'max:32'],
                'email' => ['nullable', 'email', 'max:255'],
                'pin_vat_number' => ['nullable', 'string', 'max:64'],
                'default_unit_rate' => ['nullable', 'numeric', 'min:0'],
                'billing_day' => ['required', 'integer', 'between:1,28'],
                'due_days' => ['required', 'integer', 'between:1,90'],
                'late_fee_enabled' => ['required', 'boolean'],
                'late_fee_percentage' => ['required', 'numeric', 'between:0,100'],
                'email_notifications' => ['required', 'boolean'],
                'sms_notifications' => ['required', 'boolean'],
                'feature_flags' => ['sometimes', 'array'],
                'feature_flags.*' => ['boolean'],
            ],
            default => [],
        };
    }
}
