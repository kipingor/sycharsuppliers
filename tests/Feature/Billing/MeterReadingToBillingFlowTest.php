<?php

namespace Tests\Feature\Billing;

use App\Models\Account;
use App\Models\Billing;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Services\Billing\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test the complete flow: Meter Reading → Billing Generation
 */
class MeterReadingToBillingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected BillingService $billingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->billingService = app(BillingService::class);
    }

    /** @test */
    public function it_creates_billing_from_sequential_meter_readings()
    {
        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();

        // Create initial reading
        $initialReading = MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 1000,
            'reading_date' => '2025-01-01',
            'reading_type' => 'actual',
        ]);

        // Create current reading
        $currentReading = MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 1050,
            'reading_date' => '2025-01-31',
            'reading_type' => 'actual',
        ]);

        // Generate billing
        $billing = $this->billingService->generateForAccount($account, '2025-01');

        $this->assertNotNull($billing);
        $this->assertEquals($account->id, $billing->account_id);
        $this->assertEquals('2025-01', $billing->billing_period);

        // Verify billing detail has correct consumption
        $detail = $billing->details()->first();
        $this->assertNotNull($detail);
        $this->assertEquals(50, $detail->units); // 1050 - 1000 = 50
    }

    /** @test */
    public function it_calculates_consumption_correctly_from_sequential_readings()
    {
        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();

        // Sequential readings
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

        $billing = $this->billingService->generateForAccount($account, '2025-01');

        $detail = $billing->details()->first();
        $this->assertEquals(250, $detail->units); // 750 - 500 = 250
    }

    /** @test */
    public function it_handles_multiple_meters_in_billing_generation()
    {
        $account = Account::factory()->create();
        
        $meter1 = Meter::factory()->for($account)->create();
        $meter2 = Meter::factory()->for($account)->create();

        // Meter 1 readings
        MeterReading::factory()->create([
            'meter_id' => $meter1->id,
            'reading' => 100,
            'reading_date' => '2024-12-31',
            'reading_type' => 'actual',
        ]);
        MeterReading::factory()->create([
            'meter_id' => $meter1->id,
            'reading' => 150,
            'reading_date' => '2025-01-31',
            'reading_type' => 'actual',
        ]);

        // Meter 2 readings
        MeterReading::factory()->create([
            'meter_id' => $meter2->id,
            'reading' => 200,
            'reading_date' => '2024-12-31',
            'reading_type' => 'actual',
        ]);
        MeterReading::factory()->create([
            'meter_id' => $meter2->id,
            'reading' => 280,
            'reading_date' => '2025-01-31',
            'reading_type' => 'actual',
        ]);

        $billing = $this->billingService->generateForAccount($account, '2025-01');

        $this->assertEquals(2, $billing->details()->count());
        
        $details = $billing->details;
        $units1 = $details->where('meter_id', $meter1->id)->first()->units;
        $units2 = $details->where('meter_id', $meter2->id)->first()->units;
        
        $this->assertEquals(50, $units1);  // 150 - 100
        $this->assertEquals(80, $units2);  // 280 - 200
    }

    /** @test */
    public function it_ensures_consumption_is_never_negative()
    {
        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();

        // Create readings that would result in negative consumption if not protected
        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 1000,
            'reading_date' => '2025-01-15',
            'reading_type' => 'actual',
        ]);

        // Even if a lower reading exists (shouldn't happen in practice due to validation)
        // the system should protect against negative consumption
        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 950,
            'reading_date' => '2025-01-31',
            'reading_type' => 'actual',
        ]);

        // The billing generation should handle this gracefully
        // (In practice, this would be prevented by validation, but we test the safety mechanism)
        $billing = $this->billingService->generateForAccount($account, '2025-01');
        
        // Consumption should be 0, not negative
        $detail = $billing->details()->first();
        $this->assertGreaterThanOrEqual(0, $detail->units);
    }

    /** @test */
    public function it_handles_first_billing_with_no_previous_reading()
    {
        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();

        // Only current reading, no previous
        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 1000,
            'reading_date' => '2025-01-31',
            'reading_type' => 'actual',
        ]);

        $billing = $this->billingService->generateForAccount($account, '2025-01');

        $detail = $billing->details()->first();
        // First billing should have 0 consumption (uses current reading as starting point)
        $this->assertEquals(0, $detail->units);
    }

    /** @test */
    public function billing_total_matches_sum_of_details()
    {
        $account = Account::factory()->create();
        
        $meter1 = Meter::factory()->for($account)->create();
        $meter2 = Meter::factory()->for($account)->create();

        // Create readings for both meters
        foreach ([$meter1, $meter2] as $meter) {
            MeterReading::factory()->create([
                'meter_id' => $meter->id,
                'reading' => 100,
                'reading_date' => '2024-12-31',
                'reading_type' => 'actual',
            ]);
            MeterReading::factory()->create([
                'meter_id' => $meter->id,
                'reading' => 200,
                'reading_date' => '2025-01-31',
                'reading_type' => 'actual',
            ]);
        }

        $billing = $this->billingService->generateForAccount($account, '2025-01');

        $sumOfDetails = $billing->details()->sum('amount');
        $this->assertEquals($billing->total_amount, $sumOfDetails);
    }
}

