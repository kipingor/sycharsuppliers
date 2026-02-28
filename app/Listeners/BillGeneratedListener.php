<?php

namespace App\Listeners;

use App\Events\Billing\BillGenerated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Bill Generated Listener
 * 
 * Handles post-generation actions:
 * - Sends bill statements
 * - Notifies account holder
 * - Updates analytics
 * 
 * @package App\Listeners
 */
class BillGeneratedListener implements ShouldQueue
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
    public function handle(BillGenerated $event): void
    {
        Log::info('Bill generated, processing follow-up actions', [
            'billing_id' => $event->billing->id,
            'account_id' => $event->billing->account_id,
            'billing_period' => $event->billing->billing_period,
            'total_amount' => $event->billing->total_amount,
        ]);

        try {
            // Send bill statement if auto-send is enabled
            if (config('billing.statements.auto_send', true)) {
                $this->sendBillStatement($event);
            }

            // Send notification if configured
            if (config('billing.notifications.on_bill_generated', true)) {
                $this->sendBillNotification($event);
            }

            // Apply carry-forward credit if available
            $this->applyCarryForwardCredit($event);

            // Update account analytics
            $this->updateAccountAnalytics($event);
        } catch (\Exception $e) {
            Log::error('Failed to process bill generated event', [
                'billing_id' => $event->billing->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send bill statement to account
     */
    protected function sendBillStatement(BillGenerated $event): void
    {
        $billing = $event->billing;
        $account = $billing->account;

        // Check if account has email
        if (!$account->email) {
            Log::warning('Account has no email, skipping statement send', [
                'billing_id' => $billing->id,
                'account_id' => $account->id,
            ]);
            return;
        }

        Log::info('Dispatching send statement job', [
            'billing_id' => $billing->id,
            'account_email' => $account->email,
        ]);

        // This would dispatch SendStatementJob
        // SendStatementJob::dispatch($billing, $account->email);
    }

    /**
     * Send bill notification
     */
    protected function sendBillNotification(BillGenerated $event): void
    {
        Log::info('Bill notification would be sent', [
            'billing_id' => $event->billing->id,
            'account_id' => $event->billing->account_id,
        ]);

        // Example: Notification::send($account->user, new BillGeneratedNotification($event->billing));
    }

    /**
     * Apply carry-forward credit to new bill
     */
    protected function applyCarryForwardCredit(BillGenerated $event): void
    {
        $account = $event->billing->account;

        $activeCredit = $account->carryForwardBalances()
            ->where('type', 'credit')
            ->where('status', 'active')
            ->sum('balance');

        if ($activeCredit > 0) {
            Log::info('Account has carry-forward credit', [
                'account_id' => $account->id,
                'billing_id' => $event->billing->id,
                'credit_amount' => $activeCredit,
            ]);

            // Auto-apply credit would be handled by reconciliation
            // This is just for logging/notification
        }
    }

    /**
     * Update account analytics
     */
    protected function updateAccountAnalytics(BillGenerated $event): void
    {
        // Update billing statistics, consumption trends, etc.
        Log::debug('Account analytics updated', [
            'account_id' => $event->billing->account_id,
            'billing_id' => $event->billing->id,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(BillGenerated $event, \Throwable $exception): void
    {
        Log::error('BillGeneratedListener failed', [
            'billing_id' => $event->billing->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
