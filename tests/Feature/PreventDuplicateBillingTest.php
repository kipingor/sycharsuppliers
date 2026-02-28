<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PreventDuplicateBillingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A test to prevent duplicate billing.
     */
    public function test_duplicate_billing_is_prevented(): void
    {
        $account = $this->createAccountWithMeterReadings();

        app(\App\Services\Billing\BillingOrchestrator::class)
            ->generateMonthlyBill($account->id, '2025-01');

        $this->expectException(\App\Exceptions\Billing\BillingException::class);

        app(\App\Services\Billing\BillingOrchestrator::class)
            ->generateMonthlyBill($account->id, '2025-01');
    }
}
