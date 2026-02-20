<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RebillingAdjustmentTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_rebilling_creates_adjustment_bill(): void
    {
        $account = $this->billedAccountWithError();

        app(\App\Services\Billing\RebillingService::class)
            ->rebill($account->id, '2025-01');

        $this->assertDatabaseHas('bills', [
            'type' => 'ADJUSTMENT',
        ]);
    }
}
