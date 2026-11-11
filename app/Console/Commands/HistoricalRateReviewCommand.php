<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HistoricalRateReviewCommand extends Command
{
    protected $signature = 'billing:historical-rate-review
                            {--output= : CSV filename to create under storage/app/reports}';

    protected $description = 'Export historical consumption billing for review without changing accounting records';

    /**
     * @var list<float>
     */
    private const CANDIDATE_RATES = [200, 250, 300];

    public function handle(): int
    {
        $filename = $this->option('output')
            ?: 'historical-rate-review-'.now()->format('Ymd-His').'.csv';

        if (basename($filename) !== $filename || ! str_ends_with(strtolower($filename), '.csv')) {
            $this->error('Output must be a .csv filename, not a path.');

            return self::FAILURE;
        }

        $directory = storage_path('app/reports');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error("Unable to create report directory: {$directory}");

            return self::FAILURE;
        }

        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        $handle = fopen($path, 'x');

        if ($handle === false) {
            $this->error("Unable to create report file (it may already exist): {$path}");

            return self::FAILURE;
        }

        $headers = [
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
        ];

        fputcsv($handle, $headers);

        $allocations = DB::table('payment_allocations as pa')
            ->join('payments as p', 'p.id', '=', 'pa.payment_id')
            ->where('p.status', 'completed')
            ->whereNull('p.deleted_at')
            ->select('pa.billing_id', DB::raw('SUM(pa.allocated_amount) as total_allocated'))
            ->groupBy('pa.billing_id');
        $directPayments = DB::table('payments as p')
            ->whereNotNull('p.billing_id')
            ->where('p.status', 'completed')
            ->whereNull('p.deleted_at')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('payment_allocations as pa')
                    ->whereColumn('pa.billing_id', 'p.billing_id')
                    ->whereColumn('pa.payment_id', 'p.id');
            })
            ->select('p.billing_id', DB::raw('SUM(p.amount) as total_direct'))
            ->groupBy('p.billing_id');
        $credits = DB::table('credit_notes')
            ->select('billing_id', DB::raw('SUM(amount) as total_credits'))
            ->where('status', 'applied')
            ->groupBy('billing_id');

        $rows = DB::table('billings as b')
            ->leftJoin('billing_details as bd', 'bd.billing_id', '=', 'b.id')
            ->leftJoin('accounts as a', 'a.id', '=', 'b.account_id')
            ->leftJoin('meters as m', 'm.id', '=', 'bd.meter_id')
            ->leftJoinSub($allocations, 'pa', 'pa.billing_id', '=', 'b.id')
            ->leftJoinSub($directPayments, 'dp', 'dp.billing_id', '=', 'b.id')
            ->leftJoinSub($credits, 'cn', 'cn.billing_id', '=', 'b.id')
            ->select([
                'bd.id as detail_id',
                'bd.billing_id',
                'b.billing_period',
                'b.status as billing_status',
                'b.account_id',
                'a.account_number',
                'bd.meter_id',
                'm.meter_number',
                'bd.units_used',
                'bd.rate',
                'bd.amount as line_amount',
                'b.total_amount',
                'b.amount_due',
                'pa.total_allocated',
                'dp.total_direct',
                'cn.total_credits',
            ])
            ->orderBy('b.billing_period')
            ->orderBy('b.id')
            ->orderBy('bd.id')
            ->cursor();

        foreach ($rows as $row) {
            $units = is_numeric($row->units_used) ? (float) $row->units_used : null;
            $rate = is_numeric($row->rate) ? (float) $row->rate : null;
            $lineAmount = is_numeric($row->line_amount) ? (float) $row->line_amount : null;
            $billTotal = is_numeric($row->total_amount) ? (float) $row->total_amount : null;
            $allocatedAmount = (float) ($row->total_allocated ?? 0);
            $directPaymentAmount = (float) ($row->total_direct ?? 0);
            $paymentAmount = $allocatedAmount + $directPaymentAmount;
            $creditAmount = (float) ($row->total_credits ?? 0);
            $calculatedBalance = max(0, round(($billTotal ?? 0) - $paymentAmount - $creditAmount, 2));
            $calculatedLineAmount = $units !== null && $rate !== null
                ? round($units * $rate, 2)
                : null;
            $isVoided = in_array($row->billing_status, ['voided', 'void'], true);
            $reviewStatus = $isVoided
                ? 'VOIDED - exclude from adjustments'
                : ($row->detail_id === null
                    ? 'MISSING_BILLING_DETAIL - investigate'
                    : ($units === null || $rate === null
                    ? 'MISSING_RATE_OR_UNITS - investigate'
                    : 'UNVERIFIED_RATE_PERIOD - evidence and approval required'));

            $candidateDeltas = [];
            foreach (self::CANDIDATE_RATES as $candidateRate) {
                $candidateDeltas[] = $isVoided || $units === null || $rate === null
                    ? null
                    : round(($candidateRate - $rate) * $units, 2);
            }

            $values = [
                $row->detail_id,
                $row->billing_id,
                $row->billing_period,
                $row->billing_status,
                $row->account_id,
                $this->spreadsheetSafe($row->account_number),
                $row->meter_id,
                $this->spreadsheetSafe($row->meter_number),
                $row->units_used,
                $row->rate,
                $row->line_amount,
                $row->total_amount,
                $row->amount_due,
                $allocatedAmount,
                $directPaymentAmount,
                $paymentAmount,
                $creditAmount,
                $calculatedBalance,
                $calculatedLineAmount,
                $lineAmount !== null && $calculatedLineAmount !== null
                    ? round($lineAmount - $calculatedLineAmount, 2)
                    : null,
                $billTotal !== null && $calculatedLineAmount !== null
                    ? round($billTotal - $calculatedLineAmount, 2)
                    : null,
                ...$candidateDeltas,
                $reviewStatus,
            ];

            if (fputcsv($handle, $values) === false) {
                fclose($handle);
                $this->error("Unable to write report file: {$path}");

                return self::FAILURE;
            }
        }

        fclose($handle);

        $this->info("Historical rate review exported to {$path}");
        $this->warn('Candidate differences are usage-only estimates. They do not include taxes or fixed charges and must not be posted without verified dates and approval.');

        return self::SUCCESS;
    }

    private function spreadsheetSafe(?string $value): ?string
    {
        if ($value !== null && preg_match('/^[\t\r ]*[=+\-@]/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
