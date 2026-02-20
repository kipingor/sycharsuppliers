<?php

namespace App\Services\Billing;

use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Billing;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bulk Meter Service
 * 
 * Handles operations specific to bulk meters including:
 * - Distribution of bulk meter readings to sub-meters
 * - Allocation calculation based on percentages
 * - Coordinated bill generation for bulk meters
 * 
 * @package App\Services\Billing
 */
class BulkMeterService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Distribute bulk meter reading to sub-meters
     * 
     * Takes a reading from a bulk meter and distributes it proportionally
     * to all sub-meters based on their allocation percentages.
     * 
     * @param MeterReading $bulkReading The reading from the bulk meter
     * @return array Array of created sub-meter readings
     * @throws \Exception
     */
    public function distributeBulkReading(MeterReading $bulkReading): array
    {
        $bulkMeter = $bulkReading->meter;

        if (!$bulkMeter->isBulkMeter()) {
            throw new \InvalidArgumentException('Meter is not a bulk meter');
        }

        if (!$bulkMeter->hasSubMeters()) {
            throw new \InvalidArgumentException('Bulk meter has no sub-meters');
        }

        $subMeters = $bulkMeter->subMeters()->active()->get();

        if ($subMeters->isEmpty()) {
            throw new \InvalidArgumentException('Bulk meter has no active sub-meters');
        }

        // Get previous bulk reading to calculate consumption
        $previousBulkReading = MeterReading::where('meter_id', $bulkMeter->id)
            ->where('reading_date', '<', $bulkReading->reading_date)
            ->latest('reading_date')
            ->first();

        if (!$previousBulkReading) {
            throw new \InvalidArgumentException('No previous bulk meter reading found for consumption calculation');
        }

        $bulkConsumption = $bulkReading->reading - $previousBulkReading->reading;

        if ($bulkConsumption < 0) {
            throw new \InvalidArgumentException('Bulk meter reading is less than previous reading');
        }

        $distributedReadings = [];

        DB::beginTransaction();
        try {
            foreach ($subMeters as $subMeter) {
                // Calculate allocated consumption
                $allocatedConsumption = $this->calculateSubMeterAllocation(
                    $bulkConsumption,
                    $subMeter->allocation_percentage
                );

                // Get previous sub-meter reading
                $previousSubReading = MeterReading::where('meter_id', $subMeter->id)
                    ->where('reading_date', '<', $bulkReading->reading_date)
                    ->latest('reading_date')
                    ->first();

                // Calculate new reading value
                $newReading = $previousSubReading 
                    ? $previousSubReading->reading + $allocatedConsumption
                    : $allocatedConsumption;

                // Create sub-meter reading
                $subReading = MeterReading::create([
                    'meter_id' => $subMeter->id,
                    'reading' => $newReading,
                    'reading_date' => $bulkReading->reading_date,
                    'reader_id' => $bulkReading->reader_id,
                    'reading_type' => 'calculated',
                    'notes' => "Distributed from bulk meter #{$bulkMeter->meter_number}. Allocation: {$subMeter->allocation_percentage}%",
                    'parent_reading_id' => $bulkReading->id,
                ]);

                $distributedReadings[] = $subReading;

                // Audit log
                $this->auditService->logMeterAction(
                    'reading_distributed',
                    $subMeter,
                    [
                        'bulk_meter_id' => $bulkMeter->id,
                        'bulk_reading_id' => $bulkReading->id,
                        'allocated_consumption' => $allocatedConsumption,
                        'allocation_percentage' => $subMeter->allocation_percentage,
                    ]
                );
            }

            // Mark bulk reading as distributed
            $bulkReading->update([
                'is_distributed' => true,
                'distributed_at' => now(),
            ]);

            DB::commit();

            Log::info('Bulk meter reading distributed', [
                'bulk_meter_id' => $bulkMeter->id,
                'bulk_reading_id' => $bulkReading->id,
                'bulk_consumption' => $bulkConsumption,
                'sub_meter_count' => count($distributedReadings),
            ]);

            return $distributedReadings;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to distribute bulk meter reading', [
                'bulk_meter_id' => $bulkMeter->id,
                'bulk_reading_id' => $bulkReading->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculate sub-meter allocation from bulk consumption
     * 
     * @param float $bulkConsumption Total consumption from bulk meter
     * @param float $allocationPercentage Percentage allocated to sub-meter
     * @return float Allocated consumption
     */
    public function calculateSubMeterAllocation(float $bulkConsumption, float $allocationPercentage): float
    {
        if ($allocationPercentage < 0 || $allocationPercentage > 100) {
            throw new \InvalidArgumentException('Allocation percentage must be between 0 and 100');
        }

        return round($bulkConsumption * ($allocationPercentage / 100), 2);
    }

    /**
     * Generate bills for bulk meter and all sub-meters
     * 
     * Creates coordinated bills that show the relationship between
     * the bulk meter and its sub-meters.
     * 
     * @param Meter $bulkMeter
     * @param string $billingPeriod Format: Y-m
     * @return array ['bulk_bill' => Billing, 'sub_bills' => Billing[]]
     * @throws \Exception
     */
    public function generateBulkMeterBill(Meter $bulkMeter, string $billingPeriod): array
    {
        if (!$bulkMeter->isBulkMeter()) {
            throw new \InvalidArgumentException('Meter is not a bulk meter');
        }

        if (!$bulkMeter->hasSubMeters()) {
            throw new \InvalidArgumentException('Bulk meter has no sub-meters');
        }

        $subMeters = $bulkMeter->subMeters()->active()->get();

        DB::beginTransaction();
        try {
            $subBills = [];

            // Generate bills for each sub-meter first
            foreach ($subMeters as $subMeter) {
                // This would call the regular BillingService->generateForMeter()
                // but we'll create a simplified version here
                $subBill = $this->generateSubMeterBill($subMeter, $billingPeriod, $bulkMeter);
                $subBills[] = $subBill;
            }

            // Generate summary bill for bulk meter (optional, based on config)
            $bulkBill = null;
            if (config('billing.bulk_meters.generate_summary_bill', true)) {
                $bulkBill = $this->generateBulkSummaryBill($bulkMeter, $billingPeriod, $subBills);
            }

            DB::commit();

            Log::info('Bulk meter bills generated', [
                'bulk_meter_id' => $bulkMeter->id,
                'billing_period' => $billingPeriod,
                'sub_bill_count' => count($subBills),
                'bulk_bill_id' => $bulkBill?->id,
            ]);

            return [
                'bulk_bill' => $bulkBill,
                'sub_bills' => $subBills,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to generate bulk meter bills', [
                'bulk_meter_id' => $bulkMeter->id,
                'billing_period' => $billingPeriod,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate bill for a sub-meter
     * 
     * @param Meter $subMeter
     * @param string $billingPeriod
     * @param Meter $bulkMeter
     * @return Billing
     */
    protected function generateSubMeterBill(Meter $subMeter, string $billingPeriod, Meter $bulkMeter): Billing
    {
        // This is a simplified version - in production, this would call BillingService
        // For now, we'll create a placeholder
        
        $account = $subMeter->account;
        
        $billing = Billing::create([
            'account_id' => $account->id,
            'billing_period' => $billingPeriod,
            'total_amount' => 0, // Will be calculated by BillingService
            'status' => 'pending',
            'issued_at' => now(),
            'due_date' => now()->addDays(config('billing.generation.due_days', 14)),
        ]);

        // Log audit
        $this->auditService->logBillingAction(
            'generated_from_bulk',
            $billing,
            [
                'sub_meter_id' => $subMeter->id,
                'bulk_meter_id' => $bulkMeter->id,
                'allocation_percentage' => $subMeter->allocation_percentage,
            ]
        );

        return $billing;
    }

    /**
     * Generate summary bill for bulk meter
     * 
     * @param Meter $bulkMeter
     * @param string $billingPeriod
     * @param array $subBills
     * @return Billing
     */
    protected function generateBulkSummaryBill(Meter $bulkMeter, string $billingPeriod, array $subBills): Billing
    {
        $account = $bulkMeter->account;
        
        // Calculate total from sub-bills
        $totalAmount = collect($subBills)->sum('total_amount');
        
        $billing = Billing::create([
            'account_id' => $account->id,
            'billing_period' => $billingPeriod,
            'total_amount' => $totalAmount,
            'status' => 'pending',
            'issued_at' => now(),
            'due_date' => now()->addDays(config('billing.generation.due_days', 14)),
            'is_bulk_summary' => true,
        ]);

        // Log audit
        $this->auditService->logBillingAction(
            'bulk_summary_generated',
            $billing,
            [
                'bulk_meter_id' => $bulkMeter->id,
                'sub_bill_count' => count($subBills),
                'sub_bill_ids' => collect($subBills)->pluck('id')->toArray(),
            ]
        );

        return $billing;
    }

    /**
     * Validate bulk meter setup
     * 
     * Ensures bulk meter and its sub-meters are properly configured.
     * 
     * @param Meter $bulkMeter
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    public function validateBulkMeterSetup(Meter $bulkMeter): array
    {
        $errors = [];

        if (!$bulkMeter->isBulkMeter()) {
            $errors[] = 'Meter is not configured as a bulk meter';
        }

        if (!$bulkMeter->hasSubMeters()) {
            $errors[] = 'Bulk meter has no sub-meters';
        }

        $subMeters = $bulkMeter->subMeters()->get();
        
        if ($subMeters->isEmpty()) {
            $errors[] = 'No sub-meters found';
        }

        // Check allocation totals 100%
        if (config('billing.bulk_meters.require_full_allocation', true)) {
            if (!$bulkMeter->hasCompleteAllocation()) {
                $total = $bulkMeter->getTotalSubMeterAllocation();
                $errors[] = "Sub-meter allocations total {$total}%, must equal 100%";
            }
        }

        // Check for inactive sub-meters
        $inactiveCount = $subMeters->where('status', '!=', 'active')->count();
        if ($inactiveCount > 0) {
            $errors[] = "{$inactiveCount} sub-meter(s) are not active";
        }

        // Check for duplicate allocations
        foreach ($subMeters as $subMeter) {
            if ($subMeter->allocation_percentage <= 0) {
                $errors[] = "Sub-meter #{$subMeter->meter_number} has invalid allocation: {$subMeter->allocation_percentage}%";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'total_allocation' => $bulkMeter->getTotalSubMeterAllocation(),
            'sub_meter_count' => $subMeters->count(),
            'active_sub_meter_count' => $subMeters->where('status', 'active')->count(),
        ];
    }

    /**
     * Adjust sub-meter allocations
     * 
     * Updates allocation percentages for sub-meters.
     * Validates that total equals 100%.
     * 
     * @param Meter $bulkMeter
     * @param array $allocations ['meter_id' => percentage, ...]
     * @return bool
     * @throws \Exception
     */
    public function adjustSubMeterAllocations(Meter $bulkMeter, array $allocations): bool
    {
        if (!$bulkMeter->isBulkMeter()) {
            throw new \InvalidArgumentException('Meter is not a bulk meter');
        }

        // Validate total equals 100%
        $total = array_sum($allocations);
        if (config('billing.bulk_meters.require_full_allocation', true)) {
            if (abs($total - 100) > 0.01) {
                throw new \InvalidArgumentException("Allocations total {$total}%, must equal 100%");
            }
        }

        DB::beginTransaction();
        try {
            foreach ($allocations as $meterId => $percentage) {
                $subMeter = Meter::findOrFail($meterId);
                
                if ($subMeter->parent_meter_id !== $bulkMeter->id) {
                    throw new \InvalidArgumentException("Meter #{$meterId} is not a sub-meter of bulk meter #{$bulkMeter->id}");
                }

                $oldPercentage = $subMeter->allocation_percentage;
                $subMeter->update(['allocation_percentage' => $percentage]);

                // Audit log
                $this->auditService->logMeterAction(
                    'allocation_adjusted',
                    $subMeter,
                    [
                        'old_percentage' => $oldPercentage,
                        'new_percentage' => $percentage,
                        'bulk_meter_id' => $bulkMeter->id,
                    ]
                );
            }

            DB::commit();

            Log::info('Sub-meter allocations adjusted', [
                'bulk_meter_id' => $bulkMeter->id,
                'allocations' => $allocations,
                'total' => $total,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to adjust sub-meter allocations', [
                'bulk_meter_id' => $bulkMeter->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}