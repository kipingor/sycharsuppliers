<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * PaymentAllocation Model
 * 
 * Tracks how payments are allocated to specific bills.
 * This is the core model for payment reconciliation functionality.
 * 
 * @property int $id
 * @property int $payment_id
 * @property int $billing_id
 * @property float $allocated_amount
 * @property \Carbon\Carbon $allocation_date
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property-read Payment $payment
 * @property-read Billing $billing
 */
class PaymentAllocation extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'payment_id',
        'billing_id',
        'allocated_amount',
        'allocation_date',
        'notes',
    ];

    protected $casts = [
        'allocation_date' => 'datetime',
        'allocated_amount' => 'decimal:2',
    ];

    /**
     * Attributes to include in audit
     */
    protected $auditInclude = [
        'payment_id',
        'billing_id',
        'allocated_amount',
        'allocation_date',
    ];

    /**
     * Generate audit tags for easier filtering
     */
    public function generateTags(): array
    {
        return [
            'payment_allocation',
            'payment:' . $this->payment_id,
            'billing:' . $this->billing_id,
        ];
    }

    /* =========================
     | Relationships
     |========================= */

    /**
     * Get the payment this allocation belongs to
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the billing this allocation is for
     */
    public function billing(): BelongsTo
    {
        return $this->belongsTo(Billing::class);
    }

    /* =========================
     | Scopes
     |========================= */

    /**
     * Scope to get allocations for a specific payment
     */
    public function scopeForPayment($query, int $paymentId)
    {
        return $query->where('payment_id', $paymentId);
    }

    /**
     * Scope to get allocations for a specific billing
     */
    public function scopeForBilling($query, int $billingId)
    {
        return $query->where('billing_id', $billingId);
    }

    /**
     * Scope to get allocations for a date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('allocation_date', [$startDate, $endDate]);
    }

    /* =========================
     | Helper Methods
     |========================= */

    /**
     * Check if this allocation fully pays the bill
     */
    public function fullPaysBill(): bool
    {
        $totalAllocated = static::where('billing_id', $this->billing_id)->sum('allocated_amount');
        return $totalAllocated >= $this->billing->total_amount;
    }

    /**
     * Get the remaining balance on the bill after this allocation
     */
    public function getRemainingBillBalance(): float
    {
        $totalAllocated = static::where('billing_id', $this->billing_id)->sum('allocated_amount');
        return max(0, $this->billing->total_amount - $totalAllocated);
    }
}
