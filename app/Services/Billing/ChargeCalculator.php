<?php

namespace App\Services\Billing;

use App\Models\Meter;
use App\Models\Tariff;

/**
 * Charge Calculator Service
 *
 * Calculates charges based on consumption and tariff rates.
 * Supports tiered rates, fixed charges, and taxes.
 *
 * @package App\Services\Billing
 */
class ChargeCalculator
{
    /**
     * Calculate charges for consumption
     *
     * @param float $consumption Units consumed
     * @param Tariff $tariff Applicable tariff
     * @param Meter|null $meter Optional meter for fixed charges
     * @return array ['consumption_charge', 'fixed_charge', 'tax', 'total', 'average_rate', 'breakdown']
     */
    public function calculateCharges(float $consumption, Tariff $tariff, ?Meter $meter = null): array
    {
        // Calculate consumption charge using tiered rates
        $consumptionResult = $this->calculateConsumptionCharge($consumption, $tariff);

        // Calculate fixed charge if applicable
        $fixedCharge = $this->calculateFixedCharge($tariff, $meter);

        // Calculate subtotal
        $subtotal = $consumptionResult['total'] + $fixedCharge;

        // Calculate tax
        $tax = $this->calculateTax($subtotal, $tariff);

        // Calculate total
        $total = $subtotal + $tax;

        // Calculate average rate
        $averageRate = $consumption > 0
            ? $consumptionResult['total'] / $consumption
            : 0;

        return [
            'consumption_charge' => $consumptionResult['total'],
            'fixed_charge' => $fixedCharge,
            'tax' => $tax,
            'subtotal' => $subtotal,
            'total' => round($total, 2),
            'average_rate' => round($averageRate, 2),
            'breakdown' => $consumptionResult['breakdown'],
        ];
    }

    /**
     * Calculate consumption charge using tiered rates
     *
     * @param float $consumption
     * @param Tariff $tariff
     * @return array ['total', 'breakdown']
     */
    protected function calculateConsumptionCharge(float $consumption, Tariff $tariff): array
    {
        $rates = $tariff->rates()
            ->orderBy('min_units', 'asc')
            ->get();

        if ($rates->isEmpty()) {
            return [
                'total' => 0,
                'breakdown' => [],
            ];
        }

        $total = 0;
        $breakdown = [];
        $remainingConsumption = $consumption;

        foreach ($rates as $rate) {
            if ($remainingConsumption <= 0) {
                break;
            }

            // Determine units in this tier
            $minUnits = $rate->min_units;
            $maxUnits = $rate->max_units ?? PHP_FLOAT_MAX;

            if ($consumption < $minUnits) {
                // Consumption doesn't reach this tier
                continue;
            }

            // Calculate units in this tier
            $tierUnits = min(
                $remainingConsumption,
                $maxUnits - $minUnits + 1
            );

            if ($tierUnits <= 0) {
                continue;
            }

            // Calculate charge for this tier
            $tierCharge = $tierUnits * $rate->rate_per_unit;
            $total += $tierCharge;

            $breakdown[] = [
                'tier' => $rate->name ?? "Tier {$minUnits}-{$maxUnits}",
                'units' => $tierUnits,
                'rate' => $rate->rate_per_unit,
                'charge' => round($tierCharge, 2),
            ];

            $remainingConsumption -= $tierUnits;
        }

        return [
            'total' => round($total, 2),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Calculate fixed charge (meter charge, service charge, etc.)
     *
     * @param Tariff $tariff
     * @param Meter|null $meter
     * @return float
     */
    protected function calculateFixedCharge(Tariff $tariff, ?Meter $meter = null): float
    {
        $fixedCharge = 0;

        // Base fixed charge from tariff
        if ($tariff->fixed_charge > 0) {
            $fixedCharge += $tariff->fixed_charge;
        }

        // Meter-specific charges could be added here
        // Example: Different fixed charges for bulk vs individual meters
        if ($meter && $meter->isBulkMeter()) {
            // Could apply different fixed charge for bulk meters
        }

        return round($fixedCharge, 2);
    }

    /**
     * Calculate tax on charges
     *
     * @param float $subtotal
     * @param Tariff $tariff
     * @return float
     */
    protected function calculateTax(float $subtotal, Tariff $tariff): float
    {
        if (!$tariff->tax_rate || $tariff->tax_rate <= 0) {
            return 0;
        }

        $tax = $subtotal * ($tariff->tax_rate / 100);

        return round($tax, 2);
    }

    /**
     * Calculate late fee for overdue bill
     *
     * @param float $amount Bill amount
     * @param int $daysOverdue Number of days overdue
     * @return float
     */
    public function calculateLateFee(float $amount, int $daysOverdue): float
    {
        if (!config('billing.late_fees.enabled', true)) {
            return 0;
        }

        $gracePeriod = config('billing.late_fees.grace_period_days', 14);

        if ($daysOverdue <= $gracePeriod) {
            return 0;
        }

        $percentage = config('billing.late_fees.percentage', 5);
        $lateFee = $amount * ($percentage / 100);

        $minFee = config('billing.late_fees.minimum_amount', 50);
        $maxFee = config('billing.late_fees.maximum_amount', 5000);

        $lateFee = max($minFee, min($lateFee, $maxFee));

        return round($lateFee, 2);
    }

    /**
     * Calculate consumption charges for bulk meter distribution
     *
     * @param float $bulkConsumption Total bulk meter consumption
     * @param float $allocationPercentage Sub-meter allocation percentage
     * @param Tariff $tariff
     * @return array
     */
    public function calculateDistributedCharges(
        float $bulkConsumption,
        float $allocationPercentage,
        Tariff $tariff
    ): array {
        $allocatedConsumption = $bulkConsumption * ($allocationPercentage / 100);

        return $this->calculateCharges($allocatedConsumption, $tariff);
    }

    /**
     * Get charge summary for display
     *
     * @param array $charges Result from calculateCharges()
     * @return array
     */
    public function getChargeSummary(array $charges): array
    {
        return [
            'consumption_charge' => number_format($charges['consumption_charge'], 2),
            'fixed_charge' => number_format($charges['fixed_charge'], 2),
            'subtotal' => number_format($charges['subtotal'], 2),
            'tax' => number_format($charges['tax'], 2),
            'total' => number_format($charges['total'], 2),
            'average_rate' => number_format($charges['average_rate'], 2),
            'breakdown' => $charges['breakdown'],
        ];
    }

    /**
     * Resolve the applicable rate for the meter.
     *
     * @return float
     */
    public function resolveRate(Tariff $tariff): float
    {
        $rates = $tariff->rates()
            ->orderBy('min_units', 'asc')
            ->get();
        // For simplicity, we return a fixed rate. In a real application, this could be dynamic based on the meter or tariff.
        return 300; // Example rate per unit
    }
}
