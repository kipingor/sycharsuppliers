<?php

namespace App\Jobs;

use App\Models\Account;
use App\Models\Billing;
use App\Services\Billing\BillingService;
use App\Services\Audit\AuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generate Bill Job
 * 
 * Handles asynchronous bill generation for a single account.
 * Includes retry logic, duplicate prevention, and comprehensive logging.
 * 
 * @package App\Jobs
 */
class GenerateBillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 120;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Account $account,
        public string $billingPeriod,
        public bool $forceRegenerate = false
    ) {
        $this->onQueue(config('billing.queue.queue_name', 'billing'));
    }

    /**
     * Execute the job.
     */
    public function handle(
        BillingService $billingService,
        AuditService $auditService
    ): void {
        Log::info('Generating bill for account', [
            'account_id' => $this->account->id,
            'account_number' => $this->account->account_number,
            'billing_period' => $this->billingPeriod,
            'force_regenerate' => $this->forceRegenerate,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Reload account to ensure we have latest data
            $this->account->refresh();

            // Check if account is active
            if (!$this->account->isActive()) {
                Log::warning('Skipping bill generation for inactive account', [
                    'account_id' => $this->account->id,
                    'status' => $this->account->status,
                ]);
                return;
            }

            // Check if account has active meters
            if ($this->account->meters()->active()->count() === 0) {
                Log::warning('Skipping bill generation - no active meters', [
                    'account_id' => $this->account->id,
                ]);
                return;
            }

            // Check for duplicate bill
            if (!$this->forceRegenerate && config('billing.generation.prevent_duplicates', true)) {
                $existingBill = Billing::where('account_id', $this->account->id)
                    ->where('billing_period', $this->billingPeriod)
                    ->whereNotIn('status', ['voided'])
                    ->first();

                if ($existingBill) {
                    Log::info('Bill already exists for account and period', [
                        'account_id' => $this->account->id,
                        'billing_id' => $existingBill->id,
                        'billing_period' => $this->billingPeriod,
                    ]);
                    return;
                }
            }

            // Generate the bill
            $billing = $billingService->generateForAccount(
                $this->account,
                $this->billingPeriod
            );

            Log::info('Bill generated successfully', [
                'account_id' => $this->account->id,
                'billing_id' => $billing->id,
                'billing_period' => $this->billingPeriod,
                'total_amount' => $billing->total_amount,
                'detail_count' => $billing->details()->count(),
            ]);

            // Log audit
            $auditService->logBillingAction(
                'generated_async',
                $billing,
                [
                    'job_id' => $this->job->getJobId(),
                    'force_regenerate' => $this->forceRegenerate,
                ]
            );

            // Dispatch follow-up jobs if configured
            if (config('billing.statements.auto_send', true)) {
                // You would dispatch a SendStatementJob here
                Log::info('Auto-send statement is enabled, would dispatch SendStatementJob');
            }
        } catch (\Exception $e) {
            Log::error('Failed to generate bill', [
                'account_id' => $this->account->id,
                'billing_period' => $this->billingPeriod,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts(),
            ]);

            // Log audit for failure
            $auditService->logBillingAction(
                'generation_failed',
                null,
                [
                    'account_id' => $this->account->id,
                    'billing_period' => $this->billingPeriod,
                    'error' => $e->getMessage(),
                    'attempt' => $this->attempts(),
                    'job_id' => $this->job->getJobId(),
                ]
            );

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Bill generation job failed permanently', [
            'account_id' => $this->account->id,
            'account_number' => $this->account->account_number,
            'billing_period' => $this->billingPeriod,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        /**
         * TODO: You might want to:
         * 1. Send notification to admins
         * 2. Mark account for manual review
         * 3. Create a failed bill generation record
         */
    }

    /**
     * Get tags for job monitoring
     */
    public function tags(): array
    {
        return [
            'account:' . $this->account->id,
            'period:' . $this->billingPeriod,
            'bill-generation',
        ];
    }
}
