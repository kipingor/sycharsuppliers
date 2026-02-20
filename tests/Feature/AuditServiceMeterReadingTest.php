<?php

namespace Tests\Feature;

use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Meter\MeterReadingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Test Suite for AuditService with MeterReading Integration
 * 
 * Tests audit logging for meter reading operations including
 * creation, updates, deletions, validations, and bulk operations.
 */
class AuditServiceMeterReadingTest extends TestCase
{
    use RefreshDatabase;

    protected AuditService $auditService;
    protected MeterReadingService $meterReadingService;
    protected User $user;
    protected Meter $meter;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable auditing
        Config::set('audit.enabled', true);

        // Create services
        $this->auditService = app(AuditService::class);
        $this->meterReadingService = app(MeterReadingService::class);

        // Create test user
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Create test meter
        $this->meter = Meter::factory()->create([
            'meter_number' => 'TEST-001',
            'type' => 'water',
        ]);
    }

    /** @test */
    public function it_logs_meter_reading_creation()
    {
        $reading = $this->meterReadingService->createReading([
            'meter_id' => $this->meter->id,
            'reading_value' => 1000,
            'reading_date' => Carbon::now(),
            'reading_type' => 'actual',
        ]);

        $audit = Audit::where('auditable_type', MeterReading::class)
            ->where('auditable_id', $reading->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->user->id, $audit->user_id);
        
        $newValues = json_decode($audit->new_values, true);
        $this->assertEquals($this->meter->id, $newValues['meter_id']);
        $this->assertEquals(1000, $newValues['reading_value']);
        $this->assertEquals('actual', $newValues['reading_type']);
        $this->assertEquals('meter_reading', $newValues['action_type']);
    }

    /** @test */
    public function it_logs_meter_reading_update()
    {
        $reading = MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
            'reading_value' => 1000,
            'reading_date' => Carbon::now(),
        ]);

        $this->meterReadingService->updateReading($reading, [
            'notes' => 'Updated reading notes',
        ]);

        $audit = Audit::where('auditable_type', MeterReading::class)
            ->where('auditable_id', $reading->id)
            ->where('event', 'updated')
            ->latest()
            ->first();

        $this->assertNotNull($audit);
        
        $newValues = json_decode($audit->new_values, true);
        $this->assertArrayHasKey('old_values', $newValues);
        $this->assertArrayHasKey('new_values', $newValues);
        $this->assertEquals('Updated reading notes', $newValues['new_values']['notes']);
    }

    /** @test */
    public function it_logs_meter_reading_deletion()
    {
        $reading = MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
            'reading_value' => 1000,
            'reading_date' => Carbon::now(),
        ]);

        $readingId = $reading->id;
        $this->meterReadingService->deleteReading($reading);

        $audit = Audit::where('auditable_type', MeterReading::class)
            ->where('auditable_id', $readingId)
            ->where('event', 'deleted')
            ->first();

        $this->assertNotNull($audit);
        
        $newValues = json_decode($audit->new_values, true);
        $this->assertArrayHasKey('deleted_reading_data', $newValues);
        $this->assertEquals(1000, $newValues['deleted_reading_data']['reading_value']);
    }

    /** @test */
    public function it_logs_monotonic_violation()
    {
        // Create previous reading
        MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
            'reading_value' => 1000,
            'reading_date' => Carbon::now()->subMonth(),
        ]);

        // Attempt to create reading that violates monotonic constraint
        try {
            $this->meterReadingService->createReading([
                'meter_id' => $this->meter->id,
                'reading_value' => 500, // Less than previous
                'reading_date' => Carbon::now(),
                'reading_type' => 'actual',
            ]);
        } catch (\Exception $e) {
            // Expected to fail
        }

        $audit = Audit::where('auditable_type', MeterReading::class)
            ->where('event', 'monotonic_violation')
            ->first();

        $this->assertNotNull($audit);
        
        $newValues = json_decode($audit->new_values, true);
        $this->assertEquals(1000, $newValues['previous_reading_value']);
        $this->assertEquals(500, $newValues['attempted_reading_value']);
        $this->assertEquals(500, $newValues['violation_amount']); // 1000 - 500
    }

    /** @test */
    public function it_logs_duplicate_prevention()
    {
        // Create existing reading for current month
        MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
            'reading_value' => 1000,
            'reading_date' => Carbon::now()->startOfMonth(),
        ]);

        // Attempt to create another reading in same month
        try {
            $this->meterReadingService->createReading([
                'meter_id' => $this->meter->id,
                'reading_value' => 1100,
                'reading_date' => Carbon::now()->endOfMonth(),
                'reading_type' => 'actual',
            ]);
        } catch (\Exception $e) {
            // Expected to fail
        }

        $audit = Audit::where('auditable_type', MeterReading::class)
            ->where('event', 'duplicate_prevented')
            ->first();

        $this->assertNotNull($audit);
        
        $newValues = json_decode($audit->new_values, true);
        $this->assertArrayHasKey('existing_reading_id', $newValues);
        $this->assertArrayHasKey('existing_reading_date', $newValues);
    }

    /** @test */
    public function it_logs_update_prevention_for_billed_reading()
    {
        $reading = MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
            'reading_value' => 1000,
            'reading_date' => Carbon::now()->subMonth(),
        ]);

        // Simulate reading being used in billing
        DB::table('billing_details')->insert([
            'billing_id' => 1,
            'meter_id' => $this->meter->id,
            'reading_date' => $reading->reading_date,
            'previous_reading_value' => 0,
            'current_reading_value' => 1000,
            'consumption' => 1000,
            'rate' => 300,
            'amount' => 1500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->meterReadingService->updateReading($reading, [
                'reading_value' => 1100,
            ]);
        } catch (\Exception $e) {
            // Expected to fail
        }

        $audit = Audit::where('auditable_type', MeterReading::class)
            ->where('auditable_id', $reading->id)
            ->where('event', 'update_prevented')
            ->first();

        $this->assertNotNull($audit);
        
        $newValues = json_decode($audit->new_values, true);
        $this->assertEquals('reading_already_billed', $newValues['reason']);
        $this->assertArrayHasKey('attempted_changes', $newValues);
    }

    /** @test */
    public function it_logs_delete_prevention_for_billed_reading()
    {
        $reading = MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
            'reading_value' => 1000,
            'reading_date' => Carbon::now()->subMonth(),
        ]);

        // Simulate reading being used in billing
        DB::table('billing_details')->insert([
            'billing_id' => 1,
            'meter_id' => $this->meter->id,
            'reading_date' => $reading->reading_date,
            'previous_reading_value' => 0,
            'current_reading_value' => 1000,
            'consumption' => 1000,
            'rate' => 300,
            'amount' => 1500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->meterReadingService->deleteReading($reading);
        } catch (\Exception $e) {
            // Expected to fail
        }

        $audit = Audit::where('auditable_type', MeterReading::class)
            ->where('auditable_id', $reading->id)
            ->where('event', 'delete_prevented')
            ->first();

        $this->assertNotNull($audit);
        
        $newValues = json_decode($audit->new_values, true);
        $this->assertEquals('reading_already_billed', $newValues['reason']);
    }

    /** @test */
    public function it_logs_bulk_meter_reading_creation()
    {
        $readingsData = [
            [
                'meter_id' => $this->meter->id,
                'reading_value' => 1000,
                'reading_date' => Carbon::now()->subMonths(2),
                'reading_type' => 'actual',
            ],
            [
                'meter_id' => $this->meter->id,
                'reading_value' => 1100,
                'reading_date' => Carbon::now()->subMonth(),
                'reading_type' => 'actual',
            ],
            [
                'meter_id' => $this->meter->id,
                'reading_value' => 1200,
                'reading_date' => Carbon::now(),
                'reading_type' => 'actual',
            ],
        ];

        $result = $this->meterReadingService->createBulkReadings($readingsData, [
            'upload_id' => 'TEST-UPLOAD-001',
            'file_name' => 'readings.csv',
        ]);

        // Check bulk operation summary audit
        $bulkAudit = Audit::where('event', 'bulk_reading_created')
            ->where('auditable_type', 'System')
            ->first();

        $this->assertNotNull($bulkAudit);
        
        $newValues = json_decode($bulkAudit->new_values, true);
        $this->assertEquals(3, $newValues['total_readings']);
        $this->assertEquals(3, $newValues['total_attempted']);
        $this->assertEquals(3, $newValues['total_created']);
        $this->assertEquals(0, $newValues['total_failed']);
        $this->assertEquals('TEST-UPLOAD-001', $newValues['upload_id']);

        // Check individual reading audits
        $individualAudits = Audit::where('auditable_type', MeterReading::class)
            ->where('event', 'created')
            ->get();

        $this->assertCount(3, $individualAudits);
    }

    /** @test */
    public function it_logs_validation_failures_in_bulk_operations()
    {
        // Create a reading first
        MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
            'reading_value' => 1000,
            'reading_date' => Carbon::now()->subMonth(),
        ]);

        $readingsData = [
            [
                'meter_id' => $this->meter->id,
                'reading_value' => 1100,
                'reading_date' => Carbon::now(),
                'reading_type' => 'actual',
            ],
            [
                'meter_id' => $this->meter->id,
                'reading_value' => 500, // Violates monotonic constraint
                'reading_date' => Carbon::now()->addMonth(),
                'reading_type' => 'actual',
            ],
        ];

        $result = $this->meterReadingService->createBulkReadings($readingsData);

        // Check that one succeeded and one failed
        $this->assertCount(1, $result['created']);
        $this->assertCount(1, $result['failed']);

        // Check validation failure audit
        $failureAudit = Audit::where('event', 'validation_failed')
            ->first();

        $this->assertNotNull($failureAudit);
        
        $newValues = json_decode($failureAudit->new_values, true);
        $this->assertArrayHasKey('error', $newValues);
        $this->assertTrue($newValues['bulk_operation']);
    }

    /** @test */
    public function it_retrieves_meter_audit_trail_including_readings()
    {
        // Create readings
        $reading1 = MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
            'reading_value' => 1000,
            'reading_date' => Carbon::now()->subMonth(),
        ]);

        $reading2 = MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
            'reading_value' => 1100,
            'reading_date' => Carbon::now(),
        ]);

        // Log some actions
        $this->auditService->logMeterAction('activated', $this->meter);
        $this->auditService->logMeterReadingAction('created', $reading1);
        $this->auditService->logMeterReadingAction('created', $reading2);

        // Get meter audit trail (should include meter + readings)
        $trail = $this->auditService->getMeterAuditTrail($this->meter);

        $this->assertGreaterThanOrEqual(3, $trail->count());
        
        // Verify different entity types are included
        $entityTypes = $trail->pluck('auditable_type')->unique();
        $this->assertTrue($entityTypes->contains(Meter::class));
        $this->assertTrue($entityTypes->contains(MeterReading::class));
    }

    /** @test */
    public function it_retrieves_account_audit_trail_including_meter_readings()
    {
        $accountId = $this->meter->account_id;

        // Create readings
        $reading = MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
            'reading_value' => 1000,
            'reading_date' => Carbon::now(),
        ]);

        $this->auditService->logMeterReadingAction('created', $reading);

        // Get account audit trail
        $trail = $this->auditService->getAccountAuditTrail($accountId);

        $this->assertGreaterThan(0, $trail->count());
        
        // Should include meter reading audit
        $readingAudits = $trail->where('auditable_type', MeterReading::class);
        $this->assertGreaterThan(0, $readingAudits->count());
    }

    /** @test */
    public function it_calculates_meter_reading_statistics()
    {
        // Create multiple readings
        for ($i = 0; $i < 5; $i++) {
            $reading = MeterReading::factory()->create([
                'meter_id' => $this->meter->id,
                'reading_value' => 1000 + ($i * 100),
                'reading_date' => Carbon::now()->subMonths(5 - $i),
                'reading_type' => $i % 2 == 0 ? 'actual' : 'estimated',
            ]);

            $this->auditService->logMeterReadingAction('created', $reading);
        }

        $stats = $this->auditService->getMeterReadingStatistics(
            from: Carbon::now()->subMonths(6),
            to: Carbon::now(),
            meterId: $this->meter->id
        );

        $this->assertEquals(5, $stats['total_reading_actions']);
        $this->assertArrayHasKey('actions_by_event', $stats);
        $this->assertArrayHasKey('readings_by_type', $stats);
        $this->assertEquals(1, $stats['unique_meters_affected']);
    }

    /** @test */
    public function it_retrieves_validation_failures()
    {
        // Create a reading
        MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
            'reading_value' => 1000,
            'reading_date' => Carbon::now()->subMonth(),
        ]);

        // Attempt monotonic violation
        try {
            $this->meterReadingService->createReading([
                'meter_id' => $this->meter->id,
                'reading_value' => 500,
                'reading_date' => Carbon::now(),
                'reading_type' => 'actual',
            ]);
        } catch (\Exception $e) {}

        // Attempt duplicate
        try {
            MeterReading::factory()->create([
                'meter_id' => $this->meter->id,
                'reading_value' => 1100,
                'reading_date' => Carbon::now()->subMonth()->addDay(),
            ]);
        } catch (\Exception $e) {}

        $failures = $this->auditService->getValidationFailures(
            from: Carbon::now()->subDay(),
            to: Carbon::now()->addDay()
        );

        $this->assertGreaterThan(0, $failures->count());
        
        $events = $failures->pluck('event')->unique();
        $this->assertTrue($events->contains('monotonic_violation'));
    }

    /** @test */
    public function it_handles_audit_logging_when_disabled()
    {
        Config::set('audit.enabled', false);

        $reading = $this->meterReadingService->createReading([
            'meter_id' => $this->meter->id,
            'reading_value' => 1000,
            'reading_date' => Carbon::now(),
            'reading_type' => 'actual',
        ]);

        // No audit should be created
        $audit = Audit::where('auditable_type', MeterReading::class)
            ->where('auditable_id', $reading->id)
            ->first();

        $this->assertNull($audit);
    }

    /** @test */
    public function it_includes_consumption_in_audit_context()
    {
        // Create previous reading
        MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
            'reading_value' => 1000,
            'reading_date' => Carbon::now()->subMonth(),
        ]);

        // Create new reading
        $reading = $this->meterReadingService->createReading([
            'meter_id' => $this->meter->id,
            'reading_value' => 1250,
            'reading_date' => Carbon::now(),
            'reading_type' => 'actual',
        ]);

        $audit = Audit::where('auditable_type', MeterReading::class)
            ->where('auditable_id', $reading->id)
            ->where('event', 'created')
            ->first();

        $newValues = json_decode($audit->new_values, true);
        $this->assertEquals(1000, $newValues['previous_reading_value']);
        $this->assertEquals(250, $newValues['consumption_calculated']);
        $this->assertTrue($newValues['validation_passed']);
    }
}