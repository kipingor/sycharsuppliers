<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SystemSettingsService
{
    private const CACHE_KEY = 'system_settings.groups';

    private const SECRET_FIELDS = [
        'email' => ['password', 'api_key'],
        'mpesa' => ['consumer_key', 'consumer_secret', 'passkey', 'security_credential'],
    ];

    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => SystemSetting::query()
            ->get()
            ->mapWithKeys(fn (SystemSetting $setting) => [$setting->group => $setting->data])
            ->all());
    }

    public function get(string $group): array
    {
        return $this->all()[$group] ?? [];
    }

    public function update(string $group, array $data): void
    {
        $existing = $this->get($group);
        foreach (self::SECRET_FIELDS[$group] ?? [] as $field) {
            if (! isset($data[$field]) || $data[$field] === '') {
                unset($data[$field]);
            }
        }

        SystemSetting::query()->updateOrCreate(
            ['group' => $group],
            ['data' => array_replace($existing, $data)],
        );

        Cache::forget(self::CACHE_KEY);
        $this->applyRuntimeConfiguration();
    }

    public function applyRuntimeConfiguration(): void
    {
        $groups = $this->all();
        $email = $groups['email'] ?? [];
        $mailer = $email['mailer'] ?? config('mail.default');
        config([
            'mail.default' => $mailer,
            'mail.from.address' => $email['from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $email['from_name'] ?? config('mail.from.name'),
        ]);

        if ($mailer === 'smtp') {
            config(['mail.mailers.smtp' => array_replace(config('mail.mailers.smtp'), array_filter([
                'transport' => 'smtp',
                'host' => $email['host'] ?? null,
                'port' => isset($email['port']) ? (int) $email['port'] : null,
                'username' => $email['username'] ?? null,
                'password' => $email['password'] ?? null,
                'scheme' => ($email['encryption'] ?? null) === 'ssl' ? 'smtps' : 'smtp',
            ], fn ($value) => $value !== null))]);
        } elseif ($mailer === 'resend' && isset($email['api_key'])) {
            config([
                'mail.mailers.resend.api_key' => $email['api_key'],
                'services.resend.key' => $email['api_key'],
            ]);
        } elseif ($mailer === 'mailersend' && isset($email['api_key'])) {
            config(['services.mailersend.api_key' => $email['api_key']]);
        }

        $general = $groups['general'] ?? [];
        $banking = $groups['banking']['bank_accounts'][0] ?? [];
        $mpesa = $groups['mpesa'] ?? [];
        config([
            'app.company_name' => $general['company_name'] ?? config('app.company_name'),
            'app.company_logo' => $general['logo'] ?? config('app.company_logo'),
            'app.company_address' => $general['address'] ?? config('app.company_address'),
            'app.company_phone' => $general['phone'] ?? config('app.company_phone'),
            'app.company_email' => $general['email'] ?? config('app.company_email'),
            'billing.company.bank_name' => $banking['bank_name'] ?? config('billing.company.bank_name'),
            'billing.company.bank_account_number' => $banking['account_number'] ?? config('billing.company.bank_account_number'),
            'billing.company.bank_branch' => $banking['branch'] ?? config('billing.company.bank_branch'),
            'billing.company.paybill_number' => $mpesa['paybill_number'] ?? config('billing.company.paybill_number'),
            'billing.company.mpesa_account_number' => $mpesa['account_number'] ?? config('billing.company.mpesa_account_number'),
            'services.mpesa' => array_replace(config('services.mpesa', []), $mpesa),
            'billing.generation.generation_day' => $general['billing_day'] ?? config('billing.generation.generation_day'),
            'billing.generation.due_days' => $general['due_days'] ?? config('billing.generation.due_days'),
            'billing.generation.auto_generate' => $general['feature_flags']['auto_bill_generation'] ?? config('billing.generation.auto_generate'),
            'billing.late_fees.enabled' => $general['late_fee_enabled'] ?? config('billing.late_fees.enabled'),
            'billing.late_fees.percentage' => $general['late_fee_percentage'] ?? config('billing.late_fees.percentage'),
            'price.unit_price' => $general['default_unit_rate'] ?? config('price.unit_price'),
            'app.notifications' => [
                'email' => $general['email_notifications'] ?? true,
                'sms' => $general['sms_notifications'] ?? false,
            ],
        ]);
    }

    public function publicGroups(): array
    {
        $groups = $this->all();
        foreach (self::SECRET_FIELDS as $group => $fields) {
            foreach ($fields as $field) {
                unset($groups[$group][$field]);
                $groups[$group]["{$field}_configured"] = filled($this->get($group)[$field] ?? null);
            }
        }

        return $groups;
    }
}
