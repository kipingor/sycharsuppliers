<?php

namespace App\Events\Billing;

use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Payment Received Event
 * 
 * Fired when a payment is created/received.
 * Triggers reconciliation and notification processes.
 * 
 * @package App\Events
 */
class PaymentReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Payment $payment
    ) {}

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'payment:' . $this->payment->id,
            'account:' . $this->payment->account_id,
            'payment-received',
        ];
    }
}
