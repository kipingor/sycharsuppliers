<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;
use Carbon\Carbon;

/**
 * Payment Model
 * 
 * Represents a payment made by an account.
 * Supports reconciliation through PaymentAllocation relationships.
 * 
 * @property int $id
 * @property int $account_id
 * @property int|null $meter_id (deprecated - use allocations)
 * @property int|null $billing_id (deprecated - use allocations)
 * @property float $amount
 * @property \Carbon\Carbon $payment_date
 * @property string $method (Cash|Bank Transfer|M-Pesa|Card|Cheque)
 * @property string|null $reference (transaction reference)
 * @property string|null $transaction_id
 * @property string $status (pending|completed|failed|reversed)
 * @property string $reconciliation_status (pending|reconciled|partially_reconciled)
 * @property \Carbon\Carbon|null $reconciled_at
 * @property int|null $reconciled_by (user_id)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property-read Account $account
 * @property-read Meter|null $meter
 * @property-read Billing|null $bill
 * @property-read \Illuminate\Database\Eloquent\Collection|PaymentAllocation[] $allocations
 */
class Payment extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'account_id',
        'meter_id', // Deprecated but kept for backward compatibility
        'billing_id', // Deprecated but kept for backward compatibility
        'amount',
        'payment_date',
        'method',
        'reference',
        'transaction_id',
        'status',
        'reconciliation_status',
        'reconciled_at',
        'reconciled_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => MoneyCast::class,
        'reconciled_at' => 'datetime',
    ];

    /**
     * Attributes to include in audit
     */
    protected $auditInclude = [
        'account_id',
        'amount',
        'payment_date',
        'method',
        'reference',
        'status',
        'reconciliation_status',
        'reconciled_at',
        'reconciled_by',
    ];

    /**
     * Generate audit tags
     */
    public function generateTags(): array
    {
        return [
            'payment',
            'account:' . $this->account_id,
            'method:' . $this->method,
            'status:' . $this->status,
            'reconciliation:' . $this->reconciliation_status,
        ];
    }

    /* =========================
     | Relationships
     |========================= */

    /**
     * Get the account this payment belongs to
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the meter (deprecated - for backward compatibility)
     */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    /**
     * Get the bill (deprecated - for backward compatibility)
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Billing::class, 'billing_id');
    }

    /**
     * Get all allocations for this payment
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /* =========================
     | Scopes
     |========================= */

    /**
     * Scope to get completed payments
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get reconciled payments
     */
    public function scopeReconciled($query)
    {
        return $query->where('reconciliation_status', 'reconciled');
    }

    /**
     * Scope to get unreconciled payments
     */
    public function scopeUnreconciled($query)
    {
        return $query->where('reconciliation_status', 'pending');
    }

    /**
     * Scope to get payments for a specific account
     */
    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope to get payments by method
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('method', $method);
    }

    /**
     * Scope to get payments in date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    /* =========================
     | Accessor Methods
     |========================= */

    /**
     * Get total allocated amount
     * 
     * @return float
     */
    public function getAllocatedAmountAttribute(): float
    {
        return $this->allocations()->sum('allocated_amount');
    }

    /**
     * Get unallocated amount (remaining to be reconciled)
     * 
     * @return float
     */
    public function getUnallocatedAmountAttribute(): float
    {
        return max(0, $this->amount - $this->allocated_amount);
    }

    /* =========================
     | Status Check Methods
     |========================= */

    /**
     * Check if the payment is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the payment is failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the payment is reversed
     */
    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    /**
     * Check if payment is reconciled
     */
    public function isReconciled(): bool
    {
        return $this->reconciliation_status === 'reconciled';
    }

    /**
     * Check if payment is partially reconciled
     */
    public function isPartiallyReconciled(): bool
    {
        return $this->reconciliation_status === 'partially_reconciled';
    }

    /**
     * Check if payment needs reconciliation
     */
    public function needsReconciliation(): bool
    {
        return $this->reconciliation_status === 'pending' 
            && $this->status === 'completed';
    }

    /**
     * Check if payment can be reconciled
     */
    public function canBeReconciled(): bool
    {
        return $this->status === 'completed' 
            && $this->reconciliation_status !== 'reconciled';
    }

    /**
     * Check if payment can be reversed
     */
    public function canBeReversed(): bool
    {
        return in_array($this->status, ['completed', 'pending'])
            && !$this->isReversed();
    }

    /* =========================
     | Helper Methods
     |========================= */

    /**
     * Format the payment date
     */
    public function formattedDate(): string
    {
        return $this->payment_date->format('Y-m-d');
    }

    /**
     * Get reconciliation summary
     * 
     * @return array
     */
    public function getReconciliationSummary(): array
    {
        $allocations = $this->allocations()->with('billing')->get();

        return [
            'payment_id' => $this->id,
            'amount' => $this->amount,
            'allocated_amount' => $this->allocated_amount,
            'unallocated_amount' => $this->unallocated_amount,
            'reconciliation_status' => $this->reconciliation_status,
            'reconciled_at' => $this->reconciled_at?->toISOString(),
            'reconciled_by' => $this->reconciled_by,
            'allocation_count' => $allocations->count(),
            'allocations' => $allocations->map(function ($allocation) {
                return [
                    'billing_id' => $allocation->billing_id,
                    'billing_period' => $allocation->billing->billing_period,
                    'allocated_amount' => $allocation->allocated_amount,
                    'allocation_date' => $allocation->allocation_date->toISOString(),
                ];
            }),
        ];
    }

    /**
     * Get payment summary
     * 
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'amount' => $this->amount,
            'payment_date' => $this->payment_date->toDateString(),
            'method' => $this->method,
            'reference' => $this->reference,
            'transaction_id' => $this->transaction_id,
            'status' => $this->status,
            'reconciliation_status' => $this->reconciliation_status,
            'allocated_amount' => $this->allocated_amount,
            'unallocated_amount' => $this->unallocated_amount,
            'is_completed' => $this->isCompleted(),
            'is_reconciled' => $this->isReconciled(),
            'needs_reconciliation' => $this->needsReconciliation(),
            'allocation_count' => $this->allocations()->count(),
        ];
    }
}
