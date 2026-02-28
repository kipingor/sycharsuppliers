<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\Billing\BillingOrchestrator;
use App\Models\Account;
use App\Models\Meter;
use App\Models\MeterReading;
use Carbon\Carbon;

class GenerateMonthlyBillTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create test account with meter and readings for a billing period.
     */
    protected function createAccountWithMeterReadings()
    {
        // Create account
        $account = Account::create([
            'account_number' => 'TEST-' . uniqid(),
            'name' => 'Test Account',
            'status' => 'active',
        ]);

        // Create a resident (required for meter)
        $resident = \App\Models\Resident::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '0712345678',
            'account_number' => 'RES-' . uniqid(),
            'status' => true,
        ]);

        // Create meter
        $meter = Meter::create([
            'resident_id' => $resident->id,
            'meter_number' => 'MTR-' . uniqid(),
            'meter_name' => 'Main Meter',
            'account_id' => $account->id,
            'location' => 'Test Location',
            'status' => 'active',
        ]);

        // Create meter readings for January 2025
        MeterReading::create([
            'meter_id' => $meter->id,
            'reading_date' => Carbon::parse('2024-12-31'),
            'reading_value' => 1000,
            'reading_type' => 'manual',
        ]);

        MeterReading::create([
            'meter_id' => $meter->id,
            'reading_date' => Carbon::parse('2025-01-31'),
            'reading_value' => 1150,  // 150 units consumed
            'reading_type' => 'manual',
        ]);

        return $account;
    }

    /**
     * Test: It generates a bill for a valid period.
     */
    public function test_it_generates_a_bill_for_a_valid_period(): void
    {
        // Arrange
        $account = $this->createAccountWithMeterReadings();

        // Act
        $billing = app(BillingOrchestrator::class)->generateMonthlyBill($account->id, '2025-01');

        // Assert
        $this->assertDatabaseHas('billings', [
            'account_id' => $account->id,
            'billing_period' => '2025-01',
            'status' => 'pending',
        ]);

        $this->assertNotNull($billing);
        $this->assertEquals('2025-01', $billing->billing_period);
        $this->assertTrue($billing->total_amount > 0);
    }

    /**
     * Test: Idempotent billing - generating twice should return same bill.
     */
    public function test_idempotent_billing_no_duplicate_bills(): void
    {
        // Arrange
        $account = $this->createAccountWithMeterReadings();

        // Act - Generate bill first time
        $billing1 = app(BillingOrchestrator::class)->generateMonthlyBill($account->id, '2025-01');

        // Generate bill second time
        $billing2 = app(BillingOrchestrator::class)->generateMonthlyBill($account->id, '2025-01');

        // Assert - Should be the same bill
        $this->assertEquals($billing1->id, $billing2->id);
        
        // Only one bill should exist for this period
        $billsCount = \App\Models\Billing::where('account_id', $account->id)
            ->where('billing_period', '2025-01')
            ->count();
        $this->assertEquals(1, $billsCount);
    }
}

