<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Meter extends Model
{
    /** @use HasFactory<\Database\Factories\MeterFactory> */
    use HasFactory;

    protected $fillable = [
        'resident_id',
        'meter_number',
        'meter_name',
        'location',
        'status',
        'installation_date',
    ];

    protected $casts = [
        'installation_date' => 'date',
        'status' => 'string', // Active (1) or Inactive (0)
    ];

    protected $appends = [
        'total_paid', 
    ];

    /**
     * A Meter can have multiple bills.
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Billing::class);
    }

    /**
     * Get all of the Payments for the Meter.
     */
    public function payments(): hasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getTotalPaidAttribute()
    {
        return $this->payments()->sum('amount');
    }

    /**
     * Get the total outstanding balance for the resident.
     */
    public function getOutstandingBalance(): float
    {
        return $this->bills()->where('status', 'pending')->sum('amount_due');
    }

    /**
     * Check if the resident has overdue bills.
     */
    public function hasOverdueBills(): bool
    {
        return $this->bills()->where('status', 'overdue')->exists();
    }

    /**
     * Get the resident who owns the meter.
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * A meter can have multiple readings.
     */
    public function meterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    /**
     * Get the latest meter reading for the meter.
     */
    public function latestReading(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->meterReadings()->latest()->first()?->reading_value ?? 0
        );
    }
}
