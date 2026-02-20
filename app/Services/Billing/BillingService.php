<?php

namespace App\Services\Billing;

use App\Events\Billing\BillGenerated;
use App\Models\Account;
use App\Models\Billing;
use App\Models\BillingDetail;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Services\Audit\AuditService;
use App\Services\Billing\ChargeCalculator;
use App\Services\Tariff\TariffResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Billing Service
 * 
 * Core service for bill generation.
 * Handles single account bill generation with full business logic.
 * 
 * @package App\Services\Billing
 */
class BillingService
{
    public function __construct(
        protected AuditService $auditService,
        protected ChargeCalculator $chargeCalculator,
        protected TariffResolver $tariffResolver
    ) {}

    /**
     * Generate bill for an account for a specific period
     * 
     * @param Account $account
     * @param string $billingPeriod Format: Y-m
     * @return Billing
     * @throws \Exception
     */
    public function generateForAccount(Account $account, string $billingPeriod): Billing
    {
        Log::info('Generating bill for account', [
            'account_id' => $account->id,
            'billing_period' => $billingPeriod,
        ]);

        // Validate account
        if (!$account->isActive()) {
            throw new \InvalidArgumentException('Account is not active');
        }

        // Get active meters
        $meters = $account->meters()->active()->get();

        if ($meters->isEmpty()) {
            throw new \InvalidArgumentException('Account has no active meters');
        }

        // Check for duplicate bill
        if (config('billing.generation.prevent_duplicates', true)) {
            $existing = Billing::where('account_id', $account->id)
                ->where('billing_period', $billingPeriod)
                ->whereNotIn('status', ['voided'])
                ->first();

            if ($existing) {
                throw new \InvalidArgumentException("Bill already exists for this period (Bill #{$existing->id})");
            }
        }

        DB::beginTransaction();
        try {
            // Create billing record
            $periodDate = Carbon::createFromFormat('Y-m', $billingPeriod);
            $dueDate = now()->addDays(config('billing.generation.due_days', 14));

            $billing = Billing::create([
                'account_id' => $account->id,
                'billing_period' => $billingPeriod,
                'total_amount' => 0, // Will be calculated
                'amount_due' => 0, // Will be calculated    
                'status' => 'pending',
                'issued_at' => now(),
                'due_date' => $dueDate,
            ]);

            // Generate billing details for each meter
            $totalAmount = 0;

            foreach ($meters as $meter) {
                try {
                    $detail = $this->generateMeterBillingDetail($meter, $billing, $periodDate);
                    if ($detail) {
                        $totalAmount += $detail->amount;
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to generate billing detail for meter', [
                        'meter_id' => $meter->id,
                        'billing_id' => $billing->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with other meters
                }
            }

            // Update total amount
            $billing->update(['total_amount' => $totalAmount, 'amount_due' => $totalAmount]);

            // Log audit
            $this->auditService->logBillingAction(
                'generated',
                $billing,
                [
                    'billing_period' => $billingPeriod,
                    'meter_count' => $meters->count(),
                    'total_amount' => $totalAmount,
                    'amount_due' => $totalAmount,
                ]
            );

            DB::commit();

            // Dispatch event
            event(new BillGenerated($billing));

            Log::info('Bill generated successfully', [
                'billing_id' => $billing->id,
                'account_id' => $account->id,
                'total_amount' => $totalAmount,
                'amount_due' => $totalAmount,
            ]);

            return $billing;
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to generate bill', [
                'account_id' => $account->id,
                'billing_period' => $billingPeriod,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate billing detail for a specific meter
     * 
     * @param Meter $meter
     * @param Billing $billing
     * @param Carbon $periodDate
     * @return BillingDetail|null
     */
    protected function generateMeterBillingDetail(Meter $meter, Billing $billing, Carbon $periodDate): ?BillingDetail
    {
        // Get meter readings for the period
        $currentReading = $this->getCurrentReading($meter, $periodDate);
        $previousReading = $this->getPreviousReading($meter, $periodDate);

        if (!$currentReading) {
            Log::warning('No current reading found for meter', [
                'meter_id' => $meter->id,
                'period' => $periodDate->format('Y-m'),
            ]);

            // Use estimation if enabled
            if (config('billing.estimation.enabled', true)) {
                $currentReading = $this->estimateReading($meter, $previousReading, $periodDate);
            } else {
                return null;
            }
        }

        if (!$previousReading) {
            // First bill for this meter - use current reading as starting point
            $consumption = 0;
            $previousReadingValue = $currentReading->reading_value;
        } else {
            $consumption = max(0, $currentReading->reading_value - $previousReading->reading_value);
            $previousReadingValue = $previousReading->reading_value;
        }

        // Skip if consumption is below minimum and zero bills not allowed
        if ($consumption < config('billing.generation.minimum_consumption', 0) 
            && !config('billing.generation.include_zero_bills', false)) {
            Log::info('Skipping meter with zero/minimal consumption', [
                'meter_id' => $meter->id,
                'consumption' => $consumption,
            ]);
            return null;
        }

        // Get applicable tariff
        $tariff = $this->tariffResolver->getTariffForMeter($meter, $periodDate);

        if (!$tariff) {
            throw new \Exception("No tariff found for meter #{$meter->meter_number}");
        }

        // Calculate charges
        $charges = $this->chargeCalculator->calculateCharges(
            $consumption,
            $tariff,
            $meter
        );

        // Create billing detail
        $description = $currentReading->isEstimated() 
            ? "Estimated consumption for {$meter->meter_name}"
            : "Consumption for {$meter->meter_name}";

        $detail = BillingDetail::create([
            'billing_id' => $billing->id,
            'meter_id' => $meter->id,
            'previous_reading_value' => $previousReadingValue,
            'current_reading_value' => $currentReading->reading_value,
            'units_used' => $consumption,
            'rate' => $charges['average_rate'],
            'amount' => $charges['total'],
            'description' => $description,
        ]);

        Log::info('Billing detail created', [
            'billing_detail_id' => $detail->id,
            'meter_id' => $meter->id,
            'consumption' => $consumption,
            'amount' => $charges['total'],
        ]);

        return $detail;
    }

    /**
     * Get current reading for meter in period
     */
    protected function getCurrentReading(Meter $meter, Carbon $periodDate): ?MeterReading
    {
        return MeterReading::where('meter_id', $meter->id)
            ->whereYear('reading_date', $periodDate->year)
            ->whereMonth('reading_date', $periodDate->month)
            ->latest('reading_date')
            ->first();
    }

    /**
     * Get previous reading (last reading before current period)
     */
    protected function getPreviousReading(Meter $meter, Carbon $periodDate): ?MeterReading
    {
        $startOfPeriod = $periodDate->copy()->startOfMonth();

        return MeterReading::where('meter_id', $meter->id)
            ->where('reading_date', '<', $startOfPeriod)
            ->latest('reading_date')
            ->first();
    }

    /**
     * Estimate reading if actual reading not available
     */
    protected function estimateReading(Meter $meter, ?MeterReading $previousReading, Carbon $periodDate): MeterReading
    {
        $method = config('billing.estimation.method', 'average');

        $estimatedValue = match($method) {
            'average' => $this->estimateByAverage($meter, $previousReading),
            'last_reading' => $this->estimateByLastReading($meter, $previousReading),
            'seasonal' => $this->estimateBySeason($meter, $previousReading, $periodDate),
            default => $previousReading?->reading ?? 0,
        };

        // Create estimated reading
        $reading = MeterReading::create([
            'meter_id' => $meter->id,
            'reading' => $estimatedValue,
            'reading_date' => $periodDate->endOfMonth(),
            'reading_type' => 'estimated',
            'notes' => "Estimated using {$method} method",
        ]);

        Log::info('Reading estimated', [
            'meter_id' => $meter->id,
            'estimated_value' => $estimatedValue,
            'method' => $method,
        ]);

        return $reading;
    }

    /**
     * Estimate by average consumption
     */
    protected function estimateByAverage(Meter $meter, ?MeterReading $previousReading): float
    {
        $months = config('billing.estimation.average_months', 3);
        $avgConsumption = $meter->getAverageMonthlyConsumption($months);

        return ($previousReading?->reading_value ?? 0) + $avgConsumption;
    }

    /**
     * Estimate by repeating last reading
     */
    protected function estimateByLastReading(Meter $meter, ?MeterReading $previousReading): float
    {
        return $previousReading?->reading_value ?? 0;
    }

    /**
     * Estimate based on seasonal patterns
     */
    protected function estimateBySeason(Meter $meter, ?MeterReading $previousReading, Carbon $periodDate): float
    {
        // Simplified seasonal estimation
        // In production, this would use historical seasonal patterns
        $avgConsumption = $meter->getAverageMonthlyConsumption(3);
        
        // Adjust by season (example: +20% in summer, -10% in winter)
        $month = $periodDate->month;
        $seasonalFactor = match(true) {
            in_array($month, [6, 7, 8]) => 1.2,  // Summer
            in_array($month, [12, 1, 2]) => 0.9,  // Winter
            default => 1.0,
        };

        $adjustedConsumption = $avgConsumption * $seasonalFactor;

        return ($previousReading?->reading_value ?? 0) + $adjustedConsumption;
    }

    /**
     * Generate bill for bulk meter and sub-meters
     */
    public function generateForBulkMeter(Meter $bulkMeter, string $billingPeriod): array
    {
        $bulkMeterService = app(BulkMeterService::class);
        return $bulkMeterService->generateBulkMeterBill($bulkMeter, $billingPeriod);
    }
}
