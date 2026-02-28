<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNote extends Model
{
    protected $fillable = [
        'billing_id',
        'previous_account_id',
        'reference',
        'type',
        'amount',
        'reason',
        'status',
        'void_reason',
        'voided_at',
        'created_by',
        'voided_by',
    ];

    protected $casts = [
        'amount'    => 'decimal:2',
        'voided_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function billing(): BelongsTo
    {
        return $this->belongsTo(Billing::class);
    }

    public function previousAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'previous_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isApplied(): bool
    {
        return $this->status === 'applied';
    }

    public static function generateReference(): string
    {
        $year    = date('Y');
        $lastSeq = static::whereYear('created_at', $year)->count() + 1;
        return sprintf('CN-%s-%04d', $year, $lastSeq);
    }

    public static function typeLabels(): array
    {
        return [
            'previous_resident_debt' => 'Previous Resident Debt',
            'billing_error'          => 'Billing Error',
            'goodwill'               => 'Goodwill Adjustment',
            'other'                  => 'Other',
        ];
    }

    public function typeLabel(): string
    {
        return static::typeLabels()[$this->type] ?? $this->type;
    }
}