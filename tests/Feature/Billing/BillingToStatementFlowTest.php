<?php

namespace Tests\Feature\Billing;

use App\Events\Billing\BillGenerated;
use App\Models\Account;
use App\Models\Billing;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Services\Billing\BillingService;
use App\Services\Billing\StatementGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Test the flow: Billing Generation → Statement
 */
class BillingToStatementFlowTest extends TestCase
{
    use RefreshDatabase;

    protected BillingService $billingService;
    protected StatementGenerator $statementGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->billingService = app(BillingService::class);
        $this->statementGenerator = app(StatementGenerator::class);
    }

    /** @test */
    public function billing_generation_dispatches_bill_generated_event()
    {
        Event::fake();

        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();

        // Create readings
        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 1000,
            'reading_date' => '2024-12-31',
            'reading_type' => 'actual',
        ]);

        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 1100,
            'reading_date' => '2025-01-31',
            'reading_type' => 'actual',
        ]);

        $billing = $this->billingService->generateForAccount($account, '2025-01');

        Event::assertDispatched(BillGenerated::class, function ($event) use ($billing) {
            return $event->billing->id === $billing->id;
        });
    }

    /** @test */
    public function billing_has_required_fields_for_statement_generation()
    {
        $account = Account::factory()->create(['email' => 'test@example.com']);
        $meter = Meter::factory()->for($account)->create();

        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 1000,
            'reading_date' => '2024-12-31',
            'reading_type' => 'actual',
        ]);

        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 1100,
            'reading_date' => '2025-01-31',
            'reading_type' => 'actual',
        ]);

        $billing = $this->billingService->generateForAccount($account, '2025-01');

        // Verify billing has all fields needed for statement
        $this->assertNotNull($billing->billing_period);
        $this->assertNotNull($billing->total_amount);
        $this->assertNotNull($billing->issued_at);
        $this->assertNotNull($billing->due_date);
        $this->assertNotNull($billing->account_id);

        // Verify billing has details
        $this->assertGreaterThan(0, $billing->details()->count());
    }

    /** @test */
    public function billing_details_contain_required_information_for_statement()
    {
        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();

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

        // Verify detail has required fields
        $this->assertNotNull($detail->meter_id);
        $this->assertNotNull($detail->units);
        $this->assertNotNull($detail->amount);
        $this->assertGreaterThanOrEqual(0, $detail->units);
        $this->assertGreaterThanOrEqual(0, $detail->amount);
    }

    /** @test */
    public function billing_total_matches_sum_of_details_for_statement()
    {
        $account = Account::factory()->create();
        
        $meter1 = Meter::factory()->for($account)->create();
        $meter2 = Meter::factory()->for($account)->create();

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
        
        // Total should match sum of details (allowing for small rounding differences)
        $this->assertEqualsWithDelta($billing->total_amount, $sumOfDetails, 0.01);
    }

    /** @test */
    public function billing_can_be_retrieved_for_statement_generation()
    {
        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();

        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 1000,
            'reading_date' => '2024-12-31',
            'reading_type' => 'actual',
        ]);

        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'reading' => 1100,
            'reading_date' => '2025-01-31',
            'reading_type' => 'actual',
        ]);

        $billing = $this->billingService->generateForAccount($account, '2025-01');

        // Verify billing can be loaded with relationships for statement
        $billingForStatement = Billing::with([
            'account',
            'details.meter',
        ])->find($billing->id);

        $this->assertNotNull($billingForStatement);
        $this->assertNotNull($billingForStatement->account);
        $this->assertGreaterThan(0, $billingForStatement->details->count());
        $this->assertNotNull($billingForStatement->details->first()->meter);
    }
}

