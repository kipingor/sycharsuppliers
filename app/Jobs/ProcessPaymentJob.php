<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Billing\PaymentReconciliationService;
use App\Services\Audit\AuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Process Payment Job
 * 
 * Handles asynchronous payment processing and reconciliation.
 * Includes retry logic and comprehensive logging.
 * 
 * @package App\Jobs
 */
class ProcessPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Payment $payment,
        public bool $autoReconcile = true
    ) {
        $this->onQueue(config('billing.queue.queue_name', 'billing'));
    }

    /**
     * Execute the job.
     */
    public function handle(
        PaymentReconciliationService $reconciliationService,
        AuditService $auditService
    ): void {
        Log::info('Processing payment', [
            'payment_id' => $this->payment->id,
            'account_id' => $this->payment->account_id,
            'amount' => $this->payment->amount,
            'auto_reconcile' => $this->autoReconcile,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Reload payment to ensure we have latest data
            $this->payment->refresh();

            // Skip if already processed
            if ($this->payment->isReconciled()) {
                Log::info('Payment already reconciled, skipping', [
                    'payment_id' => $this->payment->id,
                ]);
                return;
            }

            // Skip if payment is not completed
            if (!$this->payment->isCompleted()) {
                Log::warning('Payment not in completed status, skipping reconciliation', [
                    'payment_id' => $this->payment->id,
                    'status' => $this->payment->status,
                ]);
                return;
            }

            // Perform auto-reconciliation if enabled
            if ($this->autoReconcile) {
                $result = $reconciliationService->reconcilePayment($this->payment);

                Log::info('Payment reconciled successfully', [
                    'payment_id' => $this->payment->id,
                    'reconciliation_status' => $result->reconciliationStatus,
                    'allocated_amount' => $result->allocatedAmount,
                    'remaining_amount' => $result->remainingAmount,
                    'bills_paid' => count($result->allocations),
                ]);

                // Log audit
                $auditService->logPaymentAction(
                    'auto_reconciled',
                    $this->payment,
                    [
                        'reconciliation_result' => $result->getSummary(),
                        'job_id' => $this->job->getJobId(),
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to process payment', [
                'payment_id' => $this->payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts(),
            ]);

            // Log audit for failure
            $auditService->logPaymentAction(
                'processing_failed',
                $this->payment,
                [
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
        Log::critical('Payment processing job failed permanently', [
            'payment_id' => $this->payment->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Update payment status or send notification
        // You might want to notify admins here
    }

    /**
     * Get tags for job monitoring
     */
    public function tags(): array
    {
        return [
            'payment:' . $this->payment->id,
            'account:' . $this->payment->account_id,
            'payment-processing',
        ];
    }
}