<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Billing;
use App\Models\BillingDetail;
use App\Models\Meter;
use App\Models\Resident;
use App\Services\Billing\RebillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RebillingAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_rebilling_with_adjustments_voids_the_original_bill_and_creates_a_replacement(): void
    {
        $this->markTestIncomplete(
            'Rebilling still conflicts with the unique billings(account_id, billing_period) constraint. ' .
            'Either the service or the schema needs to change before this scenario can be tested honestly.'
        );
    }
}
