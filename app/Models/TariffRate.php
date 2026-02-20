<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tariff Rate Model
 * 
 * Represents a tier in a tariff's rate structure.
 * Defines rate per unit for a specific consumption range.
 * 
 * @property int $id
 * @property int $tariff_id
 * @property string|null $name
 * @property float $min_units
 * @property float|null $max_units
 * @property float $rate
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property-read Tariff $tariff
 */
class TariffRate extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'tariff_id',
        'name',
        'min_units',
        'max_units',
        'rate',
        'sort_order',
    ];

    protected $casts = [
        'min_units' => 'decimal:2',
        'max_units' => 'decimal:2',
        'rate' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    /**
     * Attributes to include in audit
     */
    protected $auditInclude = [
        'tariff_id',
        'min_units',
        'max_units',
        'rate',
        'sort_order',
    ];

    /**
     * Generate audit tags
     */
    public function generateTags(): array
    {
        return [
            'tariff_rate',
            'tariff:' . $this->tariff_id,
        ];
    }

    /* =========================
     | Relationships
     |========================= */

    /**
     * Get the tariff this rate belongs to
     */
    public function tariff(): BelongsTo
    {
        return $this->belongsTo(Tariff::class);
    }

    /* =========================
     | Scopes
     |========================= */

    /**
     * Scope to get rates for a specific tariff
     */
    public function scopeForTariff($query, int $tariffId)
    {
        return $query->where('tariff_id', $tariffId);
    }

    /**
     * Scope to order by minimum units (tier order)
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('min_units', 'asc');
    }

    /**
     * Scope to get rates applicable for consumption
     */
    public function scopeApplicableFor($query, float $consumption)
    {
        return $query->where('min_units', '<=', $consumption)
            ->where(function ($q) use ($consumption) {
                $q->whereNull('max_units')
                    ->orWhere('max_units', '>=', $consumption);
            });
    }

    /* =========================
     | Helper Methods
     |========================= */

    /**
     * Check if consumption falls within this rate tier
     * 
     * @param float $consumption
     * @return bool
     */
    public function appliesTo(float $consumption): bool
    {
        if ($consumption < $this->min_units) {
            return false;
        }

        if ($this->max_units && $consumption > $this->max_units) {
            return false;
        }

        return true;
    }

    /**
     * Get the range of units this rate applies to
     * 
     * @return string
     */
    public function getUnitRange(): string
    {
        if ($this->max_units) {
            return "{$this->min_units} - {$this->max_units} units";
        }

        return "{$this->min_units}+ units";
    }

    /**
     * Get the unit range for display
     * 
     * @return string
     */
    public function getDisplayRange(): string
    {
        if ($this->name) {
            return $this->name;
        }

        return $this->getUnitRange();
    }

    /**
     * Check if this is an open-ended tier (no max)
     */
    public function isOpenEnded(): bool
    {
        return $this->max_units === null;
    }

    /**
     * Get the number of units in this tier for a given consumption
     * 
     * @param float $consumption Total consumption
     * @return float Units that fall in this tier
     */
    public function getUnitsInTier(float $consumption): float
    {
        if (!$this->appliesTo($consumption)) {
            return 0;
        }

        $tierStart = $this->min_units;
        $tierEnd = $this->max_units ?? $consumption;

        $unitsInTier = min($consumption, $tierEnd) - $tierStart;

        return max(0, $unitsInTier);
    }

    /**
     * Calculate charge for units in this tier
     * 
     * @param float $consumption Total consumption
     * @return float Charge for units in this tier
     */
    public function calculateCharge(float $consumption): float
    {
        $units = $this->getUnitsInTier($consumption);
        return $units * $this->rate;
    }

    /**
     * Check if this rate overlaps with another rate
     * 
     * @param TariffRate $other
     * @return bool
     */
    public function overlaps(TariffRate $other): bool
    {
        // If different tariffs, no overlap concern
        if ($this->tariff_id !== $other->tariff_id) {
            return false;
        }

        // Check if ranges overlap
        $thisStart = $this->min_units;
        $thisEnd = $this->max_units ?? PHP_FLOAT_MAX;
        
        $otherStart = $other->min_units;
        $otherEnd = $other->max_units ?? PHP_FLOAT_MAX;

        return $thisStart <= $otherEnd && $otherStart <= $thisEnd;
    }

    /**
     * Get rate summary
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'min_units' => $this->min_units,
            'max_units' => $this->max_units,
            'rate' => $this->rate,
            'unit_range' => $this->getUnitRange(),
            'display_range' => $this->getDisplayRange(),
            'is_open_ended' => $this->isOpenEnded(),
            'sort_order' => $this->sort_order,
        ];
    }

    /**
     * Validate rate tier integrity
     * 
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validate(): array
    {
        $errors = [];

        // Check min is less than max
        if ($this->max_units && $this->min_units >= $this->max_units) {
            $errors[] = 'Minimum units must be less than maximum units';
        }

        // Check rate is positive
        if ($this->rate < 0) {
            $errors[] = 'Rate cannot be negative';
        }

        // Check for overlaps with other rates in same tariff
        $overlaps = TariffRate::forTariff($this->tariff_id)
            ->where('id', '!=', $this->id)
            ->get()
            ->filter(fn($rate) => $this->overlaps($rate));

        if ($overlaps->isNotEmpty()) {
            $errors[] = 'Rate range overlaps with existing rate(s)';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get the next tier (higher consumption)
     */
    public function getNextTier(): ?TariffRate
    {
        return TariffRate::forTariff($this->tariff_id)
            ->where('min_units', '>', $this->min_units)
            ->ordered()
            ->first();
    }

    /**
     * Get the previous tier (lower consumption)
     */
    public function getPreviousTier(): ?TariffRate
    {
        return TariffRate::forTariff($this->tariff_id)
            ->where('min_units', '<', $this->min_units)
            ->orderBy('min_units', 'desc')
            ->first();
    }
}
