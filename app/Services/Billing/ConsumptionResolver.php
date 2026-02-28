<?php

namespace App\Services\Billing;

use App\Models\Account;
use App\Models\MeterReading;
use Carbon\Carbon;

class ConsumptionResolver
{
    /**
     * Resolve total consumption for an account during a billing period.
     *
     * @param int $accountId
     * @param string $billingPeriod Format: 'YYYY-MM'
     * @return float Total consumption in units
     */
    public function resolveForPeriod(
        int $accountId,
        string $billingPeriod
    ): float {
        $account = Account::findOrFail($accountId);

        // Parse billing period
        $period = Carbon::createFromFormat('Y-m', $billingPeriod);
        $periodStart = $period->copy()->startOfMonth();
        $periodEnd = $period->copy()->endOfMonth();

        // Aggregate consumption across all meters for this account
        $totalConsumption = 0;

        foreach ($account->meters as $meter) {
            $consumption = $this->resolveForMeter($meter->id, $periodStart, $periodEnd);
            $totalConsumption += $consumption;
        }

        return $totalConsumption;
    }

    /**
     * Resolve consumption for a single meter during a period.
     *
     * @param int $meterId
     * @param Carbon $periodStart
     * @param Carbon $periodEnd
     * @return float Consumption in units
     */
    public function resolveForMeter(int $meterId, Carbon $periodStart, Carbon $periodEnd): float
    {
        // Get the reading at the start of the period (or latest before)
        $startReading = MeterReading::where('meter_id', $meterId)
            ->where('reading_date', '<=', $periodStart)
            ->latest('reading_date')
            ->first();

        // Get the reading at the end of the period (or latest before)
        $endReading = MeterReading::where('meter_id', $meterId)
            ->where('reading_date', '<=', $periodEnd)
            ->latest('reading_date')
            ->first();

        // If no readings exist, return 0
        if (!$startReading || !$endReading) {
            return 0;
        }

        // If both readings are the same, return the consumption field if available
        if ($startReading->id === $endReading->id) {
            return (float) ($endReading->consumption ?? 0);
        }

        // Otherwise, calculate difference
        $consumption = max(0, $endReading->current_reading - $startReading->current_reading);

        return (float) $consumption;
    }
}