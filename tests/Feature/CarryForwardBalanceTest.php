<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CarryForwardBalanceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test that unpaid balance from previous month is carried forward to the new bill.
     */
    public function test_unpaid_balance_is_carried_forward(): void
    {
        $account = $this->accountWithUnpaidBill(2024);

        app(\App\Services\Billing\BillingOrchestrator::class)
            ->generateMonthlyBill($account->id, '2025-01');

        $this->assertDatabaseHas('bills', [
            'billing_period' => '2025-01',
            'opening_balance' => 50.00,
        ]);
    }
}
