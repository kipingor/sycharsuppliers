<?php

namespace Tests\Unit;

use App\Models\SystemSetting;
use App\Services\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SystemSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('system_settings.groups');
    }

    public function test_settings_are_cached_and_invalidated_after_update(): void
    {
        $service = app(SystemSettingsService::class);
        $service->update('general', ['company_name' => 'First Company']);
        $this->assertSame('First Company', $service->get('general')['company_name']);

        SystemSetting::where('group', 'general')->update([
            'data' => Crypt::encryptString(json_encode(['company_name' => 'Direct DB edit'], JSON_THROW_ON_ERROR)),
        ]);
        $this->assertSame('First Company', $service->get('general')['company_name']);

        $service->update('general', ['company_name' => 'Second Company']);
        $this->assertSame('Second Company', $service->get('general')['company_name']);
    }
}
