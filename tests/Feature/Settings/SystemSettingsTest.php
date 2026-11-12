<?php

namespace Tests\Feature\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('system_settings.groups');
        Role::findOrCreate('admin', 'web');
    }

    public function test_admin_can_save_settings_and_sensitive_values_are_encrypted_and_not_shared(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->put(route('settings.system.update', 'email'), [
            'mailer' => 'resend',
            'from_address' => 'billing@example.test',
            'from_name' => 'Billing',
            'api_key' => 'secret-resend-key',
            'password' => '',
            'host' => '',
            'port' => 587,
            'username' => '',
            'encryption' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $stored = SystemSetting::where('group', 'email')->firstOrFail();
        $this->assertStringNotContainsString('secret-resend-key', $stored->getRawOriginal('data'));
        $this->assertSame('secret-resend-key', app(SystemSettingsService::class)->get('email')['api_key']);

        $this->actingAs($admin)->get(route('settings.system.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.email.api_key_configured', true)
                ->missing('settings.email.api_key'));
    }

    public function test_empty_secret_update_preserves_the_existing_secret(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        app(SystemSettingsService::class)->update('mpesa', [
            'consumer_key' => 'consumer-key',
            'consumer_secret' => 'consumer-secret',
            'passkey' => 'passkey',
            'environment' => 'sandbox',
        ]);

        $this->actingAs($admin)->put(route('settings.system.update', 'mpesa'), [
            'consumer_key' => '',
            'consumer_secret' => '',
            'passkey' => '',
            'environment' => 'production',
        ])->assertSessionHasNoErrors();

        $settings = app(SystemSettingsService::class)->get('mpesa');
        $this->assertSame('consumer-secret', $settings['consumer_secret']);
        $this->assertSame('production', $settings['environment']);
    }

    public function test_non_admin_cannot_view_or_update_system_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('settings.system.edit'))->assertForbidden();
        $this->actingAs($user)->put(route('settings.system.update', 'general'), [])->assertForbidden();
    }

    public function test_a_user_with_the_manage_settings_permission_can_manage_configuration(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo('manage-settings');

        $this->actingAs($manager)->get(route('settings.system.edit'))->assertOk();
    }

    public function test_invalid_settings_are_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->put(route('settings.system.update', 'email'), [
            'mailer' => 'invalid',
            'from_address' => 'not-an-email',
            'from_name' => '',
        ])->assertSessionHasErrors(['mailer', 'from_address', 'from_name']);

        $this->actingAs($admin)->put(route('settings.system.update', 'banking'), [
            'bank_accounts' => [],
        ])->assertSessionHasErrors('bank_accounts');

        $this->actingAs($admin)->put(route('settings.system.update', 'mpesa'), [
            'environment' => 'edge',
        ])->assertSessionHasErrors('environment');

        $this->actingAs($admin)->put(route('settings.system.update', 'general'), [
            'company_name' => '',
            'billing_day' => 31,
            'due_days' => 0,
            'late_fee_enabled' => true,
            'late_fee_percentage' => 120,
            'email_notifications' => true,
            'sms_notifications' => false,
        ])->assertSessionHasErrors(['company_name', 'billing_day', 'due_days', 'late_fee_percentage']);
    }

    public function test_banking_mpesa_and_general_groups_can_be_saved(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->put(route('settings.system.update', 'banking'), [
            'bank_accounts' => [[
                'bank_name' => 'Example Bank',
                'account_name' => 'Sychar Suppliers',
                'account_number' => '123456789',
                'branch' => 'Nairobi',
                'swift' => 'EXAMPKE1',
                'currency' => 'KES',
            ]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->put(route('settings.system.update', 'mpesa'), [
            'paybill_number' => '123456',
            'account_number' => 'WATER',
            'till_number' => '',
            'consumer_key' => 'mpesa-key',
            'consumer_secret' => 'mpesa-secret',
            'passkey' => 'mpesa-passkey',
            'environment' => 'sandbox',
            'shortcode' => '123456',
            'initiator_name' => 'billing',
            'security_credential' => '',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->put(route('settings.system.update', 'general'), [
            'company_name' => 'Sychar Suppliers',
            'logo' => '',
            'address' => 'Nairobi, Kenya',
            'phone' => '',
            'email' => 'billing@example.test',
            'pin_vat_number' => 'P000000000A',
            'default_unit_rate' => 50,
            'billing_day' => 1,
            'due_days' => 14,
            'late_fee_enabled' => true,
            'late_fee_percentage' => 5,
            'email_notifications' => true,
            'sms_notifications' => false,
            'feature_flags' => ['auto_bill_generation' => true],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Example Bank', app(SystemSettingsService::class)->get('banking')['bank_accounts'][0]['bank_name']);
        $this->assertSame('mpesa-secret', app(SystemSettingsService::class)->get('mpesa')['consumer_secret']);
        $this->assertSame('Sychar Suppliers', app(SystemSettingsService::class)->get('general')['company_name']);
        $this->assertSame('Example Bank', config('billing.company.bank_name'));
        $this->assertSame('mpesa-secret', config('services.mpesa.consumer_secret'));
        $this->assertSame(50, config('price.unit_price'));
        $this->assertTrue(config('billing.generation.auto_generate'));
    }

    public function test_m_pesa_connection_test_uses_saved_credentials(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        app(SystemSettingsService::class)->update('mpesa', [
            'consumer_key' => 'consumer-key',
            'consumer_secret' => 'consumer-secret',
            'environment' => 'sandbox',
        ]);
        Http::fake(['sandbox.safaricom.co.ke/*' => Http::response(['access_token' => 'token'], 200)]);

        $this->actingAs($admin)->post(route('settings.system.test-mpesa'))
            ->assertSessionHas('success', 'M-Pesa credentials connected successfully.');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/oauth/v1/generate')
            && $request->hasHeader('Authorization'));
    }
}
