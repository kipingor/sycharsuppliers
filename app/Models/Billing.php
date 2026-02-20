<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use OwenIt\Auditing\Contracts\Auditable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Billing Model
 * 
 * Represents a bill for an account for a specific billing period.
 * Contains aggregated charges from multiple meters/services.
 * 
 * @property int $id
 * @property int $account_id
 * @property string $billing_period (format: YYYY-MM)
 * @property float $total_amount
 * @property string $status (pending|partially_paid|paid|overdue|voided)
 * @property \Carbon\Carbon $issued_at
 * @property \Carbon\Carbon $due_date
 * @property \Carbon\Carbon|null $paid_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property-read Account $account
 * @property-read \Illuminate\Database\Eloquent\Collection|BillingDetail[] $details
 * @property-read \Illuminate\Database\Eloquent\Collection|PaymentAllocation[] $allocations
 */
class Billing extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'account_id',
        'billing_period',
        'total_amount',
        'amount_due',
        'status',
        'issued_at',
        'due_date',
        'paid_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'total_amount' => MoneyCast::class,
    ];

    /**
     * Attributes to include in audit
     */
    protected $auditInclude = [
        'account_id',
        'billing_period',
        'total_amount',
        'status',
        'due_date',
        'paid_at',
    ];

    /**
     * Generate audit tags
     */
    public function generateTags(): array
    {
        return [
            'billing',
            'account:' . $this->account_id,
            'period:' . $this->billing_period,
            'status:' . $this->status,
        ];
    }

    /* =========================
     | Relationships
     |========================= */

    /**
     * Get the account this billing belongs to
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get all billing details (line items) for this bill
     */
    public function details(): HasMany
    {
        return $this->hasMany(BillingDetail::class, 'billing_id');
    }

    /**
     * Get all payment allocations for this bill
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'billing_id');
    }

    /**
     * Get payments through allocations
     */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Payment::class,
            PaymentAllocation::class,
            'billing_id',
            'id',
            'id',
            'payment_id'
        );
    }

    /* =========================
     | Scopes
     |========================= */

    /**
     * Scope to get pending bills
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get paid bills
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope to get overdue bills
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->where('due_date', '<', now());
    }

    /**
     * Scope to get bills for a specific period
     */
    public function scopeForPeriod($query, string $period)
    {
        return $query->where('billing_period', $period);
    }

    /**
     * Scope to get bills for a specific account
     */
    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /* =========================
     | Accessor Methods (Read-Only Domain Logic)
     |========================= */

    /**
     * Get total paid amount from allocations
     * 
     * @return float
     */
    public function getPaidAmountAttribute(): float
    {
        return $this->allocations()
            ->whereHas('payment', function ($query) {
                $query->where('status', 'completed');
            })
            ->sum('allocated_amount');
    }

    /**
     * Get remaining balance
     * 
     * @return float
     */
    public function getBalanceAttribute(): float
    {
        return max(0, $this->total_amount - $this->paid_amount);
    }

    /**
     * Get the latest meter reading for the meter
     */
    public function getCurrentReadingAttribute()
    {
        return $this->details->sum('current_reading') ?? 0;
    }

    /**
     * Get the previous meter reading for the meter
     */
    public function getPreviousReadingAttribute()
    {
        return $this->details->sum('previous_reading_value') ?? 0;
    }

    /**
     * Get total consumption across all meters
     */
    public function getTotalConsumptionAttribute(): float
    {
        return $this->details->sum('units');
    }

    /* =========================
     | Status Check Methods
     |========================= */

    /**
     * Check if the bill is paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if the bill is partially paid
     */
    public function isPartiallyPaid(): bool
    {
        return $this->status === 'partially_paid';
    }

    /**
     * Check if the bill is overdue
     */
    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_date->isPast();
    }

    /**
     * Check if the bill is voided
     */
    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    /**
     * Check if bill can be modified
     */
    public function canBeModified(): bool
    {
        return !in_array($this->status, ['paid', 'voided']);
    }

    /* =========================
     | Domain Logic Methods
     | NOTE: These should typically be called from services,
     | not directly from controllers
     |========================= */

    /**
     * Recalculate total from billing details
     * 
     * @return void
     */
    public function recalculateTotal(): void
    {
        $this->update([
            'total_amount' => $this->details()->sum('amount'),
        ]);
    }

    public function getOutstandingBalance(): float
    {
        return $this->account->billings()
            ->whereIn('status', ['pending', 'overdue', 'partially_paid'])
            ->sum(DB::raw('total_amount - paid_amount'));
    }

    /**
     * Get days until due (negative if overdue)
     * 
     * @return int
     */
    public function getDaysUntilDue(): int
    {
        return now()->diffInDays($this->due_date, false);
    }

    /**
     * Get days overdue (0 if not overdue)
     * 
     * @return int
     */
    public function getDaysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }
        return $this->due_date->diffInDays(now());
    }

    /**
     * Get formatted billing period
     * 
     * @return string
     */
    public function getFormattedPeriod(): string
    {
        return Carbon::createFromFormat('Y-m', $this->billing_period)->format('F Y');
    }

    /**
     * Get bill summary
     * 
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'billing_period' => $this->billing_period,
            'formatted_period' => $this->getFormattedPeriod(),
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'balance' => $this->balance,
            'status' => $this->status,
            'issued_at' => $this->issued_at->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'days_until_due' => $this->getDaysUntilDue(),
            'is_overdue' => $this->isOverdue(),
            'days_overdue' => $this->getDaysOverdue(),
            'detail_count' => $this->details()->count(),
            'allocation_count' => $this->allocations()->count(),
        ];
    }
}
