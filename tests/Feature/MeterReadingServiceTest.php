<?php

namespace Tests\Feature;

use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\User;
use App\Services\Meter\MeterReadingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Meter Reading Service Test
 *
 * Tests critical business rules:
 * 1. Monotonic reading validation
 * 2. Duplicate prevention
 * 3. Transaction integrity
 * 4. Billing integration
 *
 * @package Tests\Feature
 */
class MeterReadingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MeterReadingService $service;
    protected User $user;
    protected Meter $meter;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = app(MeterReadingService::class);
        
        // Create test user
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        // Create test meter
        $this->meter = Meter::factory()->create();
    }

    /**
     * Test: First reading should be accepted
     */
    public function test_first_reading_is_accepted(): void
    {
        $reading = $this->service->createReading([
            'meter_id' => $this->meter->id,
            'reading' => 100.0,
            'reading_date' => now(),
            'reading_type' => 'actual',
        ]);

        $this->assertDatabaseHas('meter_readings', [
            'meter_id' => $this->meter->id,
            'reading' => 100.0,
        ]);
    }

    /**
     * Test: CRITICAL - Reading cannot go backwards (monotonic constraint)
     */
    public function test_reading_cannot_regress(): void
    {
        // Create first reading
        $this->service->createReading([
            'meter_id' => $this->meter->id,
            'reading' => 1000.0,
            'reading_date' => now()->subMonth(),
            'reading_type' => 'actual',
        ]);

        // Try to create lower reading - SHOULD FAIL
        $this->expectException(\App\Exceptions\Billing\BillingException::class);
        $this->expectExceptionMessage('Reading must be >= previous reading');

        $this->service->createReading([
            'meter_id' => $this->meter->id,
            'reading' => 500.0, // Lower than 1000 - INVALID
            'reading_date' => now(),
            'reading_type' => 'actual',
        ]);
    }

    /**
     * Test: CRITICAL - Same reading as previous should be accepted
     */
    public function test_same_reading_is_accepted(): void
    {
        // Create first reading
        $this->service->createReading([
            'meter_id' => $this->meter->id,
            'reading' => 1000.0,
            'reading_date' => now()->subMonth(),
            'reading_type' => 'actual',
        ]);

        // Same reading value should work (no consumption)
        $reading = $this->service->createReading([
            'meter_id' => $this->meter->id,
            'reading' => 1000.0,
            'reading_date' => now(),
            'reading_type' => 'actual',
        ]);

        $this->assertEquals(1000.0, $reading->reading);
    }

    /**
     * Test: CRITICAL - Higher reading should be accepted
     */
    public function test_higher_reading_is_accepted(): void
    {
        // Create first reading
        $this->service->createReading([
            'meter_id' => $this->meter->id,
            'reading' => 1000.0,
            'reading_date' => now()->subMonth(),
            'reading_type' => 'actual',
        ]);

        // Higher reading should work
        $reading = $this->service->createReading([
            'meter_id' => $this->meter->id,
            'reading' => 1500.0,
            'reading_date' => now(),
            'reading_type' => 'actual',
        ]);

        $this->assertEquals(1500.0, $reading->reading);
        $this->assertEquals(500.0, $reading->getConsumption());
    }

    /**
     * Test: CRITICAL - Duplicate reading for same month prevented
     */
    public function test_duplicate_reading_same_month_prevented(): void
    {
        // Create first reading for February
        $this->service->createReading([
            'meter_id' => $this->meter->id,
            'reading' => 1000.0,
            'reading_date' => '2026-02-05',
            'reading_type' => 'actual',
        ]);

        // Try to create another reading for February - SHOULD FAIL
        $this->expectException(\App\Exceptions\Billing\BillingException::class);
        $this->expectExceptionMessage('Duplicate reading detected');

        $this->service->createReading([
            'meter_id' => $this->meter->id,
            'reading' => 1100.0,
            'reading_date' => '2026-02-20', // Same month
            'reading_type' => 'actual',
        ]);
    }

    /**
     * Test: Different months should be allowed
     */
    public function test_different_months_allowed(): void
    {
        // Create reading for January
        $this->service->createReading([
            'meter_id' => $this->meter->id,
            'reading' => 1000.0,
            'reading_date' => '2026-01-15',
            'reading_type' => 'actual',
        ]);

        // Create reading for February - SHOULD WORK
        $reading = $this->service->createReading([
            'meter_id' => $this->meter->id,
            'reading' => 1100.0,
            'reading_date' => '2026-02-15',
            'reading_type' => 'actual',
        ]);

        $this->assertEquals(1100.0, $reading->reading);
    }

    /**
     * Test: CRITICAL - Transaction rollback on error
     */
    public function test_transaction_rollback_on_error(): void
    {
        $initialCount = MeterReading::count();

        try {
            // This should fail validation
            $this->service->createReading([
                'meter_id' => 999999, // Non-existent meter
                'reading' => 100.0,
                'reading_date' => now(),
                'reading_type' => 'actual',
            ]);
        } catch (\Exception $e) {
            // Expected to fail
        }

        // Verify no reading was created
        $this->assertEquals($initialCount, MeterReading::count());
    }

    /**
     * Test: CRITICAL - Cannot delete reading used in billing
     */
    public function test_cannot_delete_reading_used_in_billing(): void
    {
        $reading = MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
        ]);

        // Create billing detail that uses this reading
        \App\Models\BillingDetail::factory()->create([
            'meter_id' => $this->meter->id,
            'current_reading' => $reading->reading,
        ]);

        // Try to delete - SHOULD FAIL
        $this->expectException(\App\Exceptions\Billing\BillingException::class);
        $this->expectExceptionMessage('Cannot delete reading that has been used in billing');

        $this->service->deleteReading($reading);
    }

    /**
     * Test: Can delete unused reading
     */
    public function test_can_delete_unused_reading(): void
    {
        $reading = MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
        ]);

        $this->service->deleteReading($reading);

        $this->assertDatabaseMissing('meter_readings', [
            'id' => $reading->id,
        ]);
    }

    /**
     * Test: CRITICAL - Cannot update reading used in billing
     */
    public function test_cannot_update_reading_used_in_billing(): void
    {
        $reading = MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
        ]);

        // Create billing detail that uses this reading
        \App\Models\BillingDetail::factory()->create([
            'meter_id' => $this->meter->id,
            'current_reading' => $reading->reading,
        ]);

        // Try to update - SHOULD FAIL
        $this->expectException(\App\Exceptions\Billing\BillingException::class);
        $this->expectExceptionMessage('Cannot update reading that has been used in billing');

        $this->service->updateReading($reading, [
            'reading' => $reading->reading + 100,
        ]);
    }

    /**
     * Test: Bulk reading creation
     */
    public function test_bulk_reading_creation(): void
    {
        $meter1 = Meter::factory()->create();
        $meter2 = Meter::factory()->create();

        $readings = [
            ['meter_id' => $meter1->id, 'reading' => 100.0],
            ['meter_id' => $meter2->id, 'reading' => 200.0],
        ];

        $result = $this->service->createBulkReadings($readings, now());

        $this->assertEquals(2, $result['created']);
        $this->assertEquals(0, $result['failed']);
    }

    /**
     * Test: Bulk creation handles partial failures
     */
    public function test_bulk_creation_handles_failures(): void
    {
        $meter1 = Meter::factory()->create();

        // Create first reading for meter1
        $this->service->createReading([
            'meter_id' => $meter1->id,
            'reading' => 100.0,
            'reading_date' => now(),
        ]);

        // Try bulk create with one duplicate
        $readings = [
            ['meter_id' => $meter1->id, 'reading' => 200.0], // Duplicate month
            ['meter_id' => Meter::factory()->create()->id, 'reading' => 300.0], // Should work
        ];

        $result = $this->service->createBulkReadings($readings, now());

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['failed']);
        $this->assertCount(1, $result['errors']);
    }

    /**
     * Test: CRITICAL - Future reading validates against past
     */
    public function test_future_reading_cannot_be_lower_than_current(): void
    {
        // Create past reading
        $this->service->createReading([
            'meter_id' => $this->meter->id,
            'reading' => 1000.0,
            'reading_date' => now()->subMonth(),
        ]);

        // Create future reading
        $this->service->createReading([
            'meter_id' => $this->meter->id,
            'reading' => 2000.0,
            'reading_date' => now()->addMonth(),
        ]);

        // Try to create current reading higher than future - SHOULD FAIL
        $this->expectException(\App\Exceptions\Billing\BillingException::class);
        $this->expectExceptionMessage('Reading must be <= future reading');

        $this->service->createReading([
            'meter_id' => $this->meter->id,
            'reading' => 2500.0, // Higher than future reading
            'reading_date' => now(),
        ]);
    }

    /**
     * Test: Reading validation rules
     */
    public function test_reading_validation_rules(): void
    {
        // Negative reading should fail
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->service->createReading([
            'meter_id' => $this->meter->id,
            'reading' => -100.0, // INVALID
            'reading_date' => now(),
        ]);
    }

    /**
     * Test: Future date validation
     */
    public function test_future_date_validation(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->service->createReading([
            'meter_id' => $this->meter->id,
            'reading' => 100.0,
            'reading_date' => now()->addYear(), // INVALID - future date
        ]);
    }

    /**
     * Test: Audit logging
     */
    public function test_audit_logging(): void
    {
        $reading = $this->service->createReading([
            'meter_id' => $this->meter->id,
            'reading' => 100.0,
            'reading_date' => now(),
        ]);

        // Verify audit log created
        $this->assertDatabaseHas('audits', [
            'auditable_type' => MeterReading::class,
            'auditable_id' => $reading->id,
            'event' => 'created',
        ]);
    }
}
