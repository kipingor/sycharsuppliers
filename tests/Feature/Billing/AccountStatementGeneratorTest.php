<?php

namespace Tests\Feature\Billing;

use App\Models\Account;
use App\Models\Billing;
use App\Models\CreditNote;
use App\Models\Meter;
use App\Services\Billing\AccountStatementGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountStatementGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_statement_uses_credit_note_application_dates_and_counts_opening_balance_once(): void
    {
        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();

        $priorBill = Billing::factory()->create([
            'meter_id' => $meter->id,
            'account_id' => $account->id,
            'billing_period' => '2024-12',
            'total_amount' => 100,
            'amount_due' => 100,
            'status' => 'pending',
            'issued_at' => '2024-12-01 09:00:00',
        ]);
        $periodBill = Billing::factory()->create([
            'meter_id' => $meter->id,
            'account_id' => $account->id,
            'billing_period' => '2025-01',
            'total_amount' => 50,
            'amount_due' => 50,
            'status' => 'pending',
            'issued_at' => '2025-01-05 09:00:00',
        ]);

        $this->createCreditNote($priorBill, 'CN-2024-0001', 10, '2024-12-20 12:00:00');
        $this->createCreditNote($priorBill, 'CN-2025-0001', 20, '2025-01-15 12:00:00');
        $this->createCreditNote($periodBill, 'CN-2025-0002', 30, '2025-02-05 12:00:00');

        $statement = app(AccountStatementGenerator::class)
            ->buildData($account, '2025-01-01', '2025-01-31');

        $this->assertSame(90.0, $statement['opening_balance']);
        $this->assertSame(20.0, $statement['total_credited']);
        $this->assertSame(120.0, $statement['closing_balance']);
        $this->assertSame(
            ['bill', 'credit_note'],
            array_column($statement['transactions'], 'type')
        );
        $this->assertSame([140.0, 120.0], array_column($statement['transactions'], 'running_balance'));
    }

    private function createCreditNote(Billing $billing, string $reference, float $amount, string $createdAt): void
    {
        $creditNote = CreditNote::create([
            'billing_id' => $billing->id,
            'reference' => $reference,
            'type' => 'billing_error',
            'amount' => $amount,
            'reason' => 'Statement calculation test',
            'status' => 'applied',
        ]);

        DB::table('credit_notes')
            ->where('id', $creditNote->id)
            ->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);
    }
}
