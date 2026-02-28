<?php

namespace App\Services\Billing;

use App\Models\Billing;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use App\Models\Account;

class StatementGenerator
{
    /**
     * Generate a PDF bill statement and return the DomPDF instance.
     *
     * @param  Billing  $bill
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generateBillStatement(Billing $billing): \Barryvdh\DomPDF\PDF
    {
        // Eager-load everything the view needs (safe to call even if already loaded)
        $billing->loadMissing([
            'account',
            'details.meter',
            'allocations.payment',
            'creditNotes',
        ]);

        // ── Company info ──────────────────────────────────────────────────────
        $company = [
            'name'    => Config::get('app.company_name', config('app.name', 'Sychar Suppliers')),
            'logo'    => Config::get('app.company_logo', public_path('logo.png')),
            'address' => Config::get('app.company_address', null),
            'phone'   => Config::get('app.company_phone', null),
            'email'   => Config::get('app.company_email', null),
            'website' => Config::get('app.company_website', null),
        ];

        // ── Account info ──────────────────────────────────────────────────────
        $account = [
            'number'  => $billing->account?->account_number ?? 'N/A',
            'name'    => $billing->account?->name ?? 'N/A',
            'address' => $billing->account?->address ?? null,
            'phone'   => $billing->account?->phone ?? null,
            'email'   => $billing->account?->email ?? null,
        ];

        // ── Billing header info ───────────────────────────────────────────────
        $isOverdue   = $billing->due_date && Carbon::parse($billing->due_date)->isPast()
            && ! in_array($billing->status, ['paid', 'voided']);
        $daysOverdue = $isOverdue
            ? (int) Carbon::parse($billing->due_date)->diffInDays(now())
            : 0;

        $billingData = [
            'id'          => $billing->id,
            'status'      => ucfirst(str_replace('_', ' ', $billing->status)),
            'period'      => $billing->billing_period,                          // e.g. "2026-02"
            'issued_date' => Carbon::parse($billing->issued_at)->format('d M Y'),
            'due_date'    => Carbon::parse($billing->due_date)->format('d M Y'),
            'is_overdue'  => $isOverdue,
            'days_overdue' => $daysOverdue,
        ];

        // ── Consumption details ───────────────────────────────────────────────
        $details = ($billing->details ?? collect())->map(fn($d) => [
            'meter_number'     => $d->meter?->meter_number ?? 'N/A',
            'meter_name'       => $d->meter?->meter_name   ?? null,
            'previous_reading' => number_format($d->previous_reading, 2),
            'current_reading'  => number_format($d->current_reading, 2),
            'consumption'      => number_format($d->units, 2),
            'rate'             => number_format($d->rate, 2),
            'amount'           => number_format($d->amount, 2),
        ])->toArray();

        // ── Amount summary ────────────────────────────────────────────────────
        $totalCredited = ($billing->creditNotes ?? collect())
            ->where('status', 'applied')
            ->sum('amount');

        $amounts = [
            'subtotal'     => number_format($billing->total_amount, 2),
            'late_fee'     => null,
            'total'        => number_format($billing->total_amount, 2),
            'credited'     => $totalCredited > 0 ? number_format($totalCredited, 2) : null,
            'paid'         => number_format($billing->paid_amount ?? 0, 2),
            'balance'      => number_format($billing->balance ?? $billing->total_amount, 2),
        ];

        $creditNotes = ($billing->creditNotes ?? collect())
            ->where('status', 'applied')
            ->map(fn($cn) => [
                'reference'        => $cn->reference,
                'type'             => $cn->typeLabel(),
                'amount'           => number_format($cn->amount, 2),
                'reason'           => $cn->reason,
                'date'             => $cn->created_at->format('d M Y'),
                'previous_account' => $cn->previousAccount?->name ?? null,
            ])->values()->toArray();

        // ── Payment history ───────────────────────────────────────────────────
        $payments = ($billing->allocations ?? collect())->map(fn($a) => [
            'date'      => $a->payment?->payment_date
                ? Carbon::parse($a->payment->payment_date)->format('d M Y')
                : 'N/A',
            'reference' => $a->payment?->reference ?? 'N/A',
            'method'    => ucfirst($a->payment?->method ?? 'N/A'),
            'amount'    => number_format($a->allocated_amount, 2),
        ])->toArray();

        // ── Build the data array passed to the Blade view ─────────────────────
        // THIS is what was broken: loadView() requires an array as its 2nd argument.
        $data = [
            'billing'      => $billingData,
            'company'      => $company,
            'account'      => $account,
            'details'      => $details,
            'amounts'      => $amounts,
            'payments'     => $payments,
            'credit_notes' => $creditNotes,
            'generated_at' => Carbon::now(),
        ];

        return Pdf::loadView('statements.bill', $data)   // ← array, not a string
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'sans-serif');
    }


    public function sendBillStatement(Billing $billing, string $email): void
    {
        // Load relations FIRST — before prepareBillStatementData so everything is available
        $data = $this->prepareBillStatementData($billing);

        $pdf = Pdf::loadView('statements.bill', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'sans-serif');

        Mail::to($email)->send(new \App\Mail\BillingStatement($pdf->output(), $billing));
    }

    /**
     * Generate account statement for a period
     *
     * @param Account $account
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return string PDF content
     */
    public function generateAccountStatement(
        Account $account,
        Carbon $startDate,
        Carbon $endDate
    ): string {
        $data = $this->prepareAccountStatementData($account, $startDate, $endDate);

        $pdf = Pdf::loadView('statements.account', $data);

        return $pdf->output();
    }

