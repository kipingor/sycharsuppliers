<?php

namespace App\Listeners;

use App\Events\Billing\PaymentReceived;
use App\Jobs\ProcessPaymentJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Payment Received Listener
 * 
 * Handles initial payment processing:
 * - Dispatches reconciliation job
 * - Sends confirmation
 * - Updates account status
 * 
 * @package App\Listeners
 */
class PaymentReceivedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PaymentReceived $event): void
    {
        $payment = $event->payment;

        Log::info('Payment received, processing initial actions', [
            'payment_id' => $payment->id,
            'account_id' => $payment->account_id,
            'amount' => $payment->amount,
            'status' => $payment->status,
        ]);

        try {
            // Dispatch reconciliation job if payment is completed and auto-reconcile is enabled
            if ($payment->isCompleted() && config('reconciliation.auto_reconcile', true)) {
                $this->dispatchReconciliationJob($payment);
            }

            // Send payment confirmation
            $this->sendPaymentConfirmation($payment);

            // Check if account should be reactivated
            if ($payment->account->isSuspended()) {
                $this->checkAccountReactivation($payment);
            }
        } catch (\Exception $e) {
            Log::error('Failed to process payment received event', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Dispatch reconciliation job
     */
    protected function dispatchReconciliationJob($payment): void
    {
        Log::info('Dispatching payment reconciliation job', [
            'payment_id' => $payment->id,
        ]);

        ProcessPaymentJob::dispatch($payment, autoReconcile: true)
            ->delay(now()->addSeconds(5)); // Small delay to ensure payment is saved
    }

    /**
     * Send payment confirmation
     */
    protected function sendPaymentConfirmation($payment): void
    {
        Log::info('Payment confirmation would be sent', [
            'payment_id' => $payment->id,
            'account_id' => $payment->account_id,
        ]);

        // Example: Notification::send($payment->account->user, new PaymentConfirmationNotification($payment));
    }

    /**
     * Check if suspended account should be reactivated
     */
    protected function checkAccountReactivation($payment): void
    {
        $account = $payment->account;

        // Check if account has outstanding balance
        $currentBalance = $account->getCurrentBalance();

        if ($currentBalance <= 0) {
            Log::info('Account eligible for reactivation', [
                'account_id' => $account->id,
                'payment_id' => $payment->id,
            ]);

            // Could automatically reactivate or flag for manual review
            if (config('billing.account_status.auto_reactivate_on_payment', false)) {
                $account->activate();
                
                Log::info('Account automatically reactivated', [
                    'account_id' => $account->id,
                ]);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(PaymentReceived $event, \Throwable $exception): void
    {
        Log::error('PaymentReceivedListener failed', [
            'payment_id' => $event->payment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
