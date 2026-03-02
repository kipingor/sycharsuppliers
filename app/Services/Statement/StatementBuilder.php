<?php

namespace App\Services\Statement;

use App\Models\Account;
use App\Models\Billing;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StatementBuilder
{
    public function build(Account $account, Carbon $from, Carbon $to): array
    {
        // Normalize dates
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        // 1. Opening balance (everything before period)
        $openingBalance = $this->calculateOpeningBalance($account, $from);

        // 2. Ledger lines within the period
        $lines = collect();

        // Bills
        $billLines = $this->billLines($account, $from, $to);
        $lines = $lines->merge($billLines);

        // Payments
        $paymentLines = $this->paymentLines($account, $from, $to);
        $lines = $lines->merge($paymentLines);

        // 3. Sort chronologically
        $lines = $lines->sortBy('date')->values();

        // 4. Running balance
        $runningBalance = $openingBalance;

        $lines = $lines->map(function (array $line) use (&$runningBalance) {
            $runningBalance += ($line['debit'] ?? 0) - ($line['credit'] ?? 0);
            $line['balance'] = round($runningBalance, 2);
            return $line;
        });

        // 5. Totals
        $totalBilled = $billLines->sum('debit');
        $totalPaid   = $paymentLines->sum('credit');

        return [
            'account' => [
                'id'    => $account->id,
                'name'  => $account->name,
                'email' => $account->email,
                'number' => $account->account_number,
            ],

            'period' => [
                'from' => $from,
                'to'   => $to,
            ],

            'opening_balance' => round($openingBalance, 2),

            'lines' => $lines->all(),

            'totals' => [
                'total_billed'   => round($totalBilled, 2),
                'total_paid'     => round($totalPaid, 2),
                'closing_balance' => round($runningBalance, 2),
            ],
        ];
    }

    /**
     * Calculate balance before the statement period.
     *
     * Uses issued_at for bills (not created_at — bills can be bulk-created
     * in migrations at a time unrelated to when they were issued).
     * Uses payment_date for payments (not created_at).
     * Excludes voided bills and soft-deleted payments.
     * Accounts for applied credit notes.
     * Returns a signed value: negative = account is in credit (overpaid).
     */
    protected function calculateOpeningBalance(Account $account, Carbon $before): float
    {
        $billed = Billing::where('account_id', $account->id)
            ->where('issued_at', '<', $before)
            ->whereNotIn('status', ['voided'])
            ->sum('total_amount');

        $paid = Payment::where('account_id', $account->id)
            ->where('payment_date', '<', $before)
            ->whereNull('deleted_at')
            ->sum('amount');

        // Applied credit notes on bills issued before the period
        $credited = Billing::with('creditNotes')
            ->where('account_id', $account->id)
            ->where('issued_at', '<', $before)
            ->whereNotIn('status', ['voided'])
            ->get()
            ->sum(fn ($b) => $b->creditNotes->where('status', 'applied')->sum('amount'));

        return round($billed - $paid - $credited, 2);
    }

    /**
     * Build bill ledger lines.
     *
     * Filters by issued_at so bills land in the period they were actually
     * issued to the customer. Excludes voided bills.
     */
    protected function billLines(Account $account, Carbon $from, Carbon $to): Collection
    {
        return Billing::with('details.meter')
            ->where('account_id', $account->id)
            ->whereBetween('issued_at', [$from, $to])
            ->whereNotIn('status', ['voided'])
            ->get()
            ->map(function (Billing $billing) {

                $meterSummary = $billing->details->map(function ($detail) {
                    return sprintf(
                        'Meter %s: %s units',
                        $detail->meter->serial_number ?? $detail->meter_id,
                        number_format($detail->units, 2)
                    );
                })->implode('; ');

                return [
                    'date'        => $billing->issued_at,
                    'reference'   => 'BILL-' . $billing->id,
                    'description' => 'Water usage (' . $meterSummary . ')',
                    'debit'       => (float) $billing->total_amount,
                    'credit'      => null,
                ];
            });
    }

    /**
     * Build payment ledger lines.
     *
     * Filters by payment_date (not created_at) and excludes soft-deleted
     * payments so the ledger reflects when money was actually received.
     */
    protected function paymentLines(Account $account, Carbon $from, Carbon $to): Collection
    {
        return Payment::where('account_id', $account->id)
            ->whereBetween('payment_date', [$from, $to])
            ->whereNull('deleted_at')
            ->get()
            ->map(function (Payment $payment) {
                return [
                    'date'        => $payment->payment_date,
                    'reference'   => 'PAY-' . $payment->id,
                    'description' => 'Payment received',
                    'debit'       => null,
                    'credit'      => (float) $payment->amount,
                ];
            });
    }
}