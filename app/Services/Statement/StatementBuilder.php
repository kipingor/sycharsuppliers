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
     */
    protected function calculateOpeningBalance(Account $account, Carbon $before): float
    {
        $billed = Billing::where('account_id', $account->id)
            ->where('created_at', '<', $before)
            ->sum('total_amount');

        $paid = Payment::where('account_id', $account->id)
            ->where('created_at', '<', $before)
            ->sum('amount');

        return round($billed - $paid, 2);
    }

    /**
     * Build bill ledger lines.
     */
    protected function billLines(Account $account, Carbon $from, Carbon $to): Collection
    {
        return Billing::with('details.meter')
            ->where('account_id', $account->id)
            ->whereBetween('created_at', [$from, $to])
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
                    'date'        => $billing->created_at,
                    'reference'   => 'BILL-' . $billing->id,
                    'description' => 'Water usage (' . $meterSummary . ')',
                    'debit'       => (float) $billing->total_amount,
                    'credit'      => null,
                ];
            });
    }

    /**
     * Build payment ledger lines.
     */
    protected function paymentLines(Account $account, Carbon $from, Carbon $to): Collection
    {
        return Payment::where('account_id', $account->id)
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->map(function (Payment $payment) {
                return [
                    'date'        => $payment->created_at,
                    'reference'   => 'PAY-' . $payment->id,
                    'description' => 'Payment received',
                    'debit'       => null,
                    'credit'      => (float) $payment->amount,
                ];
            });
    }
}
