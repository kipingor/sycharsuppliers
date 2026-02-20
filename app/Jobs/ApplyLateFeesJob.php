<?php

namespace App\Jobs;

use App\Events\Billing\LateFeeApplied;
use App\Models\Billing;
use App\Services\Billing\ChargeCalculator;
use App\Services\Audit\AuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Apply Late Fees Job
 * 
 * Automatically applies late fees to overdue bills.
 * Runs daily to check for bills past grace period.
 * 
 * @package App\Jobs
 */
class ApplyLateFeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = 300; // 5 minutes

    /**
     * The maximum number of seconds the job can run.
     */
    public $timeout = 1800; // 30 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $billingId = null,
        public ?string $billingPeriod = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        ChargeCalculator $chargeCalculator,
        AuditService $auditService
    ): void {
        if (!config('billing.late_fees.enabled', true)) {
            Log::info('Late fees are disabled in configuration');
            return;
        }

        Log::info('Starting late fee application', [
            'billing_id' => $this->billingId,
            'billing_period' => $this->billingPeriod,
        ]);

        $stats = [
            'processed' => 0,
            'fees_applied' => 0,
            'skipped' => 0,
            'failed' => 0,
            'total_fees' => 0,
        ];

        try {
            $bills = $this->getEligibleBills();

            foreach ($bills as $bill) {
                $stats['processed']++;

                try {
                    $result = $this->applyLateFee($bill, $chargeCalculator, $auditService);
                    
                    if ($result['applied']) {
                        $stats['fees_applied']++;
                        $stats['total_fees'] += $result['fee_amount'];
                    } else {
                        $stats['skipped']++;
                    }
                } catch (\Exception $e) {
                    $stats['failed']++;
                    
                    Log::error('Failed to apply late fee to bill', [
                        'billing_id' => $bill->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Late fee application completed', $stats);
        } catch (\Exception $e) {
            Log::error('Late fee job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get bills eligible for late fees
     */
    protected function getEligibleBills(): \Illuminate\Database\Eloquent\Collection
    {
        $query = Billing::whereIn('status', ['pending', 'partially_paid', 'overdue'])
            ->where('due_date', '<', now());

        // Apply filters if specified
        if ($this->billingId) {
            $query->where('id', $this->billingId);
        }

        if ($this->billingPeriod) {
            $query->where('billing_period', $this->billingPeriod);
        }

        return $query->with('account')->get();
    }

    /**
     * Apply late fee to a bill
     */
    protected function applyLateFee(
        Billing $bill,
        ChargeCalculator $chargeCalculator,
        AuditService $auditService
    ): array {
        $daysOverdue = $bill->getDaysOverdue();
        $gracePeriod = config('billing.late_fees.grace_period_days', 14);

        // Check if bill is past grace period
        if ($daysOverdue <= $gracePeriod) {
            return [
                'applied' => false,
                'reason' => 'Within grace period',
            ];
        }

        // Check if late fee already applied
        if ($bill->late_fee_applied_at) {
            return [
                'applied' => false,
                'reason' => 'Late fee already applied',
            ];
        }

        // Calculate late fee
        $lateFee = $chargeCalculator->calculateLateFee($bill->total_amount, $daysOverdue);

        if ($lateFee <= 0) {
            return [
                'applied' => false,
                'reason' => 'Late fee amount is zero',
            ];
        }

        DB::beginTransaction();
        try {
            // Update bill with late fee
            $bill->update([
                'late_fee' => $lateFee,
                'late_fee_applied_at' => now(),
                'total_amount' => $bill->total_amount + $lateFee,
                'status' => 'overdue', // Ensure status is overdue
            ]);

            // Log audit
            $auditService->logBillingAction(
                'late_fee_applied',
                $bill,
                [
                    'late_fee' => $lateFee,
                    'days_overdue' => $daysOverdue,
                    'grace_period' => $gracePeriod,
                ]
            );

            // Dispatch event
            event(new LateFeeApplied($bill, $lateFee, $daysOverdue));

            DB::commit();

            Log::info('Late fee applied to bill', [
                'billing_id' => $bill->id,
                'account_id' => $bill->account_id,
                'late_fee' => $lateFee,
                'days_overdue' => $daysOverdue,
            ]);

            return [
                'applied' => true,
                'fee_amount' => $lateFee,
                'days_overdue' => $daysOverdue,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['late-fees', 'billing', 'period:' . ($this->billingPeriod ?? 'all')];
    }
}