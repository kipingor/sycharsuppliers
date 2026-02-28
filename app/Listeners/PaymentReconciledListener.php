<?php

namespace App\Listeners;

use App\Events\Billing\PaymentReconciled;
use App\Models\Billing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Payment Reconciled Listener
 * 
 * Handles post-reconciliation actions:
 * - Updates bill statuses
 * - Sends notifications
 * - Logs for reporting
 * 
 * @package App\Listeners
 */
class PaymentReconciledListener implements ShouldQueue
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
    public function handle(PaymentReconciled $event): void
    {
        Log::info('Payment reconciled, processing follow-up actions', [
            'payment_id' => $event->payment->id,
            'reconciliation_status' => $event->result->reconciliationStatus,
            'allocated_amount' => $event->result->allocatedAmount,
        ]);

        try {
            // Update bill statuses based on allocations
            $this->updateBillStatuses($event);

            // Send notification if configured
            if (config('billing.notifications.on_payment_received', true)) {
                $this->sendPaymentNotification($event);
            }

            // Check if account has overpayment (credit)
            if ($event->result->remainingAmount > 0) {
                $this->handleOverpayment($event);
            }

            // Check if all bills are paid
            if ($this->allBillsPaid($event)) {
                $this->handleAccountFullyPaid($event);
            }
        } catch (\Exception $e) {
            Log::error('Failed to process payment reconciled event', [
                'payment_id' => $event->payment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update bill statuses based on allocations
     */
    protected function updateBillStatuses(PaymentReconciled $event): void
    {
        foreach ($event->result->allocations as $allocation) {
            $billing = Billing::find($allocation['billing_id']);
            
            if (!$billing) {
                continue;
            }

            // Check if bill is now fully paid
            if ($billing->balance <= 0) {
                $billing->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                Log::info('Bill marked as paid', [
                    'billing_id' => $billing->id,
                    'payment_id' => $event->payment->id,
                ]);
            } elseif ($billing->paid_amount > 0 && $billing->status === 'pending') {
                $billing->update([
                    'status' => 'partially_paid',
                ]);

                Log::info('Bill marked as partially paid', [
                    'billing_id' => $billing->id,
                    'paid_amount' => $billing->paid_amount,
                    'balance' => $billing->balance,
                ]);
            }
        }
    }

    /**
     * Send payment notification to account
     */
    protected function sendPaymentNotification(PaymentReconciled $event): void
    {
        $account = $event->payment->account;

        // This would send actual notification via mail/sms
        Log::info('Payment notification would be sent', [
            'account_id' => $account->id,
            'payment_id' => $event->payment->id,
            'amount' => $event->payment->amount,
        ]);

        // Example: Notification::send($account->user, new PaymentReceivedNotification($event->payment));
    }

    /**
     * Handle overpayment scenario
     */
    protected function handleOverpayment(PaymentReconciled $event): void
    {
        Log::info('Overpayment detected, credit created', [
            'payment_id' => $event->payment->id,
            'credit_amount' => $event->result->remainingAmount,
        ]);

        // Credit was already created by reconciliation service
        // Just log for reporting purposes
    }

    /**
     * Check if all account bills are paid
     */
    protected function allBillsPaid(PaymentReconciled $event): bool
    {
        $account = $event->payment->account;
        
        return $account->getOutstandingBills()->isEmpty();
    }

    /**
     * Handle account fully paid scenario
     */
    protected function handleAccountFullyPaid(PaymentReconciled $event): void
    {
        Log::info('Account fully paid', [
            'account_id' => $event->payment->account_id,
            'payment_id' => $event->payment->id,
        ]);

        /**
         * TODO: Could trigger:
         * - Thank you notification
         * - Account reactivation if suspended
         * - Reporting/analytics update
         */
    }

    /**
     * Handle a job failure.
     */
    public function failed(PaymentReconciled $event, \Throwable $exception): void
    {
        Log::error('PaymentReconciledListener failed', [
            'payment_id' => $event->payment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}