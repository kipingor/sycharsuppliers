<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\BillingDetail;
use App\Casts\MoneyCast;
use Carbon\Carbon;

class Billing extends Model
{
    use HasFactory;

    protected $fillable = [
        'meter_id',
        'amount_due',
        'status',
    ];


    protected $casts = [
        // 'amount_due' => 'float',
        'amount_due' => MoneyCast::class,
    ];

    protected $appends = [
        'current_reading', 
        'previous_reading'
    ];

    /**
     * Get the Meter associated with the bill.
     */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function details()
    {
        return $this->hasOne(BillingDetail::class, 'billing_id');
    }

    /**
     * Get the latest meter reading for the meter.
     */
    public function getCurrentReadingAttribute()
    {
        return $this->details?->current_reading_value ?? 0;
    }

    /**
     * Get the latest meter reading for the meter.
     */
    public function getPreviousReadingAttribute()
    {
        return $this->details?->previous_reading_value ?? 0;
    }

    /**
     * Check if the bill is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if the bill is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'pending' && Carbon::parse($this->billing_period)->addDays(30)->isPast();
    }

    /**
     * Mark the bill as paid.
     */
    public function markAsPaid(): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    /**
     * Apply a late fee to an overdue bill.
     */
    public function applyLateFee(float $percentage = 5): void
    {
        if ($this->isOverdue()) {
            $lateFee = ($this->amount_due * $percentage) / 100;
            $this->increment('amount_due', $lateFee);
        }
    }
}
