<?php

namespace App\Traits;

use App\Models\Billing;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

trait HasBills
{
    /**
     * Establish a one-to-many relationship with bills.
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Billing::class);
    }

    /**
     * Generate a new bill for the current resident/meter.
     */
    public function generateBill(float $unitPrice = 300): Bill
    {
        $latestReading = $this->meterReadings()->latest()->first();
        $previousReading = $this->meterReadings()->orderBy('created_at', 'desc')->skip(1)->first();

        if (!$latestReading || !$previousReading) {
            throw new \Exception("Not enough meter readings to generate a bill.");
        }

        $unitsUsed = $latestReading->reading - $previousReading->reading;
        $amountDue = $unitsUsed * $unitPrice;

        return $this->bills()->create([
            'billing_period' => Carbon::now()->format('Y-m'),
            'units_used' => $unitsUsed,
            'amount_due' => $amountDue,
            'status' => 'pending',
        ]);
    }

    /**
     * Mark a bill as paid.
     */
    public function markBillAsPaid(Billing $billing): bool
    {
        if ($billing->status === 'paid') {
            return false;
        }

        return $billing->update(['status' => 'paid', 'paid_at' => now()]);
    }

    /**
     * Get the latest unpaid billing.
     */
    public function latestUnpaidBill(): ?Billing
    {
        return $this->bills()->where('status', 'pending')->latest()->first();
    }

    /**
     * Get total outstanding balance.
     */
    public function totalOutstandingBalance(): float
    {
        return $this->bills()->where('status', 'pending')->sum('amount_due');
    }
}
