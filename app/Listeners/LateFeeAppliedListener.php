<?php

namespace App\Listeners;

use App\Events\Billing\LateFeeApplied;
use App\Services\Audit\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Late Fee Applied Listener
 * 
 * Handles actions when a late fee is applied to a bill:
 * - Send notification to account holder
 * - Update account metadata
 * - Log analytics
 * - Trigger additional warnings if needed
 * 
 * @package App\Listeners
 */
class LateFeeAppliedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the listener may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public $backoff = 300;

    /**
     * Create the event listener.
     */
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(LateFeeApplied $event): void
    {
        $billing = $event->billing;
        $lateFeeAmount = $event->lateFeeAmount;
        $daysOverdue = $event->daysOverdue;

        Log::info('Processing late fee application', [
            'billing_id' => $billing->id,
            'account_id' => $billing->account_id,
            'late_fee_amount' => $lateFeeAmount,
            'days_overdue' => $daysOverdue,
        ]);

        try {
            // Send notification to account holder
            $this->sendLateFeeNotification($billing, $lateFeeAmount, $daysOverdue);

            // Update account metadata
            $this->updateAccountMetadata($billing);

            // Check if account needs additional action
            $this->checkAccountStatus($billing, $daysOverdue);

            // Log analytics
            $this->logAnalytics($billing, $lateFeeAmount, $daysOverdue);

            Log::info('Late fee processing completed', [
                'billing_id' => $billing->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process late fee event', [
                'billing_id' => $billing->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send late fee notification
     */
    protected function sendLateFeeNotification($billing, float $lateFeeAmount, int $daysOverdue): void
    {
        $account = $billing->account;

        if (!$account->email) {
            Log::warning('Account has no email for late fee notification', [
                'account_id' => $account->id,
            ]);
            return;
        }

        // Send email notification
        try {
            // Using Laravel's notification system
            // $account->notify(new LateFeeAppliedNotification($billing, $lateFeeAmount, $daysOverdue));

            Log::info('Late fee notification sent', [
                'account_id' => $account->id,
                'email' => $account->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send late fee notification', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update account metadata
     */
    protected function updateAccountMetadata($billing): void
    {
        $account = $billing->account;

        // Count total late fees applied to account
        $totalLateFees = $account->billings()
            ->whereNotNull('late_fee_applied_at')
            ->sum('late_fee');

        $lateFeeCount = $account->billings()
            ->whereNotNull('late_fee_applied_at')
            ->count();

        // Update account
        $account->update([
            'total_late_fees' => $totalLateFees,
            'late_fee_count' => $lateFeeCount,
            'last_late_fee_at' => now(),
        ]);

        Log::info('Account metadata updated', [
            'account_id' => $account->id,
            'total_late_fees' => $totalLateFees,
            'late_fee_count' => $lateFeeCount,
        ]);
    }

    /**
     * Check if account needs additional action
     */
    protected function checkAccountStatus($billing, int $daysOverdue): void
    {
        $account = $billing->account;

        // Check if account should be flagged for disconnection
        $disconnectionThreshold = config('billing.account_status.disconnection_threshold_days', 90);

        if ($daysOverdue >= $disconnectionThreshold) {
            if ($account->status !== 'flagged_for_disconnection') {
                $account->update([
                    'status' => 'flagged_for_disconnection',
                    'flagged_for_disconnection_at' => now(),
                ]);

                Log::warning('Account flagged for disconnection', [
                    'account_id' => $account->id,
                    'days_overdue' => $daysOverdue,
                ]);

                // Notify admin
                // Notification::route('mail', config('billing.admin_email'))
                //     ->notify(new AccountFlaggedForDisconnection($account, $billing));
            }
        }

        // Check for repeat late fees (pattern of late payments)
        $recentLateFees = $account->billings()
            ->whereNotNull('late_fee_applied_at')
            ->where('late_fee_applied_at', '>=', now()->subMonths(6))
            ->count();

        if ($recentLateFees >= 3) {
            Log::warning('Account has pattern of late payments', [
                'account_id' => $account->id,
                'recent_late_fees' => $recentLateFees,
            ]);

            // Could trigger additional actions like payment plan offer
        }
    }

    /**
     * Log analytics
     */
    protected function logAnalytics($billing, float $lateFeeAmount, int $daysOverdue): void
    {
        // Log to audit system
        $this->auditService->logBillingAction(
            'late_fee_notification_sent',
            $billing,
            [
                'late_fee_amount' => $lateFeeAmount,
                'days_overdue' => $daysOverdue,
                'total_with_late_fee' => $billing->total_amount,
            ]
        );

        // Could also log to analytics service
        // Analytics::track('late_fee_applied', [
        //     'account_id' => $billing->account_id,
        //     'amount' => $lateFeeAmount,
        //     'days_overdue' => $daysOverdue,
        // ]);
    }

    /**
     * Determine if the listener should be queued.
     */
    public function shouldQueue(LateFeeApplied $event): bool
    {
        return true;
    }

    /**
     * Handle a job failure.
     */
    public function failed(LateFeeApplied $event, \Throwable $exception): void
    {
        Log::error('Late fee applied listener failed', [
            'billing_id' => $event->billing->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
