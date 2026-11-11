<?php

namespace App\Console\Commands;

use App\Models\Billing;
use App\Models\BillingDetail;
use App\Services\Audit\AuditService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ApplyHistoricalBillingRatesCommand extends Command
{
    protected $signature = 'billing:apply-historical-rates
                            {file : CSV export with approved recorded_unit_rate values}
                            {--apply : Write the changes (otherwise preview only)}
                            {--reason= : Required explanation recorded in the audit trail}';

    protected $description = 'Correct historical bill detail rates from an approved CSV while preserving payment records';

    /**
     * @var list<float>
     */
    private const CORRECTABLE_RATES = [200.0, 250.0];

    public function handle(AuditService $auditService): int
    {
        $path = $this->argument('file');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("CSV file does not exist or is not readable: {$path}");

            return self::FAILURE;
        }

        $reason = trim((string) $this->option('reason'));
        if ($this->option('apply') && strlen($reason) < 10) {
            $this->error('Applying corrections requires --reason with at least 10 characters.');

            return self::FAILURE;
        }

        try {
            $rows = $this->readRows($path);
            $plans = $this->buildPlans($rows);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($plans->isEmpty()) {
            $this->warn('No eligible KES 200 or KES 250 rate corrections were found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Bill', 'Period', 'Status', 'Old total', 'New total', 'Old due', 'New due', 'Paid', 'Credit', 'Net credit position', 'Balance change'],
            $plans->map(fn (array $plan) => [
                $plan['billing']->id,
                $plan['billing']->billing_period,
                $plan['billing']->status,
                number_format($plan['old_total'], 2),
                number_format($plan['new_total'], 2),
                number_format($plan['old_due'], 2),
                number_format($plan['new_due'], 2),
                number_format($plan['paid_amount'], 2),
                number_format($plan['credit_amount'], 2),
                number_format($plan['net_credit_position'], 2),
                number_format($plan['new_due'] - $plan['old_due'], 2),
            ])->all()
        );

        $this->info(sprintf(
            'Eligible bills: %d; detail lines to correct: %d.',
            $plans->count(),
            $plans->sum(fn (array $plan) => count($plan['details']))
        ));
        $this->warn('Void and written-off bills, unsupported rates, and zero/negative consumption are excluded. Existing payments and allocations are never rewritten.');
        $this->warn('Open balances are adjusted by the bill-total change, preserving the existing recorded balance. Paid bills remain closed; payment-history discrepancies are not reconciled by this command.');
        $this->warn('Net credit positions are for manual review only. The command does not create refunds or carry-forward credits.');

        if (! $this->option('apply')) {
            $this->comment('Preview only. Add --apply and a reason after reviewing this output.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Apply these historical rate corrections and adjust existing open balances by the bill-total changes?', false)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($plans, $path, $reason, $auditService): void {
                foreach ($plans as $plan) {
                    $billing = Billing::query()
                        ->with('details')
                        ->lockForUpdate()
                        ->findOrFail($plan['billing']->id);

                    if ((float) $billing->total_amount !== $plan['old_total']
                        || $billing->status !== $plan['old_status']) {
                        throw new RuntimeException("Bill #{$billing->id} changed after preview; no corrections were applied.");
                    }

                    $detailChanges = [];
                    foreach ($plan['details'] as $detailPlan) {
                        $detail = $billing->details->firstWhere('id', $detailPlan['detail']->id);
                        if (! $detail
                            || (float) $detail->rate !== $detailPlan['old_rate']
                            || (float) $detail->amount !== $detailPlan['old_amount']
                            || (float) $detail->units_used !== $detailPlan['units']) {
                            throw new RuntimeException("Bill detail #{$detailPlan['detail']->id} changed after preview; no corrections were applied.");
                        }

                        $detail->update([
                            'rate' => $detailPlan['new_rate'],
                            'amount' => $detailPlan['new_amount'],
                        ]);
                        $detailChanges[] = [
                            'billing_detail_id' => $detail->id,
                            'units' => $detailPlan['units'],
                            'old_rate' => $detailPlan['old_rate'],
                            'new_rate' => $detailPlan['new_rate'],
                            'old_amount' => $detailPlan['old_amount'],
                            'new_amount' => $detailPlan['new_amount'],
                        ];
                    }

                    $billing->update([
                        'total_amount' => $plan['new_total'],
                        'amount_due' => $plan['new_due'],
                        'status' => $plan['new_status'],
                        'paid_at' => $plan['paid_at'],
                    ]);

                    $auditService->logBillingAction('historical_rate_corrected', $billing, [
                        'source_file' => basename($path),
                        'reason' => $reason,
                        'details' => $detailChanges,
                        'old_total_amount' => $plan['old_total'],
                        'new_total_amount' => $plan['new_total'],
                        'old_amount_due' => $plan['old_due'],
                        'new_amount_due' => $plan['new_due'],
                    ]);
                }
            });
        } catch (Throwable $exception) {
            Log::error('Historical billing rate correction failed', [
                'source_file' => basename($path),
                'error' => $exception->getMessage(),
            ]);
            $this->error('No corrections were applied: '.$exception->getMessage());

            return self::FAILURE;
        }

        Log::info('Historical billing rates corrected', [
            'source_file' => basename($path),
            'reason' => $reason,
            'billing_count' => $plans->count(),
            'detail_count' => $plans->sum(fn (array $plan) => count($plan['details'])),
        ]);
        $this->info('Historical rates and detail amounts were updated. Unsettled bill balances were adjusted by the bill-total changes; paid bills remain closed, and payment records and allocations were not changed.');

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function readRows(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV file: {$path}");
        }

        try {
            $headers = fgetcsv($handle);
            if ($headers === false) {
                throw new RuntimeException('CSV file is empty.');
            }

            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headers);
            foreach (['billing_id', 'billing_period', 'account_id', 'meter_id', 'units_used', 'recorded_unit_rate'] as $required) {
                if (! in_array($required, $headers, true)) {
                    throw new RuntimeException("CSV is missing required column: {$required}");
                }
            }

            $rows = [];
            $line = 1;
            while (($values = fgetcsv($handle)) !== false) {
                $line++;
                if ($values === [null] || $values === []) {
                    continue;
                }
                if (count($values) !== count($headers)) {
                    throw new RuntimeException("CSV row {$line} has an unexpected number of columns.");
                }

                $row = array_combine($headers, array_map(
                    fn ($value) => $value === null ? null : trim((string) $value),
                    $values
                ));
                if ($row === false) {
                    throw new RuntimeException("Unable to parse CSV row {$line}.");
                }
                $row['_line'] = (string) $line;
                $rows[] = $row;
            }

            if ($rows === []) {
                throw new RuntimeException('CSV contains no billing rows.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function buildPlans(array $rows): Collection
    {
        $correctionsByBilling = [];
        $inputDetailKeys = [];
        foreach ($rows as $row) {
            if (! is_numeric($row['billing_id'] ?? null)
                || ! is_numeric($row['account_id'] ?? null)
                || ! is_numeric($row['meter_id'] ?? null)
                || ! is_numeric($row['units_used'] ?? null)
                || ! preg_match('/^\d{4}-\d{2}$/', (string) ($row['billing_period'] ?? ''))
                || ! is_numeric($row['recorded_unit_rate'] ?? null)) {
                throw new RuntimeException('CSV row '.($row['_line'] ?? '?').' has invalid identifiers, period, units, or rate.');
            }

            $rate = round((float) $row['recorded_unit_rate'], 2);
            if (! in_array($rate, self::CORRECTABLE_RATES, true)) {
                continue;
            }

            $billingId = (int) $row['billing_id'];
            $correctionsByBilling[$billingId][] = [
                'row' => $row,
                'rate' => $rate,
                'units' => (float) $row['units_used'],
            ];
        }

        if ($correctionsByBilling === []) {
            return collect();
        }

        $billings = Billing::query()
            ->with('details')
            ->whereIn('id', array_keys($correctionsByBilling))
            ->get()
            ->keyBy('id');

        $plans = collect();
        foreach ($correctionsByBilling as $billingId => $corrections) {
            $billing = $billings->get($billingId);
            if (! $billing) {
                throw new RuntimeException("CSV references missing bill #{$billingId}.");
            }

            $skippedStatus = in_array($billing->status, ['voided', 'void', 'written_off'], true);
            if ($skippedStatus) {
                $this->line("Skipping bill #{$billing->id}: status is {$billing->status}.");

                continue;
            }

            $detailPlans = [];
            foreach ($corrections as $correction) {
                $row = $correction['row'];
                if ((int) $row['account_id'] !== (int) $billing->account_id
                    || (string) $row['billing_period'] !== (string) $billing->billing_period) {
                    throw new RuntimeException('CSV row '.$row['_line']." does not match bill #{$billing->id}.");
                }

                if ($correction['units'] <= 0) {
                    $this->line("Skipping bill #{$billing->id}, CSV row {$row['_line']}: consumption is zero or negative.");

                    continue;
                }

                $details = $billing->details;
                if (! empty($row['billing_detail_id'])) {
                    $details = $details->where('id', (int) $row['billing_detail_id']);
                } else {
                    $details = $details->filter(fn (BillingDetail $detail) => (int) $detail->meter_id === (int) $row['meter_id']
                        && abs((float) $detail->units_used - $correction['units']) < 0.005
                    );
                }

                if ($details->count() !== 1) {
                    throw new RuntimeException('CSV row '.$row['_line']." does not identify exactly one detail on bill #{$billing->id}.");
                }

                /** @var BillingDetail $detail */
                $detail = $details->first();
                if ((int) $detail->meter_id !== (int) $row['meter_id']
                    || abs((float) $detail->units_used - $correction['units']) >= 0.005) {
                    throw new RuntimeException('CSV row '.$row['_line']." does not match bill detail #{$detail->id}.");
                }

                if (isset($inputDetailKeys[$detail->id])) {
                    throw new RuntimeException("Bill detail #{$detail->id} occurs more than once in the CSV.");
                }
                $inputDetailKeys[$detail->id] = true;

                $detailPlans[] = [
                    'detail' => $detail,
                    'units' => (float) $detail->units_used,
                    'old_rate' => (float) $detail->rate,
                    'new_rate' => $correction['rate'],
                    'old_amount' => (float) $detail->amount,
                    'new_amount' => round((float) $detail->units_used * $correction['rate'], 2),
                ];
            }

            if ($detailPlans === []) {
                continue;
            }

            $amountsByDetail = collect($detailPlans)->keyBy(fn (array $detailPlan) => $detailPlan['detail']->id);
            $newTotal = round($billing->details->sum(function (BillingDetail $detail) use ($amountsByDetail) {
                return $amountsByDetail->has($detail->id)
                    ? $amountsByDetail->get($detail->id)['new_amount']
                    : (float) $detail->amount;
            }), 2);

            $plans->push([
                'billing' => $billing,
                'details' => $detailPlans,
                'old_total' => round((float) $billing->total_amount, 2),
                'old_due' => round((float) $billing->amount_due, 2),
                'old_status' => $billing->status,
                'new_total' => $newTotal,
            ]);
        }

        if ($plans->isEmpty()) {
            return $plans;
        }

        $billingIds = $plans->pluck('billing.id')->all();
        $allocated = DB::table('payment_allocations as pa')
            ->join('payments as p', 'p.id', '=', 'pa.payment_id')
            ->whereIn('pa.billing_id', $billingIds)
            ->where('p.status', 'completed')
            ->whereNull('p.deleted_at')
            ->select('pa.billing_id', DB::raw('SUM(pa.allocated_amount) as total'))
            ->groupBy('pa.billing_id')
            ->pluck('total', 'billing_id');
        $direct = DB::table('payments as p')
            ->whereIn('p.billing_id', $billingIds)
            ->where('p.status', 'completed')
            ->whereNull('p.deleted_at')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('payment_allocations as pa')
                    ->whereColumn('pa.billing_id', 'p.billing_id')
                    ->whereColumn('pa.payment_id', 'p.id');
            })
            ->select('p.billing_id', DB::raw('SUM(p.amount) as total'))
            ->groupBy('p.billing_id')
            ->pluck('total', 'billing_id');
        $credits = DB::table('credit_notes')
            ->whereIn('billing_id', $billingIds)
            ->where('status', 'applied')
            ->select('billing_id', DB::raw('SUM(amount) as total'))
            ->groupBy('billing_id')
            ->pluck('total', 'billing_id');

        return $plans->map(function (array $plan) use ($allocated, $direct, $credits) {
            $billingId = $plan['billing']->id;
            $paidAmount = (float) ($allocated->get($billingId) ?? 0)
                + (float) ($direct->get($billingId) ?? 0);
            $creditAmount = (float) ($credits->get($billingId) ?? 0);
            $balanceAdjustment = $plan['new_total'] - $plan['old_total'];
            $newDue = $plan['old_status'] === 'paid'
                ? $plan['old_due']
                : max(0, round($plan['old_due'] + $balanceAdjustment, 2));
            $plan['paid_amount'] = $paidAmount;
            $plan['credit_amount'] = $creditAmount;
            $plan['new_due'] = $newDue;
            $plan['net_credit_position'] = max(0, round($paidAmount + $creditAmount - $plan['new_total'], 2));
            $plan['new_status'] = $this->resolveStatus(
                $plan['billing'],
                $plan['new_total'],
                $newDue,
                $paidAmount,
                $creditAmount
            );
            $plan['paid_at'] = $plan['new_status'] === 'paid'
                ? ($plan['billing']->paid_at ?? now())
                : null;

            return $plan;
        });
    }

    private function resolveStatus(
        Billing $billing,
        float $total,
        float $due,
        float $paid,
        float $credits
    ): string {
        if ($billing->status === 'paid') {
            return 'paid';
        }

        if ($due <= 0.01) {
            return $credits > 0 && $paid < $total
                ? 'written_off'
                : 'paid';
        }

        if ($paid > 0 || $credits > 0) {
            return $billing->due_date && Carbon::parse($billing->due_date)->isPast()
                ? 'overdue'
                : 'partially_paid';
        }

        return $billing->due_date && Carbon::parse($billing->due_date)->isPast()
            ? 'overdue'
            : 'pending';
    }
}
