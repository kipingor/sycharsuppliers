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
 * Reconcile Payments Job
 * 
 * Processes unreconciled payments in batches.
 * Can be scheduled to run periodically.
 * 
 * @package App\Jobs
 */
class ReconcilePaymentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 1800; // 30 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $accountId = null,
        public ?int $limit = null
    ) {
        $this->onQueue(config('reconciliation.queue.queue_name', 'reconciliation'));
    }

    /**
     * Execute the job.
     */
    public function handle(
        PaymentReconciliationService $reconciliationService,
        AuditService $auditService
    ): void {
        Log::info('Starting bulk payment reconciliation', [
            'account_id' => $this->accountId,
            'limit' => $this->limit,
        ]);

        $batchSize = config('reconciliation.performance.batch_size', 100);
        $limit = $this->limit ?? $batchSize;

        try {
            // Get unreconciled payments
            $query = Payment::where('status', 'completed')
                ->where('reconciliation_status', 'pending')
                ->orderBy('payment_date', 'asc');

            if ($this->accountId) {
                $query->where('account_id', $this->accountId);
            }

            $payments = $query->limit($limit)->get();

            if ($payments->isEmpty()) {
                Log::info('No unreconciled payments found');
                return;
            }

            Log::info('Found unreconciled payments', [
                'count' => $payments->count(),
            ]);

            $successCount = 0;
            $failureCount = 0;
            $errors = [];

            foreach ($payments as $payment) {
                try {
                    $result = $reconciliationService->reconcilePayment($payment);

                    $successCount++;

                    Log::info('Payment reconciled', [
                        'payment_id' => $payment->id,
                        'account_id' => $payment->account_id,
                        'reconciliation_status' => $result->reconciliationStatus,
                        'allocated_amount' => $result->allocatedAmount,
                    ]);
                } catch (\Exception $e) {
                    $failureCount++;
                    $errors[] = [
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ];

                    Log::error('Failed to reconcile payment', [
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Small delay to prevent database overload
                usleep(100000); // 0.1 seconds
            }

            Log::info('Bulk payment reconciliation completed', [
                'total_processed' => $payments->count(),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
            ]);

            // Log audit summary
            $auditService->logPaymentAction(
                'bulk_reconciliation_completed',
                null,
                [
                    'total_processed' => $payments->count(),
                    'success_count' => $successCount,
                    'failure_count' => $failureCount,
                    'errors' => $errors,
                    'job_id' => $this->job?->getJobId(),
                ]
            );

            // If there are more unreconciled payments, dispatch another job
            if ($successCount > 0 && !$this->limit) {
                $remainingCount = Payment::where('status', 'completed')
                    ->where('reconciliation_status', 'pending')
                    ->when($this->accountId, fn($q) => $q->where('account_id', $this->accountId))
                    ->count();

                if ($remainingCount > 0) {
                    Log::info('Dispatching another reconciliation job', [
                        'remaining_count' => $remainingCount,
                    ]);

                    static::dispatch($this->accountId, $batchSize)
                        ->delay(now()->addMinutes(5));
                }
            }
        } catch (\Exception $e) {
            Log::error('Bulk payment reconciliation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Bulk payment reconciliation job failed', [
            'account_id' => $this->accountId,
            'error' => $exception->getMessage(),
        ]);

        // Notify admins about the failure
    }

    /**
     * Get tags for job monitoring
     */
    public function tags(): array
    {
        $tags = ['bulk-reconciliation', 'reconciliation'];

        if ($this->accountId) {
            $tags[] = 'account:' . $this->accountId;
        }

        return $tags;
    }
}