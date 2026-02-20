<?php

namespace App\Events\Billing;

use App\Models\Billing;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Bill Overdue Event
 * 
 * Fired when a bill becomes overdue (past due date and unpaid).
 * Triggers notifications and late fee processing.
 * 
 * @package App\Events
 */
class BillOverdue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Billing $billing,
        public int $daysOverdue
    ) {}

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'billing:' . $this->billing->id,
            'account:' . $this->billing->account_id,
            'overdue',
        ];
    }
}
