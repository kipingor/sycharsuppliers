<?php

namespace App\Events\Billing;

use App\Models\Billing;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Bill Generated Event
 * 
 * Fired when a bill is successfully generated for an account.
 * Triggers follow-up actions like sending statements.
 * 
 * @package App\Events
 */
class BillGenerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Billing $billing
    ) {}

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'billing:' . $this->billing->id,
            'account:' . $this->billing->account_id,
            'period:' . $this->billing->billing_period,
            'bill-generated',
        ];
    }
}
