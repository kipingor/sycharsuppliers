<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasBills;
use App\Traits\HasPayments;
use App\Traits\HasNotifications;

class Resident extends Model
{
    /** @use HasFactory<\Database\Factories\ResidentFactory> */
    use HasFactory, HasBills, HasPayments, HasNotifications;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'account_number',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean', // Active (1) or Inactive (0)
    ];

    /**
     * A resident can have multiple meters.
     */
    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }

    /**
     * Activate the resident.
     */
    public function activate(): void
    {
        $this->update(['status' => true]);
    }

    /**
     * Deactivate the resident.
     */
    public function deactivate(): void
    {
        $this->update(['status' => false]);
    }

    /**
     * Get account for this resident
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
