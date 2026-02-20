<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;
use App\Models\Payment;
use App\Models\Account;
use App\Models\Billing;
use App\Events\Billing\PaymentApplied;

class ApplyPaymentService
{
    public function __construct(
        protected BalanceResolver $balanceResolver
    ) {}

    /**
     * Apply a payment to an account.
     *
     * @param int $accountId
     * @param float $amount
     * @param string $reference Unique payment reference (transaction ID, etc.)
     * @param int|null $billingId Optional: link to specific bill
     * @param string $method Payment method (M-Pesa, Bank Transfer, Cash, etc.)
     * @return Payment
     * @throws \Exception
     */
    public function apply(
        int $accountId,
        float $amount,
        string $reference,
        ?int $billingId = null,
        string $method = 'Bank Transfer',
    ): Payment {
        return DB::transaction(function () use ($accountId, $amount, $reference, $billingId, $method) {
            $account = Account::lockForUpdate()->findOrFail($accountId);

            // Create payment record
            $payment = Payment::create([
                'account_id' => $accountId,
                'billing_id' => $billingId,
                'amount' => $amount,
                'payment_date' => now()->toDateString(),
                'method' => $method,
                'reference' => $reference,
                'status' => 'completed',
            ]);

            // Recalculate account balance
            $newBalance = $this->balanceResolver->resolve($accountId);

            // Dispatch event
            event(new PaymentApplied($payment, $account, $newBalance));

            return $payment;
        });
    }
}