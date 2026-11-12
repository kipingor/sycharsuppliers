<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SystemSettingsRequest;
use App\Services\SystemSettingsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly SystemSettingsService $settings) {}

    public function edit(Request $request): Response
    {
        $this->authorize('manage', \App\Models\SystemSetting::class);

        return Inertia::render('settings/system', [
            'settings' => $this->settings->publicGroups(),
            'defaults' => [
                'email' => [
                    'mailer' => config('mail.default'),
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => config('mail.mailers.smtp.port', 587),
                    'username' => config('mail.mailers.smtp.username'),
                    'encryption' => config('mail.mailers.smtp.scheme') === 'smtps' ? 'ssl' : 'tls',
                    'from_address' => config('mail.from.address'),
                    'from_name' => config('mail.from.name'),
                ],
                'banking' => [
                    'bank_accounts' => [[
                        'bank_name' => config('billing.company.bank_name', ''),
                        'account_name' => config('app.company_name', config('app.name')),
                        'account_number' => config('billing.company.bank_account_number', ''),
                        'branch' => config('billing.company.bank_branch', ''),
                        'swift' => '',
                        'currency' => 'KES',
                    ]],
                ],
                'mpesa' => [
                    'paybill_number' => config('billing.company.paybill_number', ''),
                    'account_number' => config('billing.company.mpesa_account_number', ''),
                    'environment' => config('services.mpesa.environment', 'sandbox'),
                    'shortcode' => config('services.mpesa.shortcode', ''),
                ],
                'general' => [
                    'company_name' => config('app.company_name', config('app.name')),
                    'logo' => config('app.company_logo', ''),
                    'address' => config('app.company_address', ''),
                    'phone' => config('app.company_phone', ''),
                    'email' => config('app.company_email', ''),
                    'default_unit_rate' => config('price.unit_price', 0),
                    'billing_day' => config('billing.generation.generation_day', 1),
                    'due_days' => config('billing.generation.due_days', 14),
                    'late_fee_enabled' => config('billing.late_fees.enabled', false),
                    'late_fee_percentage' => config('billing.late_fees.percentage', 0),
                    'email_notifications' => true,
                    'sms_notifications' => false,
                    'feature_flags' => [
                        'auto_bill_generation' => config('billing.generation.auto_generate', false),
                    ],
                ],
            ],
        ]);
    }

    public function update(SystemSettingsRequest $request, string $group): RedirectResponse
    {
        abort_unless(in_array($group, ['email', 'banking', 'mpesa', 'general'], true), 404);
        $this->settings->update($group, $request->validated());

        return back()->with('success', ucfirst($group).' settings saved.');
    }

    public function testEmail(Request $request): RedirectResponse
    {
        $this->authorize('manage', \App\Models\SystemSetting::class);
        $request->validate(['recipient' => ['required', 'email']]);
        $this->settings->applyRuntimeConfiguration();
        Mail::purge();

        try {
            Mail::raw('This is a test message from your system email configuration.', function ($message) use ($request) {
                $message->to($request->input('recipient'))->subject('System email configuration test');
            });
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Email test failed. Check the mail settings and application logs.');
        }

        return back()->with('success', 'Test email sent.');
    }

    public function testMpesa(): RedirectResponse
    {
        $this->authorize('manage', \App\Models\SystemSetting::class);
        $settings = $this->settings->get('mpesa');
        $consumerKey = $settings['consumer_key'] ?? config('services.mpesa.consumer_key');
        $consumerSecret = $settings['consumer_secret'] ?? config('services.mpesa.consumer_secret');
        if (empty($consumerKey) || empty($consumerSecret)) {
            return back()->with('error', 'Add the M-Pesa consumer key and secret before testing.');
        }

        $host = ($settings['environment'] ?? config('services.mpesa.environment', 'sandbox')) === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';

        try {
            Http::withBasicAuth($consumerKey, $consumerSecret)
                ->acceptJson()
                ->get("{$host}/oauth/v1/generate?grant_type=client_credentials")
                ->throw();
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'M-Pesa connection test failed. Check the credentials and environment.');
        }

        return back()->with('success', 'M-Pesa credentials connected successfully.');
    }
}
