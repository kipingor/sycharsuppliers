<?php

namespace App\Models;

use App\Models\CreditNote;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Account Model
 *
 * Represents a billing account. An account can have multiple meters,
 * bills, and payments. This is the central entity for billing operations.
 *
 * @property int $id
 * @property string $account_number
 * @property string $name
 * @property string $status
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $email
 * @property \Carbon\Carbon|null $activated_at
 * @property \Carbon\Carbon|null $suspended_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|Meter[] $meters
 * @property-read \Illuminate\Database\Eloquent\Collection|Billing[] $billings
 * @property-read \Illuminate\Database\Eloquent\Collection|Payment[] $payments
 * @property-read \Illuminate\Database\Eloquent\Collection|CarryForwardBalance[] $carryForwardBalances
 */
class Account extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    /* =========================
     | Mass Assignment
     |=========================*/

    protected $fillable = [
        'account_number',
        'name',
        'email',
        'phone',
        'address',
        'status',
        'activated_at',
        'suspended_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    /**
     * Attributes to include in audit
     */
    protected $auditInclude = [
        'account_number',
        'name',
        'status',
        'address',
        'phone',
        'email',
        'activated_at',
        'suspended_at',
    ];

    /**
     * Generate audit tags
     */
    public function generateTags(): array
    {
        return [
            'account',
            'account:' . $this->id,
            'status:' . $this->status,
        ];
    }

    /* =========================
     | Relationships
     |=========================*/

    /**
     * Get all meters for this account
     */
    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }

    /**
     * Get all billings for this account
     */
    public function billings(): HasMany
    {
        return $this->hasMany(Billing::class);
    }

    /**
     * Get all payments for this account
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get all carry-forward balances for this account
     */
    public function carryForwardBalances(): HasMany
    {
        return $this->hasMany(CarryForwardBalance::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(\App\Models\EmailLog::class);
    }

    /* =========================
     | Scopes
     |========================= */

    /**
     * Scope to get only active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get suspended accounts
     */
    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    /**
     * Scope to get accounts with outstanding balances
     */
    public function scopeWithOutstandingBalance($query)
    {
        return $query->whereHas('billings', function ($q) {
            $q->whereIn('status', ['pending', 'partially_paid']);
        });
    }

    /* =========================
     | Helper Methods
     |========================= */

    /**
     * Get current balance for this account.
     *
     * Uses a simple ledger approach (total billed minus total paid) that is
     * consistent with the account statement — so the figure shown on the
     * account page and on printed statements always agree.
     *
     * Previous implementation was broken in two ways:
     *  1. It filtered payments by status = 'completed', so pending-status
     *     payments were invisible and the balance was overstated.
     *  2. It summed total_amount rather than amount_due for outstanding bills,
     *     double-counting amounts already covered by partial payments.
     *
     * @return float Total outstanding balance
     */
    public function getCurrentBalance(): float
    {
        // All billed charges (voided bills are excluded — they were cancelled)
        $totalBilled = $this->billings()
            ->whereNotIn('status', ['voided'])
            ->sum('total_amount');

        // All payments received (no status filter — every ksh recorded counts)
        $totalPaid = $this->payments()
            ->whereNull('deleted_at')
            ->sum('amount');

        // Applied credit notes
        $credited = CreditNote::whereHas(
            'billing',
            fn ($q) => $q->where('account_id', $this->id)
        )
            ->where('status', 'applied')
            ->sum('amount');

        // Active carry-forward credits (overpayments carried to next period)
        $carryForwardCredits = $this->carryForwardBalances()
            ->where('type', 'credit')
            ->where('status', 'active')
            ->sum('balance');

        return max(0, $totalBilled - $totalPaid - $credited - $carryForwardCredits);
    }

    /**
     * Get total amount due (before payments)
     *
     * @return float Total amount from all outstanding bills
     */
    public function getTotalDue(): float
    {
        return $this->billings()
            ->whereIn('status', ['pending', 'partially_paid'])
            ->sum('total_amount');
    }

    /**
     * Get total amount paid
     *
     * @return float Total of all completed payments
     */
    public function getTotalPaid(): float
    {
        return $this->payments()
            ->where('status', 'completed')
            ->sum('amount');
    }

    /**
     * Get active carry-forward credit balance
     *
     * @return float Total active credit balance
     */
    public function getActiveCreditBalance(): float
    {
        return $this->carryForwardBalances()
            ->where('type', 'credit')
            ->where('status', 'active')
            ->sum('balance');
    }

    /**
     * Get outstanding bills for this account
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getOutstandingBills()
    {
        return $this->billings()
            ->whereIn('status', ['pending', 'partially_paid'])
            ->orderBy('due_date', 'asc')
            ->get();
    }

    public function getOutstandingPayments()
    {
        return $this->payments()
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Check if account is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if account is suspended
     *
     * @return bool
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Check if account has overdue bills
     *
     * @return bool
     */
    public function hasOverdueBills(): bool
    {
        return $this->billings()
            ->where('status', 'pending')
            ->where('due_date', '<', now())
            ->exists();
    }

    /**
     * Suspend the account
     *
     * @param string|null $reason
     * @return bool
     */
    public function suspend(?string $reason = null): bool
    {
        return $this->update([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);
    }

    /**
     * Activate/reactivate the account
     *
     * @return bool
     */
    public function activate(): bool
    {
        return $this->update([
            'status' => 'active',
            'activated_at' => now(),
            'suspended_at' => null,
        ]);
    }

    /**
     * Get account summary for dashboard/reports
     *
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'account_number' => $this->account_number,
            'name' => $this->name,
            'status' => $this->status,
            'total_due' => $this->getTotalDue(),
            'total_paid' => $this->getTotalPaid(),
            'current_balance' => $this->getCurrentBalance(),
            'credit_balance' => $this->getActiveCreditBalance(),
            'meter_count' => $this->meters()->count(),
            'outstanding_bills' => $this->getOutstandingBills()->count(),
            'has_overdue' => $this->hasOverdueBills(),
        ];
    }
}