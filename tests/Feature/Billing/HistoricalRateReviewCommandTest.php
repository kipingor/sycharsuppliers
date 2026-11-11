<?php

namespace Tests\Feature\Billing;

use App\Models\Account;
use App\Models\Billing;
use App\Models\BillingDetail;
use App\Models\CreditNote;
use App\Models\Meter;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HistoricalRateReviewCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_candidate_usage_differences_without_changing_billing_or_reconciliation_records(): void
    {
        $account = Account::factory()->create();
        $meter = Meter::factory()->for($account)->create();
        $billing = Billing::factory()->create([
            'account_id' => $account->id,
            'billing_period' => '2020-03',
            'status' => 'partially_paid',
            'total_amount' => 1500,
            'amount_due' => 900,
        ]);
        Billing::factory()->create([
            'account_id' => $account->id,
            'billing_period' => '2020-04',
            'total_amount' => 500,
            'amount_due' => 500,
        ]);
        $detail = BillingDetail::create([
            'billing_id' => $billing->id,
            'meter_id' => $meter->id,
            'previous_reading_value' => 10,
            'current_reading_value' => 13,
            'units_used' => 3,
            'rate' => 300,
            'amount' => 900,
            'description' => 'Consumption',
        ]);
        $payment = Payment::create([
            'account_id' => $account->id,
            'meter_id' => $meter->id,
            'billing_id' => $billing->id,
            'amount' => 600,
            'payment_date' => '2020-04-01',
            'method' => 'Cash',
            'transaction_id' => 'REVIEW-'.Str::uuid(),
            'status' => 'completed',
            'reconciliation_status' => 'reconciled',
        ]);
        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'billing_id' => $billing->id,
            'allocated_amount' => 600,
            'allocation_date' => '2020-04-01',
        ]);
        Payment::create([
            'account_id' => $account->id,
            'meter_id' => $meter->id,
            'billing_id' => $billing->id,
            'amount' => 75,
            'payment_date' => '2020-04-02',
            'method' => 'Cash',
            'transaction_id' => 'REVIEW-'.Str::uuid(),
            'status' => 'completed',
            'reconciliation_status' => 'pending',
        ]);
        Payment::create([
            'account_id' => $account->id,
            'meter_id' => $meter->id,
            'billing_id' => $billing->id,
            'amount' => 1000,
            'payment_date' => '2020-04-03',
            'method' => 'Cash',
            'transaction_id' => 'REVIEW-'.Str::uuid(),
            'status' => 'failed',
            'reconciliation_status' => 'pending',
        ]);
        CreditNote::create([
            'billing_id' => $billing->id,
            'reference' => 'CN-REVIEW-'.Str::uuid(),
            'type' => 'billing_error',
            'amount' => 100,
            'reason' => 'Existing approved billing credit.',
            'status' => 'applied',
        ]);

        $filename = 'historical-rate-review-'.Str::uuid().'.csv';
        $path = storage_path('app/reports/'.$filename);

        try {
            $this->artisan('billing:historical-rate-review', ['--output' => $filename])
                ->assertExitCode(0);

            $rows = array_map('str_getcsv', file($path));
            $this->assertCount(3, $rows);
            $this->assertSame(
                [
                    'billing_detail_id',
                    'billing_id',
                    'billing_period',
                    'billing_status',
                    'account_id',
                    'account_number',
                    'meter_id',
                    'meter_number',
                    'units_used',
                    'recorded_unit_rate',
                    'recorded_line_amount',
                    'total_bill_amount',
                    'amount_due',
                    'completed_allocated_payment_amount',
                    'completed_direct_payment_amount',
                    'completed_payment_amount',
                    'applied_credit_note_amount',
                    'calculated_balance_after_payments_and_credits',
                    'rate_calculated_line_amount',
                    'line_amount_difference',
                    'bill_total_difference',
                    'usage_only_delta_at_200',
                    'usage_only_delta_at_250',
                    'usage_only_delta_at_300',
                    'review_status',
                ],
                $rows[0]
            );
            $billRow = array_combine($rows[0], $rows[1]);
            $missingDetailRow = array_combine($rows[0], $rows[2]);

            $this->assertSame((string) $billing->id, $billRow['billing_id']);
            $this->assertSame('-300', $billRow['usage_only_delta_at_200']);
            $this->assertSame('-150', $billRow['usage_only_delta_at_250']);
            $this->assertSame('0', $billRow['usage_only_delta_at_300']);
            $this->assertSame(600.0, (float) $billRow['completed_allocated_payment_amount']);
            $this->assertSame(75.0, (float) $billRow['completed_direct_payment_amount']);
            $this->assertSame(675.0, (float) $billRow['completed_payment_amount']);
            $this->assertSame(100.0, (float) $billRow['applied_credit_note_amount']);
            $this->assertSame(725.0, (float) $billRow['calculated_balance_after_payments_and_credits']);
            $this->assertStringContainsString('UNVERIFIED_RATE_PERIOD', $billRow['review_status']);
            $this->assertStringContainsString('MISSING_BILLING_DETAIL', $missingDetailRow['review_status']);

            $this->assertSame(300.0, (float) $detail->fresh()->rate);
            $this->assertSame(1500.0, (float) $billing->fresh()->total_amount);
            $this->assertSame(900.0, (float) $billing->fresh()->amount_due);
            $this->assertSame(600.0, (float) $payment->allocations()->sum('allocated_amount'));
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_it_rejects_an_output_path_outside_the_reports_directory(): void
    {
        $this->artisan('billing:historical-rate-review', ['--output' => '../outside.csv'])
            ->assertExitCode(1);
    }
}
