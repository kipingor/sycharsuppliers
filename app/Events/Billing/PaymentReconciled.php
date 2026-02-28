<?php

namespace App\Events\Billing;

use App\DTOs\ReconciliationResult;
use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Payment Reconciled Event
 * 
 * Fired when a payment is successfully reconciled to bills.
 * Contains the payment and detailed reconciliation results.
 * 
 * @package App\Events
 */
class PaymentReconciled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Payment $payment,
        public ReconciliationResult $result
    ) {}

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'payment:' . $this->payment->id,
            'account:' . $this->payment->account_id,
            'reconciliation',
        ];
    }
}
