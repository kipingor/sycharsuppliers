<?php

namespace Tests\Feature\Billing;

use App\Models\Account;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Services\Billing\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Test billing domain integrity rules:
 * - Sequential meter readings
 * - No negative consumption
 * - Correct billing unit calculations
 */
class BillingDomainIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function meter_reading_validation_prevents_negative_consumption()
    {
        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();

        // Create initial reading
        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 1000,
            'reading_date' => '2025-01-01',
            'reading_type' => 'actual',
        ]);

        // Attempt to create reading lower than previous (should fail validation)
        $this->expectException(ValidationException::class);

        MeterReading::factory()->make([
            'meter_id' => $meter->id,
            'reading' => 950, // Lower than previous
            'reading_date' => '2025-01-31',
            'reading_type' => 'actual',
        ])->validate();
    }

    /** @test */
    public function meter_reading_getConsumption_returns_zero_not_negative()
    {
        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();

        // Create readings that would result in negative if not protected
        $previous = MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 1000,
            'reading_date' => '2025-01-15',
            'reading_type' => 'actual',
        ]);

        // Create reading that's actually lower (shouldn't happen, but test safety)
        $current = MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 950,
            'reading_date' => '2025-01-31',
            'reading_type' => 'actual',
        ]);

        // getConsumption() should return 0, not -50
        $consumption = $current->getConsumption();
        $this->assertGreaterThanOrEqual(0, $consumption);
    }

    /** @test */
    public function billing_service_ensures_non_negative_consumption()
    {
        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();

        // Create sequential readings
        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 500,
            'reading_date' => '2024-12-31',
            'reading_type' => 'actual',
        ]);

        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 750,
            'reading_date' => '2025-01-31',
            'reading_type' => 'actual',
        ]);

        $billingService = app(BillingService::class);
        $billing = $billingService->generateForAccount($account, '2025-01');

        $detail = $billing->details()->first();
        $this->assertGreaterThanOrEqual(0, $detail->units);
        $this->assertEquals(250, $detail->units); // 750 - 500
    }

    /** @test */
    public function sequential_readings_are_enforced_by_validation()
    {
        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();

        // Create initial reading
        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 100,
            'reading_date' => '2025-01-01',
            'reading_type' => 'actual',
        ]);

        // Attempt to create reading with lower value on later date
        $request = new \App\Http\Requests\StoreMeterReadingRequest([
            'meter_id' => $meter->id,
            'reading' => 50, // Lower than previous
            'reading_date' => '2025-01-31',
            'reading_type' => 'actual',
        ]);

        // Set the request's route resolver
        $request->setRouteResolver(function () {
            return new class {
                public function parameter($key, $default = null) {
                    return $default;
                }
            };
        });

        // This should fail validation
        $this->expectException(ValidationException::class);
        $request->validate();
    }

    /** @test */
    public function consumption_calculation_uses_correct_reading_sequence()
    {
        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();

        // Create multiple readings
        $reading1 = MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 100,
            'reading_date' => '2024-12-15',
            'reading_type' => 'actual',
        ]);

        $reading2 = MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 200,
            'reading_date' => '2024-12-31',
            'reading_type' => 'actual',
        ]);

        $reading3 = MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 350,
            'reading_date' => '2025-01-31',
            'reading_type' => 'actual',
        ]);

        // Consumption for reading3 should be relative to reading2 (latest before)
        $consumption = $reading3->getConsumption();
        $this->assertEquals(150, $consumption); // 350 - 200, not 350 - 100
    }

    /** @test */
    public function billing_uses_latest_reading_for_period()
    {
        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();

        // Multiple readings in the same month
        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 100,
            'reading_date' => '2025-01-05',
            'reading_type' => 'actual',
        ]);

        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 150,
            'reading_date' => '2025-01-15',
            'reading_type' => 'actual',
        ]);

        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 250,
            'reading_date' => '2025-01-31',
            'reading_type' => 'actual',
        ]);

        // Previous reading from before the period
        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 50,
            'reading_date' => '2024-12-31',
            'reading_type' => 'actual',
        ]);

        $billingService = app(BillingService::class);
        $billing = $billingService->generateForAccount($account, '2025-01');

        // Should use the latest reading (250) minus the previous period reading (50)
        $detail = $billing->details()->first();
        $this->assertEquals(200, $detail->units); // 250 - 50
    }

    /** @test */
    public function billing_units_match_consumption_calculation()
    {
        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();

        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 500,
            'reading_date' => '2024-12-31',
            'reading_type' => 'actual',
        ]);

        $currentReading = MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 750,
            'reading_date' => '2025-01-31',
            'reading_type' => 'actual',
        ]);

        $billingService = app(BillingService::class);
        $billing = $billingService->generateForAccount($account, '2025-01');

        $detail = $billing->details()->first();
        $consumption = $currentReading->getConsumption();

        // Billing detail units should match calculated consumption
        $this->assertEquals($consumption, $detail->units);
        $this->assertEquals(250, $detail->units);
    }
}