    /**
     * Prepare bill statement data
     *
     * @param Billing $billing
     * @return array
     */
    protected function prepareBillStatementData(Billing $billing): array
    {
        $billing->loadMissing([
            'account',
            'details.meter',
            'allocations.payment',
            'creditNotes',
        ]);

        // ── Company info ──────────────────────────────────────────────────────
        $company = [
            'name'    => Config::get('app.company_name', config('app.name', 'Sychar Suppliers')),
            'logo'    => Config::get('app.company_logo', public_path('logo.png')),
            'address' => Config::get('app.company_address', null),
            'phone'   => Config::get('app.company_phone', null),
            'email'   => Config::get('app.company_email', null),
            'website' => Config::get('app.company_website', null),
        ];

        // ── Account info ──────────────────────────────────────────────────────
        $account = [
            'number'  => $billing->account?->account_number ?? 'N/A',
            'name'    => $billing->account?->name ?? 'N/A',
            'address' => $billing->account?->address ?? null,
            'phone'   => $billing->account?->phone ?? null,
            'email'   => $billing->account?->email ?? null,
        ];

        // ── Billing header info ───────────────────────────────────────────────
        $isOverdue   = $billing->due_date && Carbon::parse($billing->due_date)->isPast()
            && ! in_array($billing->status, ['paid', 'voided']);
        $daysOverdue = $isOverdue
            ? (int) Carbon::parse($billing->due_date)->diffInDays(now())
            : 0;

        $billingData = [
            'id'          => $billing->id,
            'status'      => ucfirst(str_replace('_', ' ', $billing->status)),
            'period'      => $billing->billing_period,                          // e.g. "2026-02"
            'issued_date' => Carbon::parse($billing->issued_at)->format('d M Y'),
            'due_date'    => Carbon::parse($billing->due_date)->format('d M Y'),
            'is_overdue'  => $isOverdue,
            'days_overdue' => $daysOverdue,
        ];

        // ── Consumption details ───────────────────────────────────────────────
        $details = ($billing->details ?? collect())->map(fn($d) => [
            'meter_number'     => $d->meter?->meter_number ?? 'N/A',
            'meter_name'       => $d->meter?->meter_name   ?? null,
            'previous_reading' => number_format($d->previous_reading, 2),
            'current_reading'  => number_format($d->current_reading, 2),
            'consumption'      => number_format($d->units, 2),
            'rate'             => number_format($d->rate, 2),
            'amount'           => number_format($d->amount, 2),
        ])->toArray();

        // ── Amount summary ────────────────────────────────────────────────────
        $totalCredited = ($billing->creditNotes ?? collect())
            ->where('status', 'applied')
            ->sum('amount');

        $amounts = [
            'subtotal'     => number_format($billing->total_amount, 2),
            'late_fee'     => null,
            'total'        => number_format($billing->total_amount, 2),
            'credited'     => $totalCredited > 0 ? number_format($totalCredited, 2) : null,
            'paid'         => number_format($billing->paid_amount ?? 0, 2),
            'balance'      => number_format($billing->balance ?? $billing->total_amount, 2),
        ];

        $creditNotes = ($billing->creditNotes ?? collect())
            ->where('status', 'applied')
            ->map(fn($cn) => [
                'reference'        => $cn->reference,
                'type'             => $cn->typeLabel(),
                'amount'           => number_format($cn->amount, 2),
                'reason'           => $cn->reason,
                'date'             => $cn->created_at->format('d M Y'),
                'previous_account' => $cn->previousAccount?->name ?? null,
            ])->values()->toArray();

        // ── Payment history ───────────────────────────────────────────────────
        $payments = ($billing->allocations ?? collect())->map(fn($a) => [
            'date'      => $a->payment?->payment_date
                ? Carbon::parse($a->payment->payment_date)->format('d M Y')
                : 'N/A',
            'reference' => $a->payment?->reference ?? 'N/A',
            'method'    => ucfirst($a->payment?->method ?? 'N/A'),
            'amount'    => number_format($a->allocated_amount, 2),
        ])->toArray();

        // ── Build the data array passed to the Blade view ─────────────────────
        // THIS is what was broken: loadView() requires an array as its 2nd argument.
        $data = [
            'billing'      => $billingData,
            'company'      => $company,
            'account'      => $account,
            'details'      => $details,
            'amounts'      => $amounts,
            'payments'     => $payments,
            'credit_notes' => $creditNotes,
            'generated_at' => Carbon::now(),
        ];

        return $data;
    }

    /**
     * Prepare account statement data
     *
     * @param Account $account
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    protected function prepareAccountStatementData(
        Account $account,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        // Get bills in period
        $bills = $account->billings()
            ->whereBetween('issued_at', [$startDate, $endDate])
            ->orderBy('issued_at')
            ->get();

        // Get payments in period
        $payments = $account->payments()
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->orderBy('payment_date')
            ->get();

        // Calculate opening balance (balance before start date)
        $openingBalance = $this->calculateOpeningBalance($account, $startDate);

        // Prepare transactions
        $transactions = $this->prepareTransactionHistory($bills, $payments, $openingBalance);

        return [
            'statement_type' => 'account',
            'generated_at' => now(),
            'period' => [
                'start' => $startDate->format('F j, Y'),
                'end' => $endDate->format('F j, Y'),
            ],

            // Account information
            'account' => [
                'number' => $account->account_number,
                'name' => $account->name,
                'address' => $account->address,
                'email' => $account->email,
                'phone' => $account->phone,
            ],

            // Balance summary
            'summary' => [
                'opening_balance' => number_format($openingBalance, 2),
                'total_billed' => number_format($bills->sum('total_amount'), 2),
                'total_paid' => number_format($payments->sum('amount'), 2),
                'closing_balance' => number_format($transactions['closing_balance'], 2),
            ],

            // Transaction history
            'transactions' => $transactions['items'],

            // Company information
            'company' => $this->getCompanyInformation(),
        ];
    }

    /**
     * Calculate opening balance
     *
     * @param Account $account
     * @param Carbon $startDate
     * @return float
     */
    protected function calculateOpeningBalance(Account $account, Carbon $startDate): float
    {
        $billsBeforeStart = $account->billings()
            ->where('issued_at', '<', $startDate)
            ->sum('total_amount');

        $paymentsBeforeStart = $account->payments()
            ->where('payment_date', '<', $startDate)
            ->sum('amount');

        return $billsBeforeStart - $paymentsBeforeStart;
    }

    /**
     * Prepare transaction history
     *
     * @param \Illuminate\Support\Collection $bills
     * @param \Illuminate\Support\Collection $payments
     * @param float $openingBalance
     * @return array
     */
    protected function prepareTransactionHistory($bills, $payments, float $openingBalance): array
    {
        $transactions = [];
        $runningBalance = $openingBalance;

        // Combine bills and payments
        $allTransactions = [];

        foreach ($bills as $bill) {
            $allTransactions[] = [
                'date' => $bill->issued_at,
                'type' => 'bill',
                'data' => $bill,
            ];
        }

        foreach ($payments as $payment) {
            $allTransactions[] = [
                'date' => $payment->payment_date,
                'type' => 'payment',
                'data' => $payment,
            ];
        }

        // Sort by date
        usort($allTransactions, function ($a, $b) {
            return $a['date']->timestamp - $b['date']->timestamp;
        });

        // Build transaction list
        foreach ($allTransactions as $transaction) {
            $item = [
                'date' => $transaction['date']->format('F j, Y'),
            ];

            if ($transaction['type'] === 'bill') {
                $bill = $transaction['data'];
                $item['description'] = "Bill for {$bill->billing_period}";
                $item['reference'] = "Bill #{$bill->id}";
                $item['debit'] = number_format($bill->total_amount, 2);
                $item['credit'] = null;
                $runningBalance += $bill->total_amount;
            } else {
                $payment = $transaction['data'];
                $item['description'] = "Payment - " . ucfirst($payment->method);
                $item['reference'] = $payment->reference;
                $item['debit'] = null;
                $item['credit'] = number_format($payment->amount, 2);
                $runningBalance -= $payment->amount;
            }

            $item['balance'] = number_format($runningBalance, 2);
            $transactions[] = $item;
        }

        return [
            'items' => $transactions,
            'closing_balance' => $runningBalance,
        ];
    }

    /**
     * Get company information for statements
     *
     * @return array
     */
    protected function getCompanyInformation(): array
    {
        return [
            'name' => config('app.name', 'Water Company'),
            'address' => config('billing.company.address', ''),
            'phone' => config('billing.company.phone', ''),
            'email' => config('billing.company.email', ''),
            'website' => config('billing.company.website', ''),
            'logo' => public_path('logo.png'),
        ];
    }

    /**
     * Generate statement filename
     *
     * @param string $type 'bill' or 'account'
     * @param int|string $identifier
     * @return string
     */
    public function generateFilename(string $type, $identifier): string
    {
        $date = now()->format('Y-m-d');
        return "{$type}_statement_{$identifier}_{$date}.pdf";
    }
}
