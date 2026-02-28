<?php

namespace App\Jobs;

use App\Models\Billing;
use App\Services\Audit\AuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Send Statement Job
 * 
 * Sends billing statement to account holder via email.
 * Includes PDF attachment and account summary.
 * 
 * @package App\Jobs
 */
class SendStatementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = 600; // 10 minutes

    /**
     * The maximum number of seconds the job can run.
     */
    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Billing $billing,
        public bool $isReminder = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AuditService $auditService): void
    {
        Log::info('Sending billing statement', [
            'billing_id' => $this->billing->id,
            'account_id' => $this->billing->account_id,
            'is_reminder' => $this->isReminder,
        ]);

        $account = $this->billing->account;

        // Check if account has email
        if (!$account->email) {
            Log::warning('Account has no email address', [
                'billing_id' => $this->billing->id,
                'account_id' => $account->id,
            ]);
            return;
        }

        try {
            // Generate PDF statement
            $pdf = $this->generateStatementPdf();

            // Prepare email data
            $emailData = [
                'account' => $account,
                'billing' => $this->billing,
                'billing_period' => $this->billing->billing_period,
                'total_amount' => $this->billing->total_amount,
                'due_date' => $this->billing->due_date->format('F j, Y'),
                'is_reminder' => $this->isReminder,
                'is_overdue' => $this->billing->isOverdue(),
                'days_overdue' => $this->billing->getDaysOverdue(),
            ];

            // Send email based on type
            if ($this->isReminder) {
                Mail::to($account->email)
                    ->send(new \App\Mail\BillingReminderMail($emailData, $pdf));
            } else {
                Mail::to($account->email)
                    ->send(new \App\Mail\BillingStatement($emailData, $pdf));
            }

            // Update billing record
            $this->billing->update([
                'statement_sent_at' => now(),
            ]);

            // Log audit
            $auditService->logBillingAction(
                $this->isReminder ? 'reminder_sent' : 'statement_sent',
                $this->billing,
                [
                    'recipient' => $account->email,
                    'sent_at' => now()->toDateTimeString(),
                ]
            );

            Log::info('Statement sent successfully', [
                'billing_id' => $this->billing->id,
                'account_email' => $account->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send statement', [
                'billing_id' => $this->billing->id,
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate PDF statement
     */
    protected function generateStatementPdf(): string
    {
        // Load billing with all relations
        $this->billing->load([
            'account',
            'details.meter',
            'payments.allocations',
        ]);

        // Generate PDF using a PDF library (e.g., DomPDF, TCPDF)
        // This is a placeholder - actual implementation would use a PDF library
        $pdf = Pdf::loadView('statements.billing', [
            'billing' => $this->billing,
            'account' => $this->billing->account,
            'details' => $this->billing->details,
            'payments' => $this->billing->payments,
        ]);

        return $pdf->output();
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'statements',
            'billing',
            'account:' . $this->billing->account_id,
            'period:' . $this->billing->billing_period,
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Statement sending job failed permanently', [
            'billing_id' => $this->billing->id,
            'account_id' => $this->billing->account_id,
            'error' => $exception->getMessage(),
        ]);

        // Optionally notify admin of failure
        // Mail::to(config('billing.admin_email'))->send(new StatementFailedMail(...));
    }
}