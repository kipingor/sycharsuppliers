<?php

namespace Tests\Unit\Billing;

use App\Models\Account;
use App\Models\Billing;
use App\Models\CarryForwardBalance;
use App\Models\Meter;
use App\Models\Payment;
use App\Models\Resident;
use App\Services\Billing\BalanceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_current_balance_breakdown_for_an_account(): void
    {
        $account = Account::factory()->create();
        $resident = Resident::factory()->create([
            'account_number' => $account->account_number,
        ]);
        $meter = Meter::create([
            'account_id' => $account->id,
            'resident_id' => $resident->id,
            'meter_number' => 'MTR-BAL-1',
            'meter_name' => 'Balance Meter',
            'location' => 'Plot 1',
            'type' => 'analog',
            'meter_type' => 'individual',
            'status' => 'active',
        ]);

        Billing::create([
            'meter_id' => $meter->id,
            'account_id' => $account->id,
            'billing_period' => '2025-01',
            'total_amount' => 100,
            'amount_due' => 100,
            'status' => 'pending',
            'issued_at' => now()->subMonth(),
            'due_date' => now()->subDays(5),
        ]);

        Billing::create([
            'meter_id' => $meter->id,
            'account_id' => $account->id,
            'billing_period' => '2025-02',
            'total_amount' => 50,
            'amount_due' => 50,
            'status' => 'pending',
            'issued_at' => now(),
            'due_date' => now()->addDays(10),
        ]);

        Payment::create([
            'account_id' => $account->id,
            'meter_id' => $meter->id,
            'amount' => 40,
            'payment_date' => now(),
            'method' => 'Cash',
            'transaction_id' => 'TXN-100',
            'status' => 'completed',
            'reconciliation_status' => 'pending',
        ]);

        CarryForwardBalance::create([
            'account_id' => $account->id,
            'type' => 'credit',
            'balance' => 10,
            'status' => 'active',
        ]);

        $balance = app(BalanceResolver::class)->getAccountBalance($account, useCache: false);

        $this->assertSame(150.0, $balance['total_billed']);
        $this->assertSame(40.0, $balance['total_paid']);
        $this->assertSame(150.0, $balance['outstanding_balance']);
        $this->assertSame(10.0, $balance['carry_forward_credits']);
        $this->assertSame(140.0, $balance['net_balance']);
        $this->assertSame(100.0, $balance['overdue_amount']);
        $this->assertSame(2, $balance['outstanding_bill_count']);
    }
}
