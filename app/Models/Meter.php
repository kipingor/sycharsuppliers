<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Meter extends Model
{
    /** @use HasFactory<\Database\Factories\MeterFactory> */
    use HasFactory;

    protected $fillable = [
        'meter_id',
        'meter_number',
        'location',
        'status',
        'installation_date',
    ];

    protected $casts = [
        'installation_date' => 'date',
        'status' => 'boolean', // Active (1) or Inactive (0)
    ];

    /**
     * A Meter can have multiple bills.
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Billing::class);
    }

    /**
     * A customer can have multiple payments.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the total outstanding balance for the customer.
     */
    public function getOutstandingBalance(): float
    {
        return $this->bills()->where('status', 'pending')->sum('amount_due');
    }

    /**
     * Check if the customer has overdue bills.
     */
    public function hasOverdueBills(): bool
    {
        return $this->bills()->where('status', 'overdue')->exists();
    }

    /**
     * Get the customer who owns the meter.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
