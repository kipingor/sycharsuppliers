<?php

namespace App\Events\Billing;

use App\Models\Billing;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Late Fee Applied Event
 * 
 * Fired when a late fee is applied to an overdue bill.
 * 
 * @package App\Events
 */
class LateFeeApplied
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Billing $billing,
        public float $lateFeeAmount,
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
            'late-fee',
        ];
    }
}
