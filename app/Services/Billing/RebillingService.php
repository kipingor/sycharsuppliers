<?php

namespace App\Services\Billing;

use App\Models\Billing;
use App\Models\BillingDetail;
use App\Services\Audit\AuditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Rebilling Service
 * 
 * Handles bill adjustments, corrections, and rebilling operations.
 * Manages credit notes, adjustments, and bill modifications.
 * 
 * @package App\Services\Billing
 */
class RebillingService
{
    public function __construct(
        protected BillingService $billingService,
        protected AuditService $auditService,
        protected ChargeCalculator $chargeCalculator
    ) {}

    /**
     * Rebill an account with adjustments
     * 
     * @param Billing $originalBilling
     * @param array $adjustments
     * @param string $reason
     * @return Billing
     */
    public function rebillWithAdjustments(
        Billing $originalBilling,
        array $adjustments,
        string $reason
    ): Billing {
        Log::info('Starting rebilling with adjustments', [
            'original_billing_id' => $originalBilling->id,
            'adjustments' => $adjustments,
            'reason' => $reason,
        ]);

        if (!$originalBilling->canBeModified()) {
            throw new \InvalidArgumentException('Original bill cannot be modified in current status');
        }

        DB::beginTransaction();
        try {
            // Void original bill
            $originalBilling->update([
                'status' => 'voided',
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            // Create new bill with adjustments
            $newBilling = $this->createAdjustedBill($originalBilling, $adjustments);

            // Link bills
            $newBilling->update(['replaced_billing_id' => $originalBilling->id]);

            // Log audit
            $this->auditService->logBillingAction(
                'rebilled',
                $newBilling,
                [
                    'original_billing_id' => $originalBilling->id,
                    'adjustments' => $adjustments,
                    'reason' => $reason,
                ]
            );

            DB::commit();

            Log::info('Rebilling completed successfully', [
                'original_billing_id' => $originalBilling->id,
                'new_billing_id' => $newBilling->id,
            ]);

            return $newBilling;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Rebilling failed', [
                'original_billing_id' => $originalBilling->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create adjusted bill based on original
     * 
     * @param Billing $originalBilling
     * @param array $adjustments
     * @return Billing
     */
    protected function createAdjustedBill(Billing $originalBilling, array $adjustments): Billing
    {
        // Create new billing record
        $newBilling = Billing::create([
            'account_id' => $originalBilling->account_id,
            'billing_period' => $originalBilling->billing_period,
            'total_amount' => 0, // Will be calculated
            'status' => 'pending',
            'issued_at' => now(),
            'due_date' => $originalBilling->due_date,
        ]);

        $totalAmount = 0;

        // Copy and adjust billing details
        foreach ($originalBilling->details as $originalDetail) {
            $detailAdjustments = $adjustments['details'][$originalDetail->id] ?? [];
            
            $adjustedDetail = $this->createAdjustedDetail(
                $newBilling,
                $originalDetail,
                $detailAdjustments
            );

            $totalAmount += $adjustedDetail->amount;
        }

        // Apply global adjustments
        if (isset($adjustments['global'])) {
            $totalAmount = $this->applyGlobalAdjustments($totalAmount, $adjustments['global']);
        }

        // Update total amount
        $newBilling->update(['total_amount' => $totalAmount]);

        return $newBilling;
    }

    /**
     * Create adjusted billing detail
     * 
     * @param Billing $newBilling
     * @param BillingDetail $originalDetail
     * @param array $adjustments
     * @return BillingDetail
     */
    protected function createAdjustedDetail(
        Billing $newBilling,
        BillingDetail $originalDetail,
        array $adjustments
    ): BillingDetail {
        $data = [
            'billing_id' => $newBilling->id,
            'meter_id' => $originalDetail->meter_id,
            'previous_reading_value' => $adjustments['previous_reading_value'] ?? $originalDetail->previous_reading_value,
            'current_reading_value' => $adjustments['current_reading_value'] ?? $originalDetail->current_reading_value,
            'units_used' => 0, // Will be calculated
            'rate' => $adjustments['rate'] ?? $originalDetail->rate,
            'amount' => 0, // Will be calculated
            'description' => $originalDetail->description,
        ];

        // Calculate units
        $data['units_used'] = max(0, $data['current_reading_value'] - $data['previous_reading_value']);

        // Calculate amount
        if (isset($adjustments['amount'])) {
            $data['amount'] = $adjustments['amount'];
        } else {
            $data['amount'] = $data['units_used'] * $data['rate'];
        }

        return BillingDetail::create($data);
    }

    /**
     * Apply global adjustments to total
     * 
     * @param float $totalAmount
     * @param array $adjustments
     * @return float
     */
    protected function applyGlobalAdjustments(float $totalAmount, array $adjustments): float
    {
        // Fixed amount adjustment
        if (isset($adjustments['fixed_amount'])) {
            $totalAmount += $adjustments['fixed_amount'];
        }

        // Percentage adjustment
        if (isset($adjustments['percentage'])) {
            $adjustment = $totalAmount * ($adjustments['percentage'] / 100);
            $totalAmount += $adjustment;
        }

        // Discount
        if (isset($adjustments['discount'])) {
            $totalAmount -= $adjustments['discount'];
        }

        return max(0, $totalAmount);
    }

    /**
     * Apply credit note to a bill
     * 
     * @param Billing $billing
     * @param float $creditAmount
     * @param string $reason
     * @return array
     */
    public function applyCreditNote(Billing $billing, float $creditAmount, string $reason): array
    {
        if ($creditAmount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive');
        }

        if ($creditAmount > $billing->total_amount) {
            throw new \InvalidArgumentException('Credit amount exceeds bill total');
        }

        DB::beginTransaction();
        try {
            // Update billing
            $newTotal = $billing->total_amount - $creditAmount;
            $billing->update(['total_amount' => $newTotal]);

            // Log audit
            $this->auditService->logBillingAction(
                'credit_note_applied',
                $billing,
                [
                    'credit_amount' => $creditAmount,
                    'old_total' => $billing->total_amount + $creditAmount,
                    'new_total' => $newTotal,
                    'reason' => $reason,
                ]
            );

            DB::commit();

            return [
                'success' => true,
                'billing_id' => $billing->id,
                'credit_amount' => $creditAmount,
                'old_total' => $billing->total_amount + $creditAmount,
                'new_total' => $newTotal,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Adjust billing detail
     * 
     * @param BillingDetail $detail
     * @param array $changes
     * @param string $reason
     * @return BillingDetail
     */
    public function adjustBillingDetail(
        BillingDetail $detail,
        array $changes,
        string $reason
    ): BillingDetail {
        $billing = $detail->billing;

        if (!$billing->canBeModified()) {
            throw new \InvalidArgumentException('Bill cannot be modified in current status');
        }

        DB::beginTransaction();
        try {
            $oldAmount = $detail->amount;

            // Update detail
            $detail->update($changes);

            // Recalculate if readings or rate changed
            if (isset($changes['current_reading_value']) || isset($changes['previous_reading_value'])) {
                $detail->units_used = max(0, $detail->current_reading_value - $detail->previous_reading_value);
                $detail->amount = $detail->units_used * $detail->rate;
                $detail->save();
            }

            // Update billing total
            $amountDifference = $detail->amount - $oldAmount;
            $billing->update([
                'total_amount' => $billing->total_amount + $amountDifference,
            ]);

            // Log audit
            $this->auditService->logBillingAction(
                'detail_adjusted',
                $billing,
                [
                    'detail_id' => $detail->id,
                    'changes' => $changes,
                    'old_amount' => $oldAmount,
                    'new_amount' => $detail->amount,
                    'reason' => $reason,
                ]
            );

            DB::commit();

            return $detail;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Waive late fee from a bill
     * 
     * @param Billing $billing
     * @param string $reason
     * @return array
     */
    public function waiveLateFee(Billing $billing, string $reason): array
    {
        if (!$billing->late_fee || $billing->late_fee <= 0) {
            return [
                'success' => false,
                'message' => 'No late fee to waive',
            ];
        }

        DB::beginTransaction();
        try {
            $wavedAmount = $billing->late_fee;
            $newTotal = $billing->total_amount - $wavedAmount;

            $billing->update([
                'late_fee' => 0,
                'total_amount' => $newTotal,
                'late_fee_waived_at' => now(),
                'late_fee_waived_reason' => $reason,
            ]);

            // Log audit
            $this->auditService->logBillingAction(
                'late_fee_waived',
                $billing,
                [
                    'waived_amount' => $wavedAmount,
                    'old_total' => $billing->total_amount + $wavedAmount,
                    'new_total' => $newTotal,
                    'reason' => $reason,
                ]
            );

            DB::commit();

            return [
                'success' => true,
                'waived_amount' => $wavedAmount,
                'new_total' => $newTotal,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calculate rebilling preview
     * 
     * @param Billing $billing
     * @param array $adjustments
     * @return array
     */
    public function previewRebilling(Billing $billing, array $adjustments): array
    {
        $preview = [
            'original_total' => $billing->total_amount,
            'adjusted_total' => 0,
            'difference' => 0,
            'details' => [],
        ];

        $adjustedTotal = 0;

        foreach ($billing->details as $detail) {
            $detailAdjustments = $adjustments['details'][$detail->id] ?? [];
            
            $originalAmount = $detail->amount;
            
            // Calculate adjusted amount
            $adjustedAmount = $originalAmount;
            if (isset($detailAdjustments['amount'])) {
                $adjustedAmount = $detailAdjustments['amount'];
            } elseif (isset($detailAdjustments['current_reading_value']) || isset($detailAdjustments['previous_reading_value'])) {
                $currentReading = $detailAdjustments['current_reading_value'] ?? $detail->current_reading_value;
                $previousReading = $detailAdjustments['previous_reading_value'] ?? $detail->previous_reading_value;
                $units = max(0, $currentReading - $previousReading);
                $rate = $detailAdjustments['rate'] ?? $detail->rate;
                $adjustedAmount = $units * $rate;
            }

            $adjustedTotal += $adjustedAmount;

            $preview['details'][] = [
                'meter_id' => $detail->meter_id,
                'meter_name' => $detail->meter->meter_name,
                'original_amount' => $originalAmount,
                'adjusted_amount' => $adjustedAmount,
                'difference' => $adjustedAmount - $originalAmount,
            ];
        }

        // Apply global adjustments
        if (isset($adjustments['global'])) {
            $adjustedTotal = $this->applyGlobalAdjustments($adjustedTotal, $adjustments['global']);
        }

        $preview['adjusted_total'] = $adjustedTotal;
        $preview['difference'] = $adjustedTotal - $billing->total_amount;

        return $preview;
    }
}