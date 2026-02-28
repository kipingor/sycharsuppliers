<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Carry Forward Balance Model
 * 
 * Represents credit or debit balances carried forward from overpayments
 * or underpayments. Credits can be applied to future bills.
 * 
 * @property int $id
 * @property int $account_id
 * @property int|null $payment_id (payment that created this balance)
 * @property string $type (credit|debit)
 * @property float $balance
 * @property string $status (active|applied|expired)
 * @property \Carbon\Carbon|null $expires_at
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property-read Account $account
 * @property-read Payment|null $payment
 */
class CarryForwardBalance extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'account_id',
        'payment_id',
        'type',
        'balance',
        'status',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    /**
     * Attributes to include in audit
     */
    protected $auditInclude = [
        'account_id',
        'payment_id',
        'type',
        'balance',
        'status',
        'expires_at',
    ];

    /**
     * Generate audit tags
     */
    public function generateTags(): array
    {
        return [
            'carry_forward',
            'account:' . $this->account_id,
            'type:' . $this->type,
            'status:' . $this->status,
        ];
    }

    /* =========================
     | Relationships
     |========================= */

    /**
     * Get the account this balance belongs to
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the payment that created this balance
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /* =========================
     | Scopes
     |========================= */

    /**
     * Scope to get active balances
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to get credit balances
     */
    public function scopeCredit($query)
    {
        return $query->where('type', 'credit');
    }

    /**
     * Scope to get debit balances
     */
    public function scopeDebit($query)
    {
        return $query->where('type', 'debit');
    }

    /**
     * Scope to get expired balances
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())
            ->where('status', 'active');
    }

    /**
     * Scope for a specific account
     */
    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /* =========================
     | Helper Methods
     |========================= */

    /**
     * Check if balance is a credit
     */
    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }

    /**
     * Check if balance is a debit
     */
    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }

    /**
     * Check if balance is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' 
            && (!$this->expires_at || $this->expires_at->isFuture());
    }

    /**
     * Check if balance has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Apply balance (reduce by amount)
     * 
     * @param float $amount Amount to apply
     * @return float Actual amount applied
     */
    public function apply(float $amount): float
    {
        if (!$this->isActive()) {
            return 0;
        }

        $toApply = min($amount, $this->balance);
        $newBalance = $this->balance - $toApply;

        $this->update([
            'balance' => $newBalance,
            'status' => $newBalance <= 0 ? 'applied' : 'active',
        ]);

        return $toApply;
    }

    /**
     * Mark as expired
     */
    public function markExpired(): bool
    {
        return $this->update([
            'status' => 'expired',
        ]);
    }

    /**
     * Get balance summary
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'balance' => $this->balance,
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'is_expired' => $this->isExpired(),
            'expires_at' => $this->expires_at?->format('Y-m-d'),
            'payment_id' => $this->payment_id,
            'created_at' => $this->created_at->format('Y-m-d'),
        ];
    }
}