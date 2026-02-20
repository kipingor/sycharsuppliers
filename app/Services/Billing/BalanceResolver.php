<?php

namespace App\Services\Billing;

use App\Models\Account;
use App\Models\Billing;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;

/**
 * Balance Resolver Service
 * 
 * Provides unified balance calculation methods for accounts.
 * Handles carry-forward credits, outstanding bills, and payment allocations.
 * 
 * @package App\Services\Billing
 */
class BalanceResolver
{
    /**
     * Get complete account balance breakdown
     * 
     * @param Account $account
     * @param bool $useCache
     * @return array
     */
    public function getAccountBalance(Account $account, bool $useCache = true): array
    {
        $cacheKey = "account:{$account->id}:balance";
        $cacheTtl = config('billing.cache.ttl', 3600);

        if ($useCache && config('billing.cache.enabled', true)) {
            return Cache::remember($cacheKey, $cacheTtl, function () use ($account) {
                return $this->calculateAccountBalance($account);
            });
        }

        return $this->calculateAccountBalance($account);
    }

    /**
     * Calculate account balance
     * 
     * @param Account $account
     * @return array
     */
    protected function calculateAccountBalance(Account $account): array
    {
        // Get all outstanding bills
        $outstandingBills = $account->billings()
            ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
            ->get();

        $totalBilled = $outstandingBills->sum('total_amount');
        $totalPaid = $outstandingBills->sum('paid_amount');
        $outstandingBalance = $totalBilled - $totalPaid;

        // Get carry-forward credits
        $credits = $account->carryForwardBalances()
            ->credit()
            ->active()
            ->sum('balance');

        // Get carry-forward debits
        $debits = $account->carryForwardBalances()
            ->debit()
            ->active()
            ->sum('balance');

        // Calculate net balance
        $netBalance = $outstandingBalance + $debits - $credits;

        // Get overdue information
        $overdueBills = $outstandingBills->filter(fn($bill) => $bill->isOverdue());
        $overdueAmount = $overdueBills->sum(fn($bill) => $bill->balance);

        // Get oldest unpaid bill
        $oldestBill = $outstandingBills
            ->where('status', '!=', 'paid')
            ->sortBy('due_date')
            ->first();

        return [
            'account_id' => $account->id,
            'total_billed' => round($totalBilled, 2),
            'total_paid' => round($totalPaid, 2),
            'outstanding_balance' => round($outstandingBalance, 2),
            'carry_forward_credits' => round($credits, 2),
            'carry_forward_debits' => round($debits, 2),
            'net_balance' => round($netBalance, 2),
            'overdue_amount' => round($overdueAmount, 2),
            'overdue_bill_count' => $overdueBills->count(),
            'outstanding_bill_count' => $outstandingBills->count(),
            'oldest_bill_date' => $oldestBill?->due_date?->format('Y-m-d'),
            'oldest_bill_days_overdue' => $oldestBill?->getDaysOverdue() ?? 0,
            'has_outstanding_balance' => $netBalance > 0,
            'has_overdue_bills' => $overdueBills->isNotEmpty(),
            'calculated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Get balance for specific billing period
     * 
     * @param Account $account
     * @param string $billingPeriod Format: Y-m
     * @return array
     */
    public function getPeriodBalance(Account $account, string $billingPeriod): array
    {
        $billing = $account->billings()
            ->where('billing_period', $billingPeriod)
            ->first();

        if (!$billing) {
            return [
                'billing_period' => $billingPeriod,
                'has_bill' => false,
                'total_amount' => 0,
                'paid_amount' => 0,
                'balance' => 0,
            ];
        }

        return [
            'billing_period' => $billingPeriod,
            'billing_id' => $billing->id,
            'has_bill' => true,
            'total_amount' => $billing->total_amount,
            'paid_amount' => $billing->paid_amount,
            'balance' => $billing->balance,
            'status' => $billing->status,
            'is_overdue' => $billing->isOverdue(),
            'days_overdue' => $billing->getDaysOverdue(),
            'due_date' => $billing->due_date->format('Y-m-d'),
        ];
    }

    /**
     * Get payment history with balance impact
     * 
     * @param Account $account
     * @param int $limit
     * @return array
     */
    public function getPaymentHistory(Account $account, int $limit = 10): array
    {
        $payments = $account->payments()
            ->with('allocations.billing')
            ->latest('payment_date')
            ->limit($limit)
            ->get();

        $history = [];

        foreach ($payments as $payment) {
            $allocations = $payment->allocations->map(function ($allocation) {
                return [
                    'billing_id' => $allocation->billing_id,
                    'billing_period' => $allocation->billing->billing_period,
                    'allocated_amount' => $allocation->allocated_amount,
                ];
            })->toArray();

            $history[] = [
                'payment_id' => $payment->id,
                'payment_date' => $payment->payment_date->format('Y-m-d'),
                'amount' => $payment->amount,
                'method' => $payment->method,
                'reference' => $payment->reference,
                'reconciliation_status' => $payment->reconciliation_status,
                'allocated_amount' => $payment->allocated_amount,
                'unallocated_amount' => $payment->unallocated_amount,
                'allocations' => $allocations,
            ];
        }

        return $history;
    }

    /**
     * Get outstanding bills summary
     * 
     * @param Account $account
     * @return array
     */
    public function getOutstandingBillsSummary(Account $account): array
    {
        $bills = $account->getOutstandingBills();

        $summary = [
            'total_count' => $bills->count(),
            'total_amount' => $bills->sum('total_amount'),
            'total_paid' => $bills->sum('paid_amount'),
            'total_balance' => $bills->sum(fn($b) => $b->balance),
            'by_status' => [],
            'by_period' => [],
            'oldest_date' => $bills->min('due_date')?->format('Y-m-d'),
            'newest_date' => $bills->max('due_date')?->format('Y-m-d'),
        ];

        // Group by status
        $byStatus = $bills->groupBy('status');
        foreach ($byStatus as $status => $statusBills) {
            $summary['by_status'][$status] = [
                'count' => $statusBills->count(),
                'total_amount' => $statusBills->sum('total_amount'),
                'balance' => $statusBills->sum(fn($b) => $b->balance),
            ];
        }

        // Group by period
        $byPeriod = $bills->groupBy('billing_period')->sortKeysDesc();
        foreach ($byPeriod as $period => $periodBills) {
            $summary['by_period'][$period] = [
                'count' => $periodBills->count(),
                'total_amount' => $periodBills->sum('total_amount'),
                'balance' => $periodBills->sum(fn($b) => $b->balance),
            ];
        }

        return $summary;
    }

    /**
     * Calculate projected balance after applying payment
     * 
     * @param Account $account
     * @param float $paymentAmount
     * @return array
     */
    public function projectPaymentImpact(Account $account, float $paymentAmount): array
    {
        $currentBalance = $this->getAccountBalance($account, false);
        $outstandingBills = $account->getOutstandingBills();

        // Simulate FIFO allocation
        $remainingAmount = $paymentAmount;
        $allocations = [];

        foreach ($outstandingBills as $bill) {
            if ($remainingAmount <= 0) {
                break;
            }

            $billBalance = $bill->balance;
            $toAllocate = min($remainingAmount, $billBalance);

            $allocations[] = [
                'billing_id' => $bill->id,
                'billing_period' => $bill->billing_period,
                'current_balance' => $billBalance,
                'allocated_amount' => $toAllocate,
                'new_balance' => $billBalance - $toAllocate,
                'will_be_paid' => ($billBalance - $toAllocate) <= 0.01,
            ];

            $remainingAmount -= $toAllocate;
        }

        return [
            'payment_amount' => $paymentAmount,
            'current_balance' => $currentBalance,
            'allocated_amount' => $paymentAmount - $remainingAmount,
            'remaining_amount' => $remainingAmount,
            'new_balance' => max(0, $currentBalance['net_balance'] - ($paymentAmount - $remainingAmount)),
            'bills_to_be_paid' => collect($allocations)->where('will_be_paid', true)->count(),
            'allocations' => $allocations,
            'will_have_credit' => $remainingAmount > 0,
            'credit_amount' => $remainingAmount,
        ];
    }

    /**
     * Get carry-forward balance details
     * 
     * @param Account $account
     * @return array
     */
    public function getCarryForwardDetails(Account $account): array
    {
        $credits = $account->carryForwardBalances()
            ->credit()
            ->active()
            ->get();

        $debits = $account->carryForwardBalances()
            ->debit()
            ->active()
            ->get();

        return [
            'credits' => $credits->map(fn($c) => $c->getSummary())->toArray(),
            'debits' => $debits->map(fn($d) => $d->getSummary())->toArray(),
            'total_credits' => $credits->sum('balance'),
            'total_debits' => $debits->sum('balance'),
            'net_carry_forward' => $credits->sum('balance') - $debits->sum('balance'),
        ];
    }

    /**
     * Clear account balance cache
     * 
     * @param Account $account
     * @return void
     */
    public function clearCache(Account $account): void
    {
        $cacheKey = "account:{$account->id}:balance";
        Cache::forget($cacheKey);
    }

    /**
     * Clear all balance caches
     * 
     * @return void
     */
    public function clearAllCaches(): void
    {
        // This would clear all balance-related caches
        // In production, you might want a more targeted approach
        Cache::tags(['balances'])->flush();
    }

    /**
     * Get aging report for account
     * 
     * @param Account $account
     * @return array
     */
    public function getAgingReport(Account $account): array
    {
        $bills = $account->billings()
            ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
            ->get();

        $aging = [
            'current' => ['count' => 0, 'amount' => 0],      // 0-30 days
            '30_days' => ['count' => 0, 'amount' => 0],     // 31-60 days
            '60_days' => ['count' => 0, 'amount' => 0],     // 61-90 days
            '90_plus' => ['count' => 0, 'amount' => 0],     // 90+ days
        ];

        foreach ($bills as $bill) {
            $daysOverdue = $bill->getDaysOverdue();
            $balance = $bill->balance;

            if ($daysOverdue <= 30) {
                $aging['current']['count']++;
                $aging['current']['amount'] += $balance;
            } elseif ($daysOverdue <= 60) {
                $aging['30_days']['count']++;
                $aging['30_days']['amount'] += $balance;
            } elseif ($daysOverdue <= 90) {
                $aging['60_days']['count']++;
                $aging['60_days']['amount'] += $balance;
            } else {
                $aging['90_plus']['count']++;
                $aging['90_plus']['amount'] += $balance;
            }
        }

        return $aging;
    }
}