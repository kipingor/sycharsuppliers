<?php

namespace App\Models;

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use App\Casts\MoneyCast;

/**
 * Tariff Model
 * 
 * Represents a billing tariff/rate structure.
 * Contains base tariff information with related tiered rates.
 * 
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property string|null $meter_type (water|sewer|null for all)
 * @property string|null $type (individual|bulk|null for all)
 * @property float $fixed_charge
 * @property float|null $tax_rate
 * @property string $status (active|inactive|draft)
 * @property \Carbon\Carbon $effective_from
 * @property \Carbon\Carbon|null $effective_to
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @property-read \Illuminate\Database\Eloquent\Collection|TariffRate[] $rates
 */
class Tariff extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'code',
        'description',
        'meter_type',
        'type',
        'fixed_charge',
        'tax_rate',
        'status',
        'is_default',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'fixed_charge' => MoneyCast::class,
        'tax_rate' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_default' => 'boolean',
    ];

    /**
     * Attributes to include in audit
     */
    protected $auditInclude = [
        'name',
        'code',
        'meter_type',
        'type',
        'fixed_charge',
        'tax_rate',
        'status',
        'is_default',
        'effective_from',
        'effective_to',
    ];

    /**
     * Generate audit tags
     */
    public function generateTags(): array
    {
        return [
            'tariff',
            'status:' . $this->status,
            'type:' . ($this->meter_type ?? 'all'),
        ];
    }

    /* =========================
     | Relationships
     |========================= */

    /**
     * Get all rates for this tariff
     */
    public function rates(): HasMany
    {
        return $this->hasMany(TariffRate::class)->orderBy('min_units', 'asc');
    }

    /* =========================
     | Scopes
     |========================= */

    /**
     * Scope to get active tariffs
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get inactive tariffs
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Scope to get draft tariffs
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope to get tariffs effective on a date
     */
    public function scopeEffectiveOn($query, $date)
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            });
    }

    /**
     * Scope to get tariffs for specific meter type
     */
    public function scopeForMeterType($query, string $meterType)
    {
        return $query->where(function ($q) use ($meterType) {
            $q->whereNull('meter_type')
                ->orWhere('meter_type', $meterType);
        });
    }

    /**
     * Scope to get tariffs for bulk or individual meters
     */
    public function scopeForMeterTypeCategory($query, string $category)
    {
        return $query->where(function ($q) use ($category) {
            $q->whereNull('type')
                ->orWhere('type', $category);
        });
    }

    /* =========================
     | Helper Methods
     |========================= */

    /**
     * Check if tariff is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if tariff is draft
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if tariff is currently effective
     */
    public function isCurrentlyEffective(): bool
    {
        $now = now();

        if ($this->effective_from > $now) {
            return false;
        }

        if ($this->effective_to && $this->effective_to < $now) {
            return false;
        }

        return true;
    }

    /**
     * Check if tariff will be effective in future
     */
    public function isFutureEffective(): bool
    {
        return $this->effective_from > now();
    }

    /**
     * Check if tariff has expired
     */
    public function hasExpired(): bool
    {
        return $this->effective_to && $this->effective_to < now();
    }

    /**
     * Get the effective date range as string
     */
    public function getEffectiveDateRange(): string
    {
        $from = $this->effective_from->format('Y-m-d');
        $to = $this->effective_to ? $this->effective_to->format('Y-m-d') : 'ongoing';

        return "{$from} to {$to}";
    }

    /**
     * Activate the tariff
     */
    public function activate(): bool
    {
        return $this->update(['status' => 'active']);
    }

    /**
     * Deactivate the tariff
     */
    public function deactivate(): bool
    {
        return $this->update(['status' => 'inactive']);
    }

    /**
     * Get total rate tiers
     */
    public function getTierCount(): int
    {
        return $this->rates()->count();
    }

    /**
     * Get rate for specific consumption
     * 
     * @param float $consumption
     * @return TariffRate|null
     */
    public function getRateForConsumption(float $consumption): ?TariffRate
    {
        return $this->rates()
            ->where('min_units', '<=', $consumption)
            ->where(function ($query) use ($consumption) {
                $query->whereNull('max_units')
                    ->orWhere('max_units', '>=', $consumption);
            })
            ->first();
    }

    /**
     * Get all applicable rates for consumption
     * 
     * @param float $consumption
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getApplicableRates(float $consumption): \Illuminate\Database\Eloquent\Collection
    {
        return $this->rates()
            ->where('min_units', '<=', $consumption)
            ->get();
    }

    /**
     * Get minimum rate
     */
    public function getMinimumRate(): ?float
    {
        $rate = $this->rates()->orderBy('rate', 'asc')->first();
        return $rate?->rate;
    }

    /**
     * Get maximum rate
     */
    public function getMaximumRate(): ?float
    {
        $rate = $this->rates()->orderBy('rate', 'desc')->first();
        return $rate?->rate;
    }

    /**
     * Calculate estimated charge for consumption
     * 
     * @param float $consumption
     * @return float Quick estimation without ChargeCalculator
     */
    public function estimateCharge(float $consumption): float
    {
        $chargeCalculator = app(\App\Services\Billing\ChargeCalculator::class);
        $charges = $chargeCalculator->calculateCharges($consumption, $this);
        
        return $charges['total'];
    }

    /**
     * Get tariff summary
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'status' => $this->status,
            'meter_type' => $this->meter_type,
            'type' => $this->type,
            'fixed_charge' => $this->fixed_charge,
            'tax_rate' => $this->tax_rate,
            'tier_count' => $this->getTierCount(),
            'min_rate' => $this->getMinimumRate(),
            'max_rate' => $this->getMaximumRate(),
            'effective_from' => $this->effective_from->format('Y-m-d'),
            'effective_to' => $this->effective_to?->format('Y-m-d'),
            'is_currently_effective' => $this->isCurrentlyEffective(),
            'is_future_effective' => $this->isFutureEffective(),
            'has_expired' => $this->hasExpired(),
        ];
    }

    /**
     * Clone tariff with new effective dates
     * 
     * @param \Carbon\Carbon $effectiveFrom
     * @param \Carbon\Carbon|null $effectiveTo
     * @return Tariff
     */
    public function cloneWithNewDates(\Carbon\Carbon $effectiveFrom, ?\Carbon\Carbon $effectiveTo = null): Tariff
    {
        $newTariff = $this->replicate(['effective_from', 'effective_to']);
        $newTariff->effective_from = $effectiveFrom;
        $newTariff->effective_to = $effectiveTo;
        $newTariff->status = 'draft';
        $newTariff->save();

        // Clone rates
        foreach ($this->rates as $rate) {
            $newRate = $rate->replicate();
            $newRate->tariff_id = $newTariff->id;
            $newRate->save();
        }

        return $newTariff;
    }
}