<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category',
        'amount',
        'description',
        'expense_date',
        'receipt_number',
        'receipt_path',
        'approved_by',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'status',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'rejected_at'  => 'datetime',
        'status'       => 'boolean',
    ];

    /* ── Relationships ────────────────────────────────────────────────────── */

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /* ── Scopes ───────────────────────────────────────────────────────────── */

    public function scopeApproved($query)
    {
        return $query->where('status', true)->whereNotNull('approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', false)->whereNull('rejected_by');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', false)->whereNotNull('rejected_by');
    }

    public function scopeForPeriod($query, string $start, string $end)
    {
        return $query->whereBetween('expense_date', [$start, $end]);
    }

    /* ── State helpers ────────────────────────────────────────────────────── */

    public function isApproved(): bool
    {
        return $this->status === true && $this->approved_by !== null;
    }

    public function isPending(): bool
    {
        return $this->status === false && $this->rejected_by === null;
    }

    public function isRejected(): bool
    {
        return $this->status === false && $this->rejected_by !== null;
    }

    public function statusLabel(): string
    {
        if ($this->isApproved()) {
            return 'Approved';
        }
        if ($this->isRejected()) {
            return 'Rejected';
        }
        return 'Pending';
    }

    /* ── Actions ──────────────────────────────────────────────────────────── */

    public function approve(int $userId): void
    {
        $this->update([
            'status'           => true,
            'approved_by'      => $userId,
            'rejected_by'      => null,
            'rejected_at'      => null,
            'rejection_reason' => null,
        ]);
    }

    public function reject(int $userId, ?string $reason = null): void
    {
        $this->update([
            'status'           => false,
            'approved_by'      => null,
            'rejected_by'      => $userId,
            'rejected_at'      => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function resetToPending(): void
    {
        $this->update([
            'status'           => false,
            'approved_by'      => null,
            'rejected_by'      => null,
            'rejected_at'      => null,
            'rejection_reason' => null,
        ]);
    }

    public function getBudget(): ?ExpenseBudget
    {
        return ExpenseBudget::findForPeriod(
            $this->category,
            $this->expense_date->year,
            $this->expense_date->month
        );
    }

    public function formattedDate(): string
    {
        return $this->expense_date->format('Y-m-d');
    }
}
