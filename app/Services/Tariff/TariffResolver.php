<?php

namespace App\Services\Tariff;

use App\Models\Meter;
use App\Models\Tariff;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Tariff Resolver Service
 * 
 * Resolves the applicable tariff for a meter based on:
 * - Meter type
 * - Effective dates
 * - Account type
 * - Special rules
 * 
 * @package App\Services\Billing
 */
class TariffResolver
{
    /**
     * Get applicable tariff for a meter
     * 
     * @param Meter $meter
     * @param Carbon|null $date Date to check (default: now)
     * @return Tariff|null
     */
    public function getTariffForMeter(Meter $meter, ?Carbon $date = null): ?Tariff
    {
        $date = $date ?? now();

        // Check cache first
        $cacheKey = $this->getCacheKey($meter, $date);
        
        if (config('billing.cache.enabled', true)) {
            $tariff = Cache::remember($cacheKey, config('billing.cache.ttl', 3600), function () use ($meter, $date) {
                return $this->resolveTariff($meter, $date);
            });
        } else {
            $tariff = $this->resolveTariff($meter, $date);
        }

        return $tariff;
    }

    /**
     * Resolve tariff based on rules
     * 
     * @param Meter $meter
     * @param Carbon $date
     * @return Tariff|null
     */
    protected function resolveTariff(Meter $meter, Carbon $date): ?Tariff
    {
        $query = Tariff::where('status', 'active')
            ->where(function ($q) use ($date) {
                $q->where('effective_from', '<=', $date)
                    ->where(function ($subQ) use ($date) {
                        $subQ->whereNull('effective_to')
                            ->orWhere('effective_to', '>=', $date);
                    });
            });

        // Filter by meter type if tariff is type-specific
        $query->where(function ($q) use ($meter) {
            $q->whereNull('meter_type')
                ->orWhere('meter_type', $meter->type);
        });

        // Filter by meter_type (individual/bulk) if tariff specifies
        $query->where(function ($q) use ($meter) {
            $q->whereNull('type')
                ->orWhere('type', $meter->meter_type);
        });

        // Order by specificity (more specific tariffs first)
        $query->orderByRaw('
            CASE 
                WHEN meter_type IS NOT NULL THEN 1
                ELSE 2
            END
        ');

        // Order by effective date (newest first)
        $query->orderBy('effective_from', 'desc');

        return $query->first();
    }

    /**
     * Get all active tariffs
     * 
     * @param Carbon|null $date
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveTariffs(?Carbon $date = null): \Illuminate\Database\Eloquent\Collection
    {
        $date = $date ?? now();

        return Tariff::where('status', 'active')
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Check if tariff is applicable for a meter
     * 
     * @param Tariff $tariff
     * @param Meter $meter
     * @param Carbon|null $date
     * @return bool
     */
    public function isTariffApplicable(Tariff $tariff, Meter $meter, ?Carbon $date = null): bool
    {
        $date = $date ?? now();

        // Check status
        if ($tariff->status !== 'active') {
            return false;
        }

        // Check effective dates
        if ($tariff->effective_from > $date) {
            return false;
        }

        if ($tariff->effective_to && $tariff->effective_to < $date) {
            return false;
        }

        // Check meter type compatibility
        if ($tariff->meter_type && $tariff->meter_type !== $meter->type) {
            return false;
        }

        // Check meter_type (individual/bulk) compatibility
        if ($tariff->type && $tariff->type !== $meter->meter_type) {
            return false;
        }

        return true;
    }

    /**
     * Get tariff rate for specific consumption
     * 
     * @param Tariff $tariff
     * @param float $consumption
     * @return float Average rate for the consumption
     */
    public function getAverageRateForConsumption(Tariff $tariff, float $consumption): float
    {
        if ($consumption <= 0) {
            return 0;
        }

        $chargeCalculator = app(\App\Services\Billing\ChargeCalculator::class);
        $charges = $chargeCalculator->calculateCharges($consumption, $tariff);

        return $charges['average_rate'];
    }

    /**
     * Get tariff comparison for a meter
     * 
     * @param Meter $meter
     * @param float $consumption Sample consumption for comparison
     * @param Carbon|null $date
     * @return array
     */
    public function compareTariffsForMeter(Meter $meter, float $consumption, ?Carbon $date = null): array
    {
        $date = $date ?? now();
        $tariffs = $this->getActiveTariffs($date);
        $chargeCalculator = app(\App\Services\Billing\ChargeCalculator::class);

        $comparison = [];

        foreach ($tariffs as $tariff) {
            if (!$this->isTariffApplicable($tariff, $meter, $date)) {
                continue;
            }

            $charges = $chargeCalculator->calculateCharges($consumption, $tariff, $meter);

            $comparison[] = [
                'tariff_id' => $tariff->id,
                'tariff_name' => $tariff->name,
                'total_charge' => $charges['total'],
                'average_rate' => $charges['average_rate'],
                'breakdown' => $charges['breakdown'],
            ];
        }

        // Sort by total charge
        usort($comparison, fn($a, $b) => $a['total_charge'] <=> $b['total_charge']);

        return $comparison;
    }

    /**
     * Get cache key for tariff resolution
     * 
     * @param Meter $meter
     * @param Carbon $date
     * @return string
     */
    protected function getCacheKey(Meter $meter, Carbon $date): string
    {
        $prefix = config('billing.cache.prefix', 'billing');
        $dateKey = $date->format('Y-m-d');

        return "{$prefix}:tariff:meter:{$meter->id}:date:{$dateKey}";
    }

    /**
     * Clear tariff cache
     * 
     * @param Meter|null $meter Clear for specific meter or all
     * @return void
     */
    public function clearCache(?Meter $meter = null): void
    {
        if ($meter) {
            $prefix = config('billing.cache.prefix', 'billing');
            Cache::forget("{$prefix}:tariff:meter:{$meter->id}:*");
        } else {
            // Clear all tariff cache
            Cache::flush();
        }
    }
}