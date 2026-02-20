<?php

namespace Tests\Feature\Billing;

use App\Models\Account;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Services\Billing\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateAccountBillingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_generates_one_billing_per_account()
    {
        $account = Account::factory()->create();

        $meters = Meter::factory()
            ->count(2)
            ->for($account)
            ->create();

        foreach ($meters as $meter) {
            MeterReading::factory()->create([
                'meter_id' => $meter->id,
                'consumption' => 10,
            ]);
        }

        $billing = app(BillingService::class)
            ->generateForAccount($account, '2025-01');

        $this->assertDatabaseCount('billings', 1);
        $this->assertEquals($account->id, $billing->account_id);
    }

    /** @test */
    public function it_aggregates_multiple_meters_into_billing_details()
    {
        $account = Account::factory()->create();

        $meters = Meter::factory()
            ->count(3)
            ->for($account)
            ->create();

        foreach ($meters as $meter) {
            MeterReading::factory()->create([
                'meter_id' => $meter->id,
                'consumption' => 5,
            ]);
        }

        $billing = app(BillingService::class)
            ->generateForAccount($account, '2025-01');

        $this->assertEquals(
            3,
            $billing->details()->count()
        );
    }

    /** @test */
    public function billing_total_equals_sum_of_billing_details()
    {
        $account = Account::factory()->create();

        $meter = Meter::factory()->for($account)->create();

        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'consumption' => 10,
        ]);

        $billing = app(BillingService::class)
            ->generateForAccount($account, '2025-01');

        $sum = $billing->details()->sum('amount');

        $this->assertEquals(
            $sum,
            $billing->total_amount
        );
    }
}
