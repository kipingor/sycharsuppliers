<?php

namespace App\Console\Commands;

use App\Jobs\ReconcilePaymentsJob;
use App\Models\Payment;
use App\Services\Billing\PaymentReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Reconcile Payments Command
 * 
 * CLI command to reconcile unreconciled payments.
 * Can process all pending payments or filter by account.
 * 
 * Usage:
 *   php artisan reconciliation:process
 *   php artisan reconciliation:process --account=123
 *   php artisan reconciliation:process --limit=50
 *   php artisan reconciliation:process --dry-run
 * 
 * @package App\Console\Commands
 */
class ReconcilePaymentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reconciliation:process
                            {--account= : Process payments for specific account ID only}
                            {--limit= : Limit number of payments to process}
                            {--payment= : Process specific payment ID only}
                            {--dry-run : Show what would be reconciled without actually processing}
                            {--queue : Dispatch jobs to queue instead of processing immediately}
                            {--force : Force reconciliation even if already reconciled}';

    /**
     * The console command description.
     */
    protected $description = 'Reconcile unreconciled payments to outstanding bills';

    /**
     * Execute the console command.
     */
    public function handle(PaymentReconciliationService $reconciliationService): int
    {
        $accountId = $this->option('account');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $paymentId = $this->option('payment');
        $dryRun = $this->option('dry-run');
        $useQueue = $this->option('queue');
        $force = $this->option('force');

        $this->info('🔄 Starting payment reconciliation');
        $this->newLine();

        // Build query
        $query = Payment::where('status', 'completed');

        if (!$force) {
            $query->where('reconciliation_status', 'pending');
        }

        if ($paymentId) {
            $query->where('id', $paymentId);
            $this->info("📌 Processing: Payment ID {$paymentId}");
        } elseif ($accountId) {
            $query->where('account_id', $accountId);
            $this->info("📌 Filtering: Account ID {$accountId}");
        }

        if ($limit) {
            $query->limit($limit);
            $this->info("📌 Limit: {$limit} payments");
        }

        $query->orderBy('payment_date', 'asc');

        // Get payments
        $payments = $query->with('account')->get();

        if ($payments->isEmpty()) {
            $this->warn('No unreconciled payments found');
            return self::SUCCESS;
        }

        $this->info("📊 Found {$payments->count()} payment(s) to process");
        $this->newLine();

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No reconciliation will be performed');
            $this->newLine();
        }

        // Show payment summary
        $this->showPaymentSummary($payments);

        // Confirm if not dry-run
        if (!$dryRun && !$this->confirm('Do you want to proceed with reconciliation?', true)) {
            $this->info('Operation cancelled');
            return self::SUCCESS;
        }

        // Use queue if requested
        if ($useQueue && !$dryRun) {
            ReconcilePaymentsJob::dispatch($accountId, $limit);
            
            $this->info('✨ Reconciliation jobs dispatched to queue');
            $this->line('   Monitor progress with: php artisan queue:work');
            
            return self::SUCCESS;
        }

        // Process payments
        $progressBar = $this->output->createProgressBar($payments->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

        $successCount = 0;
        $partialCount = 0;
        $failureCount = 0;
        $results = [];

        foreach ($payments as $payment) {
            $progressBar->setMessage("Processing payment #{$payment->id}...");
            $progressBar->advance();

            if ($dryRun) {
                $results[] = $this->simulateReconciliation($payment);
                continue;
            }

            try {
                $result = $reconciliationService->reconcilePayment($payment);

                if ($result->reconciliationStatus === 'reconciled') {
                    $successCount++;
                } elseif ($result->reconciliationStatus === 'partially_reconciled') {
                    $partialCount++;
                }

                $results[] = [
                    'payment_id' => $payment->id,
                    'account' => $payment->account->account_number,
                    'amount' => $payment->amount,
                    'allocated' => $result->allocatedAmount,
                    'remaining' => $result->remainingAmount,
                    'status' => $result->reconciliationStatus,
                    'bills_paid' => count($result->allocations),
                ];
            } catch (\Exception $e) {
                $failureCount++;
                
                $results[] = [
                    'payment_id' => $payment->id,
                    'account' => $payment->account->account_number,
                    'amount' => $payment->amount,
                    'allocated' => 0,
                    'remaining' => $payment->amount,
                    'status' => 'failed',
                    'bills_paid' => 0,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to reconcile payment via command', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Small delay to prevent database overload
            usleep(50000); // 0.05 seconds
        }

        $progressBar->setMessage('Complete');
        $progressBar->finish();
        $this->newLine(2);

        // Show results
        $this->showResults($successCount, $partialCount, $failureCount, $results, $dryRun);

        return $failureCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Show payment summary table
     */
    protected function showPaymentSummary($payments): void
    {
        $rows = [];

        foreach ($payments->take(10) as $payment) {
            $rows[] = [
                $payment->id,
                $payment->payment_date->format('Y-m-d'),
                $payment->account->account_number,
                number_format($payment->amount, 2),
                $payment->method,
                $payment->reconciliation_status,
            ];
        }

        if ($payments->count() > 10) {
            $rows[] = ['...', '...', '...', '...', '...', '...'];
            $rows[] = ['', '', "({$payments->count()} total payments)", '', '', ''];
        }

        $this->table(
            ['ID', 'Date', 'Account', 'Amount', 'Method', 'Status'],
            $rows
        );

        $this->newLine();
    }

    /**
     * Simulate reconciliation for dry-run
     */
    protected function simulateReconciliation(Payment $payment): array
    {
        $account = $payment->account;
        $outstandingBills = $account->getOutstandingBills();
        
        $allocated = 0;
        $billCount = 0;
        $remaining = $payment->amount;

        foreach ($outstandingBills as $bill) {
            if ($remaining <= 0) break;

            $billBalance = $bill->balance;
            $toAllocate = min($remaining, $billBalance);
            
            $allocated += $toAllocate;
            $remaining -= $toAllocate;
            $billCount++;
        }

        return [
            'payment_id' => $payment->id,
            'account' => $account->account_number,
            'amount' => $payment->amount,
            'allocated' => $allocated,
            'remaining' => $remaining,
            'status' => $remaining > 0 ? 'partially_reconciled' : 'reconciled',
            'bills_paid' => $billCount,
        ];
    }

    /**
     * Show reconciliation results
     */
    protected function showResults(
        int $successCount,
        int $partialCount,
        int $failureCount,
        array $results,
        bool $dryRun
    ): void {
        $this->info('📈 Reconciliation Summary:');
        $this->line("  ✅ Fully Reconciled: {$successCount}");
        $this->line("  ⚠️  Partially Reconciled: {$partialCount}");
        $this->line("  ❌ Failed: {$failureCount}");
        $this->newLine();

        if ($dryRun) {
            $this->warn('Note: This was a dry run. No reconciliation was performed.');
            $this->newLine();
        }

        // Show detailed results for first 10
        if (!empty($results)) {
            $this->info('Detailed Results:');
            $this->newLine();

            $rows = [];
            foreach (array_slice($results, 0, 10) as $result) {
                $statusIcon = match($result['status']) {
                    'reconciled' => '✅',
                    'partially_reconciled' => '⚠️',
                    'failed' => '❌',
                    default => '•',
                };

                $rows[] = [
                    $result['payment_id'],
                    $result['account'],
                    number_format($result['amount'], 2),
                    number_format($result['allocated'], 2),
                    number_format($result['remaining'], 2),
                    $result['bills_paid'],
                    $statusIcon . ' ' . $result['status'],
                ];
            }

            $this->table(
                ['ID', 'Account', 'Amount', 'Allocated', 'Remaining', 'Bills', 'Status'],
                $rows
            );

            if (count($results) > 10) {
                $this->line("... and " . (count($results) - 10) . " more");
                $this->newLine();
            }
        }

        // Show errors if any
        $errors = array_filter($results, fn($r) => isset($r['error']));
        if (!empty($errors)) {
            $this->error('Errors encountered:');
            foreach (array_slice($errors, 0, 5) as $error) {
                $this->line("  • Payment #{$error['payment_id']}: {$error['error']}");
            }
            if (count($errors) > 5) {
                $this->line("  • ... and " . (count($errors) - 5) . " more");
            }
            $this->newLine();
        }
    }
}
