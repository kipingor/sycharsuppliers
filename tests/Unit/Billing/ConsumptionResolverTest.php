<?php

namespace Tests\Unit\Billing;

use App\Models\Account;
use App\Models\Employee;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Resident;
use App\Services\Billing\ConsumptionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumptionResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_period_consumption_from_sequential_readings(): void
    {
        $account = Account::factory()->create();
        $resident = Resident::factory()->create([
            'account_number' => $account->account_number,
        ]);
        $meter = Meter::create([
            'account_id' => $account->id,
            'resident_id' => $resident->id,
            'meter_number' => 'MTR-1001',
            'meter_name' => 'Main Meter',
            'location' => 'Block A',
            'type' => 'analog',
            'meter_type' => 'individual',
            'status' => 'active',
        ]);
        $reader = Employee::factory()->create();

        MeterReading::create([
            'meter_id' => $meter->id,
            'reading_date' => '2024-12-31',
            'reading_value' => 80,
            'reader_id' => $reader->id,
            'reading_type' => 'actual',
            'consumption' => 0,
        ]);

        MeterReading::create([
            'meter_id' => $meter->id,
            'reading_date' => '2025-01-31',
            'reading_value' => 100,
            'reader_id' => $reader->id,
            'reading_type' => 'actual',
            'consumption' => 20,
        ]);

        $units = app(ConsumptionResolver::class)->resolveForPeriod($account->id, '2025-01');

        $this->assertSame(20.0, $units);
    }
}
