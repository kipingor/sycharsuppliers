<?php

namespace App\Console\Commands;

use App\Jobs\GenerateBillJob;
use App\Models\Account;
use App\Services\Billing\BillingOrchestrator;
use App\Services\Billing\BillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Generate Monthly Bills Command (REFACTORED)
 * 
 * Follows thin command pattern:
 * - Input parsing
 * - Authorization (implicit via console)
 * - Service delegation
 * - Output formatting
 * 
 * NO BUSINESS LOGIC ALLOWED
 * All duplicate checking, validation, and generation handled by services
 * 
 * Usage:
 *   php artisan billing:generate
 *   php artisan billing:generate --period=2025-01
 *   php artisan billing:generate --account=123
 *   php artisan billing:generate --dry-run
 * 
 * @package App\Console\Commands
 */
class GenerateMonthlyBills extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'billing:generate
                            {--period= : Billing period (YYYY-MM). Defaults to current month}
                            {--account= : Generate for specific account ID only}
                            {--status=active : Account status filter (active|all)}
                            {--dry-run : Show what would be generated without actually generating}
                            {--queue : Dispatch jobs to queue instead of processing immediately}
                            {--force : Force regenerate even if bills already exist}';

    /**
     * The console command description.
     */
    protected $description = 'Generate monthly bills for all active accounts';

    /**
     * Execute the console command.
     */
    public function handle(
        BillingService $billingService,
        BillingOrchestrator $billingOrchestrator
    ): int {
        $period = $this->option('period') ?? now()->format('Y-m');
        $accountId = $this->option('account');
        $status = $this->option('status');
        $dryRun = $this->option('dry-run');
        $useQueue = $this->option('queue');
        $force = $this->option('force');

        $this->info("🔄 Starting bill generation for period: {$period}");
        $this->newLine();

        // Validate period format
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error('Invalid period format. Use YYYY-MM (e.g., 2025-01)');
            return self::FAILURE;
        }

        // Build query
        $query = Account::query();

        if ($accountId) {
            $query->where('id', $accountId);
            $this->info("📌 Filtering: Account ID {$accountId}");
        }

        if ($status === 'active') {
            $query->where('status', 'active');
            $this->info("📌 Filtering: Active accounts only");
        }

        // Get accounts
        $accounts = $query->with('meters')->get();

        if ($accounts->isEmpty()) {
            $this->warn('No accounts found matching criteria');
            return self::SUCCESS;
        }

        $this->info("📊 Found {$accounts->count()} account(s) to process");
        $this->newLine();

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No bills will be generated');
            $this->newLine();
        }

        // Show summary table
        $this->showAccountSummary($accounts, $period, $force);

        // Confirm if not dry-run
        if (!$dryRun && !$this->confirm('Do you want to proceed with bill generation?', true)) {
            $this->info('Operation cancelled');
            return self::SUCCESS;
        }

        // Process accounts
        $progressBar = $this->output->createProgressBar($accounts->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

        $successCount = 0;
        $skippedCount = 0;
        $failureCount = 0;
        $errors = [];

        foreach ($accounts as $account) {
            $progressBar->setMessage("Processing {$account->account_number}...");
            $progressBar->advance();

            if ($dryRun) {
                $skippedCount++;
                continue;
            }

            try {
                // ALL BUSINESS LOGIC DELEGATED TO SERVICE
                // Service handles:
                // - Active meter validation
                // - Duplicate checking
                // - Transaction wrapping
                // - Billing generation
                
                if ($useQueue) {
                    GenerateBillJob::dispatch($account, $period, $force);
                    $successCount++;
                } else {
                    // Service handles duplicate checking internally
                    try {
                        $billingService->generateForAccount($account, $period);
                        $successCount++;
                    } catch (\InvalidArgumentException $e) {
                        // Expected errors (no meters, duplicate, etc.)
                        $skippedCount++;
                        $errors[] = "Account {$account->account_number}: {$e->getMessage()}";
                    }
                }
            } catch (\Exception $e) {
                $failureCount++;
                $errors[] = "Account {$account->account_number}: {$e->getMessage()}";
                
                Log::error('Failed to generate bill via command', [
                    'account_id' => $account->id,
                    'period' => $period,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $progressBar->setMessage('Complete');
        $progressBar->finish();
        $this->newLine(2);

        // Show results
        $this->showResults($successCount, $skippedCount, $failureCount, $errors, $dryRun, $useQueue);

        return $failureCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Show account summary table
     * 
     * PRESENTATION LOGIC ONLY - No business decisions
     */
    protected function showAccountSummary($accounts, string $period, bool $force): void
    {
        $rows = [];

        foreach ($accounts->take(10) as $account) {
            $meterCount = $account->meters()->active()->count();
            
            // NOTE: This query is for DISPLAY only, not business logic
            // Actual duplicate prevention happens in BillingService
            $hasExisting = !$force && \App\Models\Billing::where('account_id', $account->id)
                ->where('billing_period', $period)
                ->whereNotIn('status', ['voided'])
                ->exists();

            $rows[] = [
                $account->id,
                $account->account_number,
                $account->name,
                $meterCount,
                $account->status,
                $hasExisting ? 'Yes' : 'No',
            ];
        }

        if ($accounts->count() > 10) {
            $rows[] = ['...', '...', '...', '...', '...', '...'];
            $rows[] = ['', '', "({$accounts->count()} total accounts)", '', '', ''];
        }

        $this->table(
            ['ID', 'Account #', 'Name', 'Meters', 'Status', 'Has Bill'],
            $rows
        );

        $this->newLine();
    }

    /**
     * Show generation results
     * 
     * PRESENTATION LOGIC ONLY
     */
    protected function showResults(
        int $successCount,
        int $skippedCount,
        int $failureCount,
        array $errors,
        bool $dryRun,
        bool $useQueue
    ): void {
        $this->info('📈 Generation Summary:');
        $this->line("  ✅ Success: {$successCount}");
        $this->line("  ⏭️  Skipped: {$skippedCount}");
        $this->line("  ❌ Failed: {$failureCount}");
        $this->newLine();

        if ($dryRun) {
            $this->warn('Note: This was a dry run. No bills were actually generated.');
            $this->newLine();
        }

        if ($useQueue) {
            $this->info('✨ Bills are being generated in the queue. Check queue status with:');
            $this->line('   php artisan queue:work');
            $this->newLine();
        }

        if (!empty($errors) && ($failureCount > 0 || $skippedCount > 0)) {
            $this->error('Errors/Skips encountered:');
            foreach (array_slice($errors, 0, 10) as $error) {
                $this->line("  • {$error}");
            }

            if (count($errors) > 10) {
                $this->line("  • ... and " . (count($errors) - 10) . " more");
            }
            $this->newLine();
        }

        if ($skippedCount > 0 && !$dryRun) {
            $this->warn('Some accounts were skipped. Review the messages above for details.');
            $this->newLine();
        }
    }
}