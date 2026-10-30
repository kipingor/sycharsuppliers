<?php

namespace App\Services\Billing;

use App\Models\Account;
use App\Models\Billing;
use App\Models\CreditNote;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

class AccountStatementGenerator
{
    public function buildData(Account $account, string $startDate, string $endDate): array
    {
        $periodStart = Carbon::parse($startDate)->startOfDay();
        $periodEnd = Carbon::parse($endDate)->endOfDay();

        // ── 1. Billings in period ─────────────────────────────────────────
        $billings = Billing::where('account_id', $account->id)
            ->whereBetween('issued_at', [$periodStart, $periodEnd])
            ->whereNotIn('status', ['voided'])
            ->orderBy('issued_at')
            ->get();

        // ── 2. All payments for this account in period ────────────────────
        // Payments are stored at account level (billing_id is always null).
        // They may later be allocated to bills via payment_allocations, but
        // we show them by their actual payment_date regardless.
        $payments = Payment::where('account_id', $account->id)
            ->whereBetween('payment_date', [$periodStart, $periodEnd])
            ->whereNull('deleted_at')
            ->orderBy('payment_date')
            ->get();

        // ── 3. Credit notes in period ─────────────────────────────────────
        $creditNotesQuery = CreditNote::query()
            ->where('status', 'applied')
            ->whereHas('billing', fn ($query) => $query
                ->where('account_id', $account->id)
                ->whereNotIn('status', ['voided']));
        $creditNotes = (clone $creditNotesQuery)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->orderBy('created_at')
            ->get();

        // ── 4. Opening balance ────────────────────────────────────────────
        // Must be calculated BEFORE the running-balance loop so the ledger
        // starts from the correct carried-forward position.
        //
        // Formula: prior billed (non-voided) - prior paid - prior applied credits
        // NOTE: result is intentionally allowed to be negative — a negative
        // opening balance means the account was in credit before this period
        // (i.e. they had overpaid). Clamping to 0 with max() would silently
        // discard that credit and was the previous bug.
        $priorBilled = (float) Billing::where('account_id', $account->id)
            ->where('issued_at', '<', $periodStart)
            ->whereNotIn('status', ['voided'])
            ->sum('total_amount');

        $priorPaid = (float) Payment::where('account_id', $account->id)
            ->where('payment_date', '<', $periodStart)
            ->whereNull('deleted_at')
            ->sum('amount');

        $priorCredited = (float) (clone $creditNotesQuery)
            ->where('created_at', '<', $periodStart)
            ->sum('amount');

        // Negative = account was in credit; positive = amount owed from before period
        $openingBalance = round($priorBilled - $priorPaid - $priorCredited, 2);

        // ── 5. Build transaction ledger ───────────────────────────────────
        $transactions = collect();

        // Debit rows — one per bill
        foreach ($billings as $bill) {
            $transactions->push([
                'date'            => Carbon::parse($bill->issued_at)->format('d M Y'),
                'sort_date'       => $bill->issued_at,
                'type'            => 'bill',
                'reference'       => "Bill #{$bill->id}",
                'description'     => $bill->getFormattedPeriod() . ' water charges',
                'debit'           => (float) $bill->total_amount,
                'credit'          => null,
                'running_balance' => 0,
            ]);
        }

        // Credit rows are dated when the credit note is applied, regardless
        // of when the bill it adjusts was issued.
        foreach ($creditNotes as $creditNote) {
            $transactions->push([
                'date'            => Carbon::parse($creditNote->created_at)->format('d M Y'),
                'sort_date'       => $creditNote->created_at,
                'type'            => 'credit_note',
                'reference'       => $creditNote->reference,
                'description'     => $creditNote->typeLabel() . ': ' . $creditNote->reason,
                'debit'           => null,
                'credit'          => (float) $creditNote->amount,
                'running_balance' => 0,
            ]);
        }

        // Credit rows — one per payment
        foreach ($payments as $payment) {
            $transactions->push([
                'date'            => Carbon::parse($payment->payment_date)->format('d M Y'),
                'sort_date'       => $payment->payment_date,
                'type'            => 'payment',
                'reference'       => $payment->reference ?? $payment->transaction_id ?? "PAY-{$payment->id}",
                'description'     => 'Payment received (' . ucfirst($payment->method ?? 'N/A') . ')'
                                     . ($payment->transaction_id ? " · {$payment->transaction_id}" : ''),
                'debit'           => null,
                'credit'          => (float) $payment->amount,
                'running_balance' => 0,
            ]);
        }

        // Sort chronologically, compute running balance.
        // Seed with openingBalance so every row reflects the true account
        // position — not just the movement within this period.
        $runningBalance = $openingBalance;
        $transactions = $transactions
            ->sortBy('sort_date')
            ->values()
            ->map(function ($t) use (&$runningBalance) {
                $runningBalance += ($t['debit'] ?? 0) - ($t['credit'] ?? 0);
                $t['running_balance'] = round($runningBalance, 2);
                return $t;
            });

        // ── 6. Totals ─────────────────────────────────────────────────────
        $totalBilled   = (float) $billings->sum('total_amount');
        $totalPaid     = (float) $payments->sum('amount');
        $totalCredited = (float) $creditNotes->sum('amount');

        // Closing balance = opening + current period activity.
        // Negative closing balance means account is in credit after this period.
        $closingBalance = round($openingBalance + $totalBilled - $totalPaid - $totalCredited, 2);

        return [
            'account' => [
                'name'    => $account->name,
                'number'  => $account->account_number ?? 'N/A',
                'address' => $account->address ?? null,
                'phone'   => $account->phone    ?? null,
                'email'   => $account->email    ?? null,
            ],
            'company' => [
                'name'    => Config::get('app.company_name', config('app.name')),
                'logo'    => Config::get('app.company_logo', null),
                'address' => Config::get('app.company_address', null),
                'phone'   => Config::get('app.company_phone', null),
                'email'   => Config::get('app.company_email', null),
            ],
            'period' => [
                'start' => Carbon::parse($startDate)->format('d M Y'),
                'end'   => Carbon::parse($endDate)->format('d M Y'),
            ],
            'opening_balance' => $openingBalance,
            'total_billed'    => $totalBilled,
            'total_paid'      => $totalPaid,
            'total_credited'  => $totalCredited,
            'closing_balance' => $closingBalance,
            'transactions'    => $transactions->toArray(),
            'generated_at'    => Carbon::now(),
        ];
    }

    public function generatePdf(Account $account, string $startDate, string $endDate): \Barryvdh\DomPDF\PDF
    {
        $data = $this->buildData($account, $startDate, $endDate);

        return Pdf::loadView('statements.account-statement', $data)
                  ->setPaper('a4', 'portrait');
    }
}
