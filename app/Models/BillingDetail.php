<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Casts\MoneyCast;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * BillingDetail Model
 *
 * Represents one line item in a bill — one meter's water consumption
 * for a single billing period.
 *
 * Actual DB columns (after fix migration):
 *   billing_id, meter_id,
 *   previous_reading_value, current_reading_value, units_used,
 *   rate, amount, description
 *
 * Accessors provide short aliases used elsewhere in the codebase:
 *   previous_reading  →  previous_reading_value
 *   current_reading   →  current_reading_value
 *   units             →  units_used
 */
class BillingDetail extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'billing_id',
        'meter_id',
        'previous_reading_value',
        'current_reading_value',
        'units_used',
        'rate',
        'amount',
        'description',
    ];

    protected $casts = [
        'previous_reading_value' => 'decimal:2',
        'current_reading_value'  => 'decimal:2',
        'units_used'             => 'decimal:2',
        'rate'                   => 'decimal:4',
        'amount'                 => MoneyCast::class,
    ];

    protected $auditInclude = [
        'billing_id',
        'meter_id',
        'previous_reading_value',
        'current_reading_value',
        'units_used',
        'rate',
        'amount',
    ];

    /* =========================
     | Short-name Accessors
     |
     | Maps short names used throughout the codebase to
     | the real underlying column names.
     |========================= */

    public function getPreviousReadingAttribute(): ?float
    {
        return isset($this->attributes['previous_reading_value'])
            ? (float) $this->attributes['previous_reading_value']
            : null;
    }

    public function getCurrentReadingAttribute(): ?float
    {
        return isset($this->attributes['current_reading_value'])
            ? (float) $this->attributes['current_reading_value']
            : null;
    }

    public function getUnitsAttribute(): ?float
    {
        return isset($this->attributes['units_used'])
            ? (float) $this->attributes['units_used']
            : null;
    }

    /* =========================
     | Audit
     |========================= */

    public function generateTags(): array
    {
        return [
            'billing_detail',
            'billing:' . $this->billing_id,
            'meter:' . $this->meter_id,
        ];
    }

    /* =========================
     | Relationships
     |========================= */

    public function billing(): BelongsTo
    {
        return $this->belongsTo(Billing::class);
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class, 'meter_id');
    }

    /* =========================
     | Helper Methods
     |========================= */

    /**
     * Consumption = current - previous (uses real column names, always safe).
     */
    public function getConsumption(): float
    {
        return max(
            0,
            (float) $this->current_reading_value - (float) $this->previous_reading_value
        );
    }

    public function isEstimated(): bool
    {
        return str_contains(strtolower($this->description ?? ''), 'estimated');
    }

    public function getSummary(): array
    {
        return [
            'meter_number'     => $this->meter?->meter_number,
            'previous_reading' => (float) $this->previous_reading_value,
            'current_reading'  => (float) $this->current_reading_value,
            'units'            => (float) $this->units_used,
            'rate'             => (float) $this->rate,
            'amount'           => $this->amount,
            'is_estimated'     => $this->isEstimated(),
        ];
    }

    /**
     * Recalculate and persist amount from units_used * rate.
     */
    public function recalculateAmount(): void
    {
        $this->amount = round((float) $this->units_used * (float) $this->rate, 2);
        $this->save();
    }
}