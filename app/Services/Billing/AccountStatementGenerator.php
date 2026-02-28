<?php

namespace App\Services\Billing;

use App\Models\Account;
use App\Models\Billing;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

class AccountStatementGenerator
{
    public function buildData(Account $account, string $startDate, string $endDate): array
    {
        // ── 1. Billings in period ─────────────────────────────────────────
        $billings = Billing::with(['creditNotes'])
            ->where('account_id', $account->id)
            ->whereBetween('issued_at', [$startDate, $endDate . ' 23:59:59'])
            ->whereNotIn('status', ['voided'])
            ->orderBy('issued_at')
            ->get();

        // ── 2. All payments for this account in period ────────────────────
        // Payments are stored at account level (billing_id is always null).
        // They may later be allocated to bills via payment_allocations, but
        // we show them by their actual payment_date regardless.
        $payments = Payment::where('account_id', $account->id)
            ->whereBetween('payment_date', [$startDate, $endDate . ' 23:59:59'])
            ->whereNull('deleted_at')
            ->orderBy('payment_date')
            ->get();

        // ── 3. Check if allocations exist (reconciled accounts) ───────────
        $billingIds  = $billings->pluck('id');
        $paymentIds  = $payments->pluck('id');
        $allocations = PaymentAllocation::whereIn('billing_id', $billingIds)
            ->orWhereIn('payment_id', $paymentIds)
            ->get()
            ->groupBy('payment_id');  // keyed by payment_id for quick lookup

        // ── 4. Build transaction ledger ───────────────────────────────────
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

            // Credit notes against this bill
            foreach ($bill->creditNotes->where('status', 'applied') as $cn) {
                $transactions->push([
                    'date'            => Carbon::parse($cn->created_at)->format('d M Y'),
                    'sort_date'       => $cn->created_at,
                    'type'            => 'credit_note',
                    'reference'       => $cn->reference,
                    'description'     => $cn->typeLabel() . ': ' . $cn->reason,
                    'debit'           => null,
                    'credit'          => (float) $cn->amount,
                    'running_balance' => 0,
                ]);
            }
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

        // Sort chronologically, compute running balance
        $runningBalance = 0;
        $transactions = $transactions
            ->sortBy('sort_date')
            ->values()
            ->map(function ($t) use (&$runningBalance) {
                $runningBalance += ($t['debit'] ?? 0) - ($t['credit'] ?? 0);
                $t['running_balance'] = $runningBalance;
                return $t;
            });

        // ── 5. Totals ─────────────────────────────────────────────────────
        $totalBilled   = (float) $billings->sum('total_amount');
        $totalPaid     = (float) $payments->sum('amount');
        $totalCredited = (float) $billings->sum(
            fn ($b) => $b->creditNotes->where('status', 'applied')->sum('amount')
        );
        $closingBalance = max(0, $totalBilled - $totalPaid - $totalCredited);

        // ── 6. Opening balance ────────────────────────────────────────────
        // Unpaid charges from before the period minus payments before the period
        $priorBilled = (float) Billing::where('account_id', $account->id)
            ->where('issued_at', '<', $startDate)
            ->whereNotIn('status', ['voided'])
            ->sum('total_amount');

        $priorPaid = (float) Payment::where('account_id', $account->id)
            ->where('payment_date', '<', $startDate)
            ->whereNull('deleted_at')
            ->sum('amount');

        $priorCredited = (float) Billing::with('creditNotes')
            ->where('account_id', $account->id)
            ->where('issued_at', '<', $startDate)
            ->whereNotIn('status', ['voided'])
            ->get()
            ->sum(fn ($b) => $b->creditNotes->where('status', 'applied')->sum('amount'));

        $openingBalance = max(0, $priorBilled - $priorPaid - $priorCredited);

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
