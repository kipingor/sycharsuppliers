<?php

namespace Tests\Feature\Billing;

use App\Events\Billing\CarryForwardCreated;
use App\Events\Billing\PaymentReconciled;
use App\Models\Account;
use App\Models\Billing;
use App\Models\CarryForwardBalance;
use App\Models\Meter;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ReconcilePaymentsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reconciles_an_overpayment_and_records_the_carry_forward_credit(): void
    {
        Event::fake([PaymentReconciled::class, CarryForwardCreated::class]);
        [$payment, $billing] = $this->paymentAndBill(150, 100);

        $this->artisan('reconciliation:process', ['--payment' => $payment->id])
            ->expectsConfirmation('Do you want to proceed with reconciliation?', 'yes')
            ->expectsOutputToContain('Fully Reconciled: 1')
            ->assertExitCode(0);

        $payment->refresh();
        $billing->refresh();
        $carryForward = CarryForwardBalance::where('payment_id', $payment->id)->firstOrFail();

        $this->assertSame('reconciled', $payment->reconciliation_status);
        $this->assertSame('paid', $billing->status);
        $this->assertSame(50.0, (float) $carryForward->balance);
        $this->assertSame('credit', $carryForward->type);
        $this->assertSame('active', $carryForward->status);
    }

    public function test_it_reconciles_a_payment_even_when_the_bill_remains_partially_paid(): void
    {
        Event::fake([PaymentReconciled::class, CarryForwardCreated::class]);
        [$payment, $billing] = $this->paymentAndBill(50, 100);

        $this->artisan('reconciliation:process', ['--payment' => $payment->id])
            ->expectsConfirmation('Do you want to proceed with reconciliation?', 'yes')
            ->expectsOutputToContain('Fully Reconciled: 1')
            ->assertExitCode(0);

        $this->assertSame('reconciled', $payment->fresh()->reconciliation_status);
        $this->assertSame('partially_paid', $billing->fresh()->status);
    }

    /**
     * @return array{Payment, Billing}
     */
    private function paymentAndBill(float $paymentAmount, float $billAmount): array
    {
        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();
        $billing = Billing::factory()->create([
            'account_id' => $account->id,
            'meter_id' => $meter->id,
            'billing_period' => '2024-06',
            'status' => 'pending',
            'total_amount' => $billAmount,
            'amount_due' => $billAmount,
        ]);
        $payment = Payment::create([
            'account_id' => $account->id,
            'meter_id' => $meter->id,
            'amount' => $paymentAmount,
            'payment_date' => '2024-06-15',
            'method' => 'Cash',
            'transaction_id' => uniqid('reconcile-', true),
            'status' => 'completed',
            'reconciliation_status' => 'pending',
        ]);

        return [$payment, $billing];
    }
}
