<?php

namespace Tests\Feature\Billing;

use App\Models\Account;
use App\Models\Billing;
use App\Models\CreditNote;
use App\Models\Meter;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\Billing\AccountConflictReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditNoteWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_write_off_creates_auditable_credit_note_and_marks_bill_written_off(): void
    {
        $user = $this->admin();
        $bill = $this->bill([
            'billing_period' => '2024-06',
            'total_amount' => 1200,
            'amount_due' => 1200,
        ]);

        $this->actingAs($user)->post(route('credit-notes.apply-bulk'), [
            'billing_ids' => [$bill->id],
            'reason' => 'Approved bad debt write-off reference BR-24-06.',
        ])->assertRedirect(route('credit-notes.apply'));

        $bill->refresh();
        $creditNote = CreditNote::where('billing_id', $bill->id)->firstOrFail();

        $this->assertSame('written_off', $bill->status);
        $this->assertSame(0.0, (float) $bill->amount_due);
        $this->assertNull($bill->paid_at);
        $this->assertSame('bad_debt_writeoff', $creditNote->type);
        $this->assertSame(1200.0, (float) $creditNote->amount);
        $this->assertDatabaseHas('audits', [
            'auditable_type' => CreditNote::class,
            'auditable_id' => $creditNote->id,
            'event' => 'created',
        ]);
    }

    public function test_bulk_write_off_rejects_a_bill_with_conflicting_amount_due(): void
    {
        $user = $this->admin();
        $bill = $this->bill([
            'billing_period' => '2024-06',
            'total_amount' => 1000,
            'amount_due' => 1000,
        ]);
        $payment = Payment::create([
            'account_id' => $bill->account_id,
            'meter_id' => $bill->meter_id,
            'amount' => 200,
            'payment_date' => '2024-06-15',
            'method' => 'Cash',
            'status' => 'completed',
            'reconciliation_status' => 'reconciled',
        ]);
        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'billing_id' => $bill->id,
            'allocated_amount' => 200,
            'allocation_date' => now(),
        ]);

        $this->actingAs($user)->from(route('credit-notes.apply'))
            ->post(route('credit-notes.apply-bulk'), [
                'billing_ids' => [$bill->id],
                'reason' => 'Approved bad debt write-off reference BR-24-06.',
            ])
            ->assertRedirect(route('credit-notes.apply'))
            ->assertSessionHasErrors('billing_ids');

        $this->assertDatabaseCount('credit_notes', 0);
        $this->assertSame('pending', $bill->fresh()->status);
        $this->assertSame(1000.0, (float) $bill->fresh()->amount_due);
    }

    public function test_voiding_a_credit_note_restores_the_bill_open_balance_and_status(): void
    {
        $user = $this->admin();
        $bill = $this->bill([
            'billing_period' => now()->format('Y-m'),
            'total_amount' => 500,
            'amount_due' => 500,
            'due_date' => now()->addDays(10),
        ]);

        $this->actingAs($user)->post(route('billings.credit-notes.store', $bill), [
            'type' => 'other',
            'amount' => 500,
            'reason' => 'Approved full bill adjustment.',
        ])->assertRedirect(route('billings.show', $bill));

        $creditNote = CreditNote::where('billing_id', $bill->id)->firstOrFail();
        $this->assertSame('written_off', $bill->fresh()->status);
        $this->assertSame(0.0, (float) $bill->fresh()->amount_due);

        $this->actingAs($user)->post(route('credit-notes.void', $creditNote), [
            'void_reason' => 'Correction: this credit note was applied in error.',
        ])->assertRedirect();

        $this->assertSame('voided', $creditNote->fresh()->status);
        $this->assertSame('pending', $bill->fresh()->status);
        $this->assertSame(500.0, (float) $bill->fresh()->amount_due);
    }

    public function test_conflict_report_flags_paid_bills_with_due_and_unreconciled_completed_payments(): void
    {
        $account = Account::factory()->create();
        $bill = $this->bill([
            'account_id' => $account->id,
            'billing_period' => '2024-06',
            'status' => 'paid',
            'total_amount' => 1000,
            'amount_due' => 1000,
        ]);
        Payment::create([
            'account_id' => $account->id,
            'meter_id' => $bill->meter_id,
            'amount' => 200,
            'payment_date' => '2024-06-15',
            'method' => 'Cash',
            'status' => 'completed',
            'reconciliation_status' => 'pending',
        ]);

        $report = app(AccountConflictReport::class)->build();
        $reportedAccount = collect($report['accounts'])->firstWhere('account_id', $account->id);

        $this->assertNotNull($reportedAccount);
        $codes = collect($reportedAccount['issues'])->pluck('code');
        $this->assertContains('paid_bill_has_due', $codes);
        $this->assertContains('unreconciled_completed_payment', $codes);
        $this->assertContains('ledger_vs_open_bills', $codes);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('admin');

        return $user;
    }

    private function bill(array $attributes = []): Billing
    {
        $account = isset($attributes['account_id'])
            ? Account::findOrFail($attributes['account_id'])
            : Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();

        return Billing::factory()->create(array_merge([
            'account_id' => $account->id,
            'meter_id' => $meter->id,
            'billing_period' => '2024-06',
            'issued_at' => '2024-06-01',
            'due_date' => '2024-06-15',
            'status' => 'pending',
            'total_amount' => 1000,
            'amount_due' => 1000,
        ], $attributes));
    }
}
