<?php

namespace App\Services\Billing;

use App\Models\Meter;
use App\Models\MeterReading;

class MeterChargeCalculator
{
    public function __construct(
        protected ChargeCalculator $chargeCalculator
    ) {
    }

    /**
     * Calculate charges for a meter.
     *
     * @return array<int, array<string, mixed>>
     */
    public function calculate(int $meterId): array
    {
        $meter = Meter::findOrFail($meterId);

        // Get the latest two readings to calculate consumption
        $latestReading = MeterReading::where('meter_id', $meterId)
            ->latest('reading_date')
            ->first();

        if (!$latestReading) {
            return [];
        }

        // Get the previous reading (second to last)
        $previousReading = MeterReading::where('meter_id', $meterId)
            ->where('reading_date', '<', $latestReading->reading_date)
            ->latest('reading_date')
            ->first();

        // Calculate consumption in units
        $units = 0;
        if ($previousReading) {
            $units = max(0, $latestReading->reading_value - $previousReading->reading_value);
        }

        $rate = $this->chargeCalculator->resolveRate($meter->tariff);
        $amount = $this->chargeCalculator->calculateCharges($units, $meter->tariff)['total'];

        return [[
            'description' => 'Water usage',
            'units_used' => $units,
            'rate' => $rate,
            'amount' => $amount,
            'previous_reading_value' => $previousReading?->reading_value ?? 0,
            'current_reading_value' => $latestReading->reading_value,
        ]];
    }
}
