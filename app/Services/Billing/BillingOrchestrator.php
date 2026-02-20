<?php

namespace App\Services\Billing;

use App\Events\Billing\BillGenerated;
use App\Jobs\GenerateBillJob;
use App\Models\Account;
use App\Models\Billing;
use App\Services\Audit\AuditService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Billing Orchestrator Service
 * 
 * Coordinates complex billing operations including:
 * - Bulk bill generation for multiple accounts
 * - Billing cycle management
 * - Bill regeneration and voiding
 * - Coordinated bulk meter billing
 * 
 * @package App\Services\Billing
 */
class BillingOrchestrator
{
    public function __construct(
        protected BillingService $billingService,
        protected BulkMeterService $bulkMeterService,
        protected AuditService $auditService
    ) {}

    /**
     * Generate bills for all active accounts
     * 
     * @param string $billingPeriod Format: Y-m
     * @param bool $useQueue Whether to use queue for generation
     * @return array Statistics about generation
     */
    public function generateForAllAccounts(string $billingPeriod, bool $useQueue = true): array
    {
        Log::info('Starting bulk bill generation for all accounts', [
            'billing_period' => $billingPeriod,
            'use_queue' => $useQueue,
        ]);

        // Get all active accounts
        $accounts = Account::active()
            ->whereHas('meters', function ($query) {
                $query->where('status', 'active');
            })
            ->get();

        if ($accounts->isEmpty()) {
            Log::warning('No active accounts with meters found for billing');
            return [
                'total_accounts' => 0,
                'queued' => 0,
                'generated' => 0,
                'skipped' => 0,
                'failed' => 0,
            ];
        }

        $stats = [
            'total_accounts' => $accounts->count(),
            'queued' => 0,
            'generated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($accounts as $account) {
            try {
                // Check if bill already exists
                $existingBill = Billing::where('account_id', $account->id)
                    ->where('billing_period', $billingPeriod)
                    ->whereNotIn('status', ['voided'])
                    ->first();

                if ($existingBill) {
                    $stats['skipped']++;
                    continue;
                }

                if ($useQueue) {
                    // Dispatch to queue
                    GenerateBillJob::dispatch($account, $billingPeriod);
                    $stats['queued']++;
                } else {
                    // Generate immediately
                    $this->billingService->generateForAccount($account, $billingPeriod);
                    $stats['generated']++;
                }
            } catch (\Exception $e) {
                $stats['failed']++;
                $stats['errors'][] = [
                    'account_id' => $account->id,
                    'account_number' => $account->account_number,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to generate bill for account', [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Log audit
        $this->auditService->logBillingAction(
            'bulk_generation_completed',
            null,
            [
                'billing_period' => $billingPeriod,
                'statistics' => $stats,
            ]
        );

        Log::info('Bulk bill generation completed', $stats);

        return $stats;
    }

    /**
     * Generate bills for specific accounts
     * 
     * @param array|Collection $accountIds
     * @param string $billingPeriod
     * @param bool $useQueue
     * @return array
     */
    public function generateForAccounts($accountIds, string $billingPeriod, bool $useQueue = true): array
    {
        $accountIds = $accountIds instanceof Collection 
            ? $accountIds->toArray() 
            : $accountIds;

        $accounts = Account::whereIn('id', $accountIds)
            ->active()
            ->get();

        $stats = [
            'total_accounts' => $accounts->count(),
            'queued' => 0,
            'generated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($accounts as $account) {
            try {
                if ($useQueue) {
                    GenerateBillJob::dispatch($account, $billingPeriod);
                    $stats['queued']++;
                } else {
                    $this->billingService->generateForAccount($account, $billingPeriod);
                    $stats['generated']++;
                }
            } catch (\Exception $e) {
                $stats['failed']++;
                $stats['errors'][] = [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $stats;
    }

    /**
     * Void a bill and optionally regenerate
     * 
     * @param Billing $billing
     * @param string $reason
     * @param bool $regenerate
     * @return Billing|null New billing if regenerated
     */
    public function voidAndRegenerate(Billing $billing, string $reason, bool $regenerate = true): ?Billing
    {
        if (!$billing->canBeModified()) {
            throw new \InvalidArgumentException('Bill cannot be modified in current status');
        }

        DB::beginTransaction();
        try {
            // Void the bill
            $billing->update([
                'status' => 'voided',
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            // Log audit
            $this->auditService->logBillingAction(
                'voided',
                $billing,
                ['reason' => $reason, 'will_regenerate' => $regenerate]
            );

            $newBilling = null;

            if ($regenerate) {
                // Regenerate bill
                $newBilling = $this->billingService->generateForAccount(
                    $billing->account,
                    $billing->billing_period
                );

                // Link to original
                $newBilling->update(['replaced_billing_id' => $billing->id]);

                Log::info('Bill voided and regenerated', [
                    'original_billing_id' => $billing->id,
                    'new_billing_id' => $newBilling->id,
                ]);
            }

            DB::commit();

            return $newBilling;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Regenerate bills for a period
     * 
     * @param string $billingPeriod
     * @param array $accountIds Optional specific accounts
     * @param bool $forceRegenerate Force even if bills exist
     * @return array
     */
    public function regenerateForPeriod(
        string $billingPeriod, 
        array $accountIds = [], 
        bool $forceRegenerate = false
    ): array {
        $query = Billing::where('billing_period', $billingPeriod);

        if (!empty($accountIds)) {
            $query->whereIn('account_id', $accountIds);
        }

        $existingBills = $query->get();

        $stats = [
            'total_bills' => $existingBills->count(),
            'voided' => 0,
            'regenerated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($existingBills as $bill) {
            try {
                if ($forceRegenerate || $bill->canBeModified()) {
                    $this->voidAndRegenerate($bill, 'Regeneration requested', true);
                    $stats['voided']++;
                    $stats['regenerated']++;
                }
            } catch (\Exception $e) {
                $stats['failed']++;
                $stats['errors'][] = [
                    'billing_id' => $bill->id,
                    'account_id' => $bill->account_id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $stats;
    }

    /**
     * Process all bulk meters for a period
     * 
     * @param string $billingPeriod
     * @return array
     */
    public function processBulkMeters(string $billingPeriod): array
    {
        $bulkMeters = \App\Models\Meter::bulk()
            ->active()
            ->get();

        $stats = [
            'total_bulk_meters' => $bulkMeters->count(),
            'processed' => 0,
            'sub_bills_generated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($bulkMeters as $bulkMeter) {
            try {
                $result = $this->bulkMeterService->generateBulkMeterBill(
                    $bulkMeter, 
                    $billingPeriod
                );

                $stats['processed']++;
                $stats['sub_bills_generated'] += count($result['sub_bills']);
            } catch (\Exception $e) {
                $stats['failed']++;
                $stats['errors'][] = [
                    'meter_id' => $bulkMeter->id,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to process bulk meter', [
                    'meter_id' => $bulkMeter->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Get billing statistics for a period
     * 
     * @param string $billingPeriod
     * @return array
     */
    public function getPeriodStatistics(string $billingPeriod): array
    {
        $bills = Billing::where('billing_period', $billingPeriod)->get();

        $stats = [
            'period' => $billingPeriod,
            'total_bills' => $bills->count(),
            'total_amount' => $bills->sum('total_amount'),
            'total_paid' => $bills->sum('paid_amount'),
            'outstanding_balance' => $bills->sum(fn($b) => $b->balance),
            'by_status' => [],
            'payment_rate' => 0,
        ];

        // Group by status
        $byStatus = $bills->groupBy('status');
        foreach ($byStatus as $status => $statusBills) {
            $stats['by_status'][$status] = [
                'count' => $statusBills->count(),
                'total_amount' => $statusBills->sum('total_amount'),
                'total_paid' => $statusBills->sum('paid_amount'),
            ];
        }

        // Calculate payment rate
        if ($stats['total_amount'] > 0) {
            $stats['payment_rate'] = round(
                ($stats['total_paid'] / $stats['total_amount']) * 100, 
                2
            );
        }

        return $stats;
    }

    /**
     * Get accounts without bills for a period
     * 
     * @param string $billingPeriod
     * @return Collection
     */
    public function getAccountsWithoutBills(string $billingPeriod): Collection
    {
        return Account::active()
            ->whereHas('meters', function ($query) {
                $query->where('status', 'active');
            })
            ->whereDoesntHave('billings', function ($query) use ($billingPeriod) {
                $query->where('billing_period', $billingPeriod)
                    ->whereNotIn('status', ['voided']);
            })
            ->get();
    }

    /**
     * Schedule bill generation for upcoming period
     * 
     * @param Carbon|null $periodDate
     * @return array
     */
    public function scheduleUpcomingPeriod(?Carbon $periodDate = null): array
    {
        $periodDate = $periodDate ?? now()->addMonth();
        $billingPeriod = $periodDate->format('Y-m');

        // Check if bills already exist for this period
        $existingCount = Billing::where('billing_period', $billingPeriod)->count();

        if ($existingCount > 0) {
            return [
                'scheduled' => false,
                'reason' => 'Bills already exist for this period',
                'existing_count' => $existingCount,
            ];
        }

        // Get accounts that will need bills
        $accounts = Account::active()
            ->whereHas('meters', function ($query) {
                $query->where('status', 'active');
            })
            ->get();

        // Dispatch jobs for each account
        foreach ($accounts as $account) {
            GenerateBillJob::dispatch($account, $billingPeriod)
                ->delay($periodDate->startOfMonth());
        }

        return [
            'scheduled' => true,
            'billing_period' => $billingPeriod,
            'account_count' => $accounts->count(),
            'scheduled_for' => $periodDate->startOfMonth()->toDateTimeString(),
        ];
    }

    /**
     * Cleanup voided bills older than specified months
     * 
     * @param int $months
     * @return int Count of deleted bills
     */
    public function cleanupVoidedBills(int $months = 12): int
    {
        $cutoffDate = now()->subMonths($months);

        $count = Billing::where('status', 'voided')
            ->where('voided_at', '<', $cutoffDate)
            ->delete();

        Log::info('Voided bills cleanup completed', [
            'cutoff_date' => $cutoffDate->toDateString(),
            'deleted_count' => $count,
        ]);

        return $count;
    }
}