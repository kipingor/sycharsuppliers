<?php

namespace Tests\Feature\Billing;

use App\Models\Account;
use App\Models\Billing;
use App\Models\BillingDetail;
use App\Models\Meter;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Tariff;
use App\Models\TariffRate;
use App\Services\Statement\StatementBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApplyHistoricalBillingRatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_previews_then_applies_historical_rates_and_refreshes_statement_totals(): void
    {
        [$account, $billing, $detail] = $this->billFixture();
        $secondMeter = Meter::factory()->for($account)->create();
        $secondDetail = BillingDetail::create([
            'billing_id' => $billing->id,
            'meter_id' => $secondMeter->id,
            'previous_reading_value' => 20,
            'current_reading_value' => 21,
            'units_used' => 1,
            'rate' => 300,
            'amount' => 300,
            'description' => 'Water consumption',
        ]);
        $billing->update([
            'total_amount' => 900,
            'amount_due' => 900,
        ]);
        $payment = Payment::create([
            'account_id' => $account->id,
            'meter_id' => $detail->meter_id,
            'amount' => 100,
            'payment_date' => '2024-06-05',
            'method' => 'Cash',
            'transaction_id' => 'RATE-'.Str::uuid(),
            'status' => 'completed',
            'reconciliation_status' => 'reconciled',
        ]);
        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'billing_id' => $billing->id,
            'allocated_amount' => 100,
            'allocation_date' => '2024-06-05',
        ]);
        $tariff = Tariff::create([
            'name' => 'Historical snapshot test',
            'code' => 'RATE-TEST-'.Str::uuid(),
            'type' => 'flat',
            'meter_type' => 'individual',
            'status' => 'active',
            'is_default' => false,
            'effective_from' => '2024-01-01',
            'description' => 'Tariff used to verify historical rate snapshots.',
        ]);
        $tariffRate = TariffRate::create([
            'tariff_id' => $tariff->id,
            'tier_number' => 1,
            'min_units' => 0,
            'max_units' => null,
            'rate_per_unit' => 300,
            'fixed_charge' => 0,
        ]);
        $path = $this->csvFile([[
            $detail->id,
            $billing->id,
            $billing->billing_period,
            $account->id,
            $account->account_number,
            $detail->meter_id,
            'METER',
            2,
            200,
            400,
        ], [
            $secondDetail->id,
            $billing->id,
            $billing->billing_period,
            $account->id,
            $account->account_number,
            $secondDetail->meter_id,
            'METER-2',
            1,
            250,
            250,
        ]]);

        try {
            $this->artisan('billing:apply-historical-rates', ['file' => $path])
                ->expectsOutputToContain('Preview only')
                ->assertExitCode(0);

            $this->assertSame(300.0, (float) $detail->fresh()->rate);
            $this->assertSame(900.0, (float) $billing->fresh()->total_amount);

            $this->artisan('billing:apply-historical-rates', [
                'file' => $path,
                '--apply' => true,
                '--reason' => 'Approved historical rate correction.',
            ])
                ->expectsConfirmation('Apply these historical rate corrections and adjust existing open balances by the bill-total changes?', 'yes')
                ->assertExitCode(0);

            $detail->refresh();
            $billing->refresh();

            $this->assertSame(200.0, (float) $detail->rate);
            $this->assertSame(400.0, (float) $detail->amount);
            $this->assertSame(250.0, (float) $secondDetail->fresh()->rate);
            $this->assertSame(250.0, (float) $secondDetail->fresh()->amount);
            $this->assertSame(650.0, (float) $billing->total_amount);
            $this->assertSame(650.0, (float) $billing->amount_due);
            $this->assertSame('partially_paid', $billing->status);
            $this->assertSame(100.0, (float) $payment->allocations()->sum('allocated_amount'));
            $this->assertDatabaseHas('audits', [
                'auditable_type' => Billing::class,
                'auditable_id' => $billing->id,
                'event' => 'historical_rate_corrected',
            ]);

            $tariffRate->update(['rate_per_unit' => 250]);
            $this->assertSame(200.0, (float) $detail->fresh()->rate);
            $this->assertSame(650.0, (float) $billing->fresh()->total_amount);

            $statement = app(StatementBuilder::class)->build(
                $account,
                Carbon::parse('2024-06-01'),
                Carbon::parse('2024-06-30')
            );
            $this->assertSame(650.0, $statement['totals']['total_billed']);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_requires_a_reason_and_does_not_modify_voided_or_zero_consumption_bills(): void
    {
        [, $billing, $detail] = $this->billFixture([
            'status' => 'voided',
        ], 0);
        $path = $this->csvFile([[
            $detail->id,
            $billing->id,
            $billing->billing_period,
            $billing->account_id,
            'ACC',
            $detail->meter_id,
            'METER',
            0,
            200,
            0,
        ]]);

        try {
            $this->artisan('billing:apply-historical-rates', [
                'file' => $path,
                '--apply' => true,
            ])->assertExitCode(1);

            $this->assertSame(300.0, (float) $detail->fresh()->rate);
            $this->assertSame(0.0, (float) $billing->fresh()->total_amount);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_keeps_paid_bills_closed_when_historical_payment_allocations_are_missing(): void
    {
        [, $billing, $detail] = $this->billFixture([
            'status' => 'paid',
            'amount_due' => 0,
            'paid_at' => '2024-06-15 12:00:00',
        ]);
        $paidAt = $billing->paid_at;
        $path = $this->csvFile([[
            $detail->id,
            $billing->id,
            $billing->billing_period,
            $billing->account_id,
            'ACC',
            $detail->meter_id,
            'METER',
            2,
            200,
            400,
        ]]);

        try {
            $this->artisan('billing:apply-historical-rates', [
                'file' => $path,
                '--apply' => true,
                '--reason' => 'Approved historical rate correction.',
            ])
                ->expectsConfirmation('Apply these historical rate corrections and adjust existing open balances by the bill-total changes?', 'yes')
                ->assertExitCode(0);

            $this->assertSame('paid', $billing->fresh()->status);
            $this->assertSame(0.0, (float) $billing->fresh()->amount_due);
            $this->assertSame($paidAt?->toDateTimeString(), $billing->fresh()->paid_at?->toDateTimeString());
            $this->assertSame(400.0, (float) $billing->fresh()->total_amount);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @return array{Account, Billing, BillingDetail}
     */
    private function billFixture(array $billingAttributes = [], float $units = 2): array
    {
        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();
        $billing = Billing::factory()->create(array_merge([
            'account_id' => $account->id,
            'meter_id' => $meter->id,
            'billing_period' => '2024-06',
            'issued_at' => '2024-06-01',
            'due_date' => now()->addMonth(),
            'status' => 'pending',
            'total_amount' => $units * 300,
            'amount_due' => $units * 300,
        ], $billingAttributes));
        $detail = BillingDetail::create([
            'billing_id' => $billing->id,
            'meter_id' => $meter->id,
            'previous_reading_value' => 10,
            'current_reading_value' => 10 + $units,
            'units_used' => $units,
            'rate' => 300,
            'amount' => $units * 300,
            'description' => 'Water consumption',
        ]);

        return [$account, $billing, $detail];
    }

    /**
     * @param  list<array<int, int|float|string>>  $rows
     */
    private function csvFile(array $rows): string
    {
        $path = tempnam(storage_path('app'), 'historical-rates-');
        $handle = fopen($path, 'w');
        fputcsv($handle, [
            'billing_detail_id',
            'billing_id',
            'billing_period',
            'account_id',
            'account_number',
            'meter_id',
            'meter_number',
            'units_used',
            'recorded_unit_rate',
            'recorded_line_amount',
        ]);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }
}
