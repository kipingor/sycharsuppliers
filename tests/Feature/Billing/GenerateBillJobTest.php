<?php

namespace Tests\Feature\Billing;

use App\Jobs\GenerateBillJob;
use App\Models\Account;
use App\Models\Meter;
use App\Models\MeterReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateBillJobTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function job_generates_billing_for_account()
    {
        $account = Account::factory()->create();

        $meter = Meter::factory()->for($account)->create();

        MeterReading::factory()->create([
            'meter_id' => $meter->id,
            'consumption' => 15,
        ]);

        GenerateBillJob::dispatchSync(
            $account->id,
            '2025-01'
        );

        $this->assertDatabaseCount('billings', 1);
        $this->assertDatabaseHas('billings', [
            'account_id' => $account->id,
        ]);
    }
}
