<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\BillingMeterReadingDetail;
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
        'amount_due' => 'float',
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
        return $this->hasOne(BillingMeterReadingDetail::class);
    }

    /**
     * Get the latest meter reading for the meter.
     */
    public function currentReading(): Attribute
    {
        return Attribute::make(
            get: fn () => BillingMeterReadingDetail::where('billing_id', $this->id)->latest()->first()?->current_reading_value ?? 0
        );
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
