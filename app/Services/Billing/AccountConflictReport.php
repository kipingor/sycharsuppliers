<?php

namespace App\Services\Billing;

use App\Models\Account;
use App\Models\Billing;
use Illuminate\Support\Collection;

class AccountConflictReport
{
    public function build(): array
    {
        $conflicts = collect();

        Account::query()
            ->with([
                'billings.allocations.payment',
                'billings.directPayments',
                'billings.creditNotes',
                'payments.allocations',
            ])
            ->chunkById(50, function (Collection $accounts) use ($conflicts) {
                foreach ($accounts as $account) {
                    $billings = $account->billings->reject(fn (Billing $bill) => $bill->status === 'voided');
                    $payments = $account->payments->whereNull('deleted_at');
                    $appliedCredits = $billings->sum(
                        fn (Billing $bill) => $bill->creditNotes->where('status', 'applied')->sum('amount')
                    );
                    $ledgerBalance = round(
                        (float) $billings->sum('total_amount')
                        - (float) $payments->sum('amount')
                        - (float) $appliedCredits,
                        2
                    );
                    $openBillBalance = round((float) $billings
                        ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
                        ->sum('amount_due'), 2);

                    $issues = [];
                    $paidBillsWithDue = $billings
                        ->filter(fn (Billing $bill) => $bill->status === 'paid' && (float) $bill->amount_due > 0.01);
                    if ($paidBillsWithDue->isNotEmpty()) {
                        $issues[] = [
                            'code' => 'paid_bill_has_due',
                            'label' => 'Bills marked paid still have balances recorded',
                            'count' => $paidBillsWithDue->count(),
                            'amount' => round((float) $paidBillsWithDue->sum('amount_due'), 2),
                            'bill_ids' => $paidBillsWithDue->take(5)->pluck('id')->values(),
                        ];
                    }

                    $openBalanceMismatches = $billings
                        ->filter(fn (Billing $bill) => in_array($bill->status, ['pending', 'partially_paid', 'overdue'], true)
                            && abs((float) $bill->amount_due - $bill->balance) > 0.01);
                    if ($openBalanceMismatches->isNotEmpty()) {
                        $issues[] = [
                            'code' => 'open_bill_balance_mismatch',
                            'label' => 'Open bill balances differ from recorded payments and credits',
                            'count' => $openBalanceMismatches->count(),
                            'amount' => round((float) $openBalanceMismatches->sum(
                                fn (Billing $bill) => abs((float) $bill->amount_due - $bill->balance)
                            ), 2),
                            'bill_ids' => $openBalanceMismatches->take(5)->pluck('id')->values(),
                        ];
                    }

                    $overCreditedBills = $billings->filter(function (Billing $bill) {
                        $credited = $bill->creditNotes->where('status', 'applied')->sum('amount');
                        return $credited > max(0, (float) $bill->total_amount - $bill->paid_amount) + 0.01;
                    });
                    if ($overCreditedBills->isNotEmpty()) {
                        $issues[] = [
                            'code' => 'credit_exceeds_remaining_bill',
                            'label' => 'Applied credit notes exceed the balance remaining after payments',
                            'count' => $overCreditedBills->count(),
                            'amount' => round((float) $overCreditedBills->sum(function (Billing $bill) {
                                $credited = $bill->creditNotes->where('status', 'applied')->sum('amount');
                                return max(0, $credited - max(0, (float) $bill->total_amount - $bill->paid_amount));
                            }), 2),
                            'bill_ids' => $overCreditedBills->take(5)->pluck('id')->values(),
                        ];
                    }

                    $unreconciledPayments = $payments->filter(
                        fn ($payment) => $payment->status === 'completed'
                            && $payment->reconciliation_status !== 'reconciled'
                    );
                    if ($unreconciledPayments->isNotEmpty()) {
                        $issues[] = [
                            'code' => 'unreconciled_completed_payment',
                            'label' => 'Completed payments are not reconciled to bills',
                            'count' => $unreconciledPayments->count(),
                            'amount' => round((float) $unreconciledPayments->sum('amount'), 2),
                            'bill_ids' => [],
                        ];
                    }

                    $difference = round($ledgerBalance - $openBillBalance, 2);
                    if (abs($difference) > 0.01) {
                        $issues[] = [
                            'code' => 'ledger_vs_open_bills',
                            'label' => 'Account ledger balance differs from open bill balances',
                            'count' => 1,
                            'amount' => abs($difference),
                            'bill_ids' => [],
                        ];
                    }

                    if ($issues !== []) {
                        $conflicts->push([
                            'account_id' => $account->id,
                            'account_number' => $account->account_number,
                            'name' => $account->name,
                            'status' => $account->status,
                            'ledger_balance' => $ledgerBalance,
                            'open_bill_balance' => $openBillBalance,
                            'difference' => $difference,
                            'issues' => $issues,
                        ]);
                    }
                }
            });

        return [
            'accounts' => $conflicts->values()->all(),
            'total_accounts' => $conflicts->count(),
            'generated_at' => now()->toDateTimeString(),
        ];
    }
}
