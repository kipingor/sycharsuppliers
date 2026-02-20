<?php

namespace App\Services\Billing;

use App\Models\Billing;
use App\Models\Account;
use Carbon\Carbon;
use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Facades\Mail;
use App\Services\Billing\BalanceResolver;

/**
 * Statement Generator Service
 * 
 * Generates PDF statements for bills and accounts.
 * Handles statement formatting, data preparation, and PDF generation.
 * 
 * @package App\Services\Billing
 */
class StatementGenerator
{
    public function __construct(
        protected BalanceResolver $balanceResolver,
        protected PDF $pdf
    ) {}

    /**
     * Generate bill statement
     * 
     * @param Billing $billing
     * @return string PDF content
     */
    public function generateBillStatement(Billing $billing): string
    {
        $data = $this->prepareBillStatementData($billing);
        
        // Generate PDF using a PDF library (e.g., DomPDF)
        $pdf = $this->pdf->loadView('statements.bill', $data);
        
        // return $pdf->output();
        return $pdf->download('bill_statement_' . $billing->id . '.pdf');
    }

    public function sendBillStatement(Billing $billing, string $email): void
    {
        $data = $this->prepareBillStatementData($billing);
        
        // Generate PDF using a PDF library (e.g., DomPDF)
        $pdf = $this->pdf->loadView('statements.bill', $data);

        // Send email with the PDF attachment
        Mail::to($email)->send(new \App\Mail\BillingStatement($pdf->output(), $data));
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
        
        $pdf = $this->pdf->loadView('statements.account', $data);
        
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
        $billing->load([
            'account',
            'details.meter',
            'payments.allocations',
        ]);

        $account = $billing->account;

        return [
            'statement_type' => 'bill',
            'generated_at' => now(),
            
            // Account information
            'account' => [
                'number' => $account->account_number,
                'name' => $account->name,
                'address' => $account->address,
                'email' => $account->email,
                'phone' => $account->phone,
            ],

            // Bill information
            'billing' => [
                'id' => $billing->id,
                'period' => $billing->billing_period,
                'issued_date' => $billing->issued_at->format('F j, Y'),
                'due_date' => $billing->due_date->format('F j, Y'),
                'status' => ucfirst($billing->status),
                'is_overdue' => $billing->isOverdue(),
                'days_overdue' => $billing->getDaysOverdue(),
            ],

            // Consumption details
            'details' => $billing->details->map(function ($detail) {
                return [
                    'meter_number' => $detail->meter->meter_number,
                    'meter_name' => $detail->meter->meter_name,
                    'previous_reading' => number_format($detail->previous_reading, 2),
                    'current_reading' => number_format($detail->current_reading, 2),
                    'consumption' => number_format($detail->units, 2),
                    'rate' => number_format($detail->rate, 2),
                    'amount' => number_format($detail->amount, 2),
                ];
            })->toArray(),

            // Amounts
            'amounts' => [
                'subtotal' => number_format($billing->total_amount - ($billing->late_fee ?? 0), 2),
                'late_fee' => $billing->late_fee ? number_format($billing->late_fee, 2) : null,
                'total' => number_format($billing->total_amount, 2),
                'paid' => number_format($billing->paid_amount, 2),
                'balance' => number_format($billing->balance, 2),
            ],

            // Payment history
            'payments' => $billing->payments->map(function ($payment) {
                return [
                    'date' => $payment->payment_date->format('F j, Y'),
                    'reference' => $payment->reference,
                    'method' => ucfirst($payment->method),
                    'amount' => number_format($payment->amount, 2),
                ];
            })->toArray(),

            // Account summary
            'account_summary' => $this->balanceResolver->getAccountBalance($account),

            // Company information
            'company' => $this->getCompanyInformation(),
        ];
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
            'logo' => config('billing.company.logo', ''),
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