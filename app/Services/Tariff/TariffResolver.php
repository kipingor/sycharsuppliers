<?php

namespace App\Services\Tariff;

use App\Models\Meter;
use App\Models\Tariff;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tariff Resolver Service
 * 
 * FIXED: Uses correct column names and provides fallback
 */
class TariffResolver
{
    /**
     * Get applicable tariff for a meter
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
     * ✅ FIXED: Correct column names + fallback logic
     */
    protected function resolveTariff(Meter $meter, Carbon $date): ?Tariff
    {
        // Try to find specific tariff for this meter type and date
        $tariff = $this->findTariffByRules($meter, $date);

        if ($tariff) {
            return $tariff;
        }

        // Fallback 1: Try to find a default tariff for this meter type
        $tariff = Tariff::where('status', 'active')
            ->where('is_default', true)
            ->where(function ($q) use ($meter) {
                $q->whereNull('meter_type')
                  ->orWhere('meter_type', $meter->meter_type);
            })
            ->first();

        if ($tariff) {
            Log::warning('Using default tariff for meter', [
                'meter_id' => $meter->id,
                'meter_number' => $meter->meter_number,
                'tariff_id' => $tariff->id,
                'tariff_name' => $tariff->name,
            ]);
            return $tariff;
        }

        // Fallback 2: Try ANY active tariff
        $tariff = Tariff::where('status', 'active')->first();

        if ($tariff) {
            Log::warning('Using any active tariff (no specific match found)', [
                'meter_id' => $meter->id,
                'meter_number' => $meter->meter_number,
                'tariff_id' => $tariff->id,
                'tariff_name' => $tariff->name,
            ]);
            return $tariff;
        }

        // No tariff found at all
        Log::error('No tariff found for meter', [
            'meter_id' => $meter->id,
            'meter_number' => $meter->meter_number,
            'meter_type' => $meter->meter_type,
            'date' => $date->format('Y-m-d'),
            'suggestion' => 'Run: php artisan tariff:create-default',
        ]);

        return null;
    }

    /**
     * Find tariff by specific rules
     */
    protected function findTariffByRules(Meter $meter, Carbon $date): ?Tariff
    {
        $query = Tariff::where('status', 'active')
            ->where(function ($q) use ($date) {
                $q->where('effective_from', '<=', $date)
                    ->where(function ($subQ) use ($date) {
                        $subQ->whereNull('effective_to')
                            ->orWhere('effective_to', '>=', $date);
                    });
            });

        // ✅ FIXED: Use correct column name ($meter->meter_type not $meter->type)
        $query->where(function ($q) use ($meter) {
            $q->whereNull('meter_type')
                ->orWhere('meter_type', $meter->meter_type);
        });

        $query->orderByRaw('
            CASE 
                WHEN meter_type IS NOT NULL THEN 1
                ELSE 2
            END
        ');

        $query->orderBy('effective_from', 'desc');

        return $query->first();
    }

    /**
     * Get all active tariffs
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
     * ✅ FIXED: Correct column names
     */
    public function isTariffApplicable(Tariff $tariff, Meter $meter, ?Carbon $date = null): bool
    {
        $date = $date ?? now();

        if ($tariff->status !== 'active') {
            return false;
        }

        if ($tariff->effective_from > $date) {
            return false;
        }

        if ($tariff->effective_to && $tariff->effective_to < $date) {
            return false;
        }

        // ✅ FIXED: Check meter_type compatibility (not ->type)
        if ($tariff->meter_type && $tariff->meter_type !== $meter->meter_type) {
            return false;
        }

        return true;
    }

    protected function getCacheKey(Meter $meter, Carbon $date): string
    {
        $prefix = config('billing.cache.prefix', 'billing');
        $dateKey = $date->format('Y-m-d');

        return "{$prefix}:tariff:meter:{$meter->id}:date:{$dateKey}";
    }

    public function clearCache(?Meter $meter = null): void
    {
        if ($meter) {
            $prefix = config('billing.cache.prefix', 'billing');
            Cache::forget("{$prefix}:tariff:meter:{$meter->id}:*");
        } else {
            Cache::flush();
        }
    }
}