<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PartialPaymentTest extends TestCase
{
    use RefreshDatabase, WithFaker;
    /**
     * A partial payment on a bill should not close the bill.
     */
    public function test_partial_payment_does_not_close_bill(): void
    {
        $account = $this->billedAccountWithAmount(100.00);

        app(\App\Services\Billing\ApplyPaymentService::class)
            ->apply($account->id, 40.00, 'PAY-001');

        $this->assertDatabaseHas('bills', [
            'account_id' => $account->id,
            'status' => 'PARTIAL',
        ]);
    }
}
