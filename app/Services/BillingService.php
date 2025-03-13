<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\Customer;
use App\Models\MeterReading;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

class BillingService
{
    protected float $unitPrice;

    public function __construct()
    {
        // Fetch unit price from config or use default
        $this->unitPrice = config('water_billing_price.unit_price', 300);
    }

    /**
     * Generate a bill for a customer based on the latest meter reading.
     */
    public function generateBill(Customer $customer): ?Billnig
    {
        try {
            $latestReading = MeterReading::where('customer_id', $customer->id)
                ->latest()
                ->first();

            $previousReading = MeterReading::where('customer_id', $customer->id)
                ->orderBy('created_at', 'desc')
                ->skip(1)
                ->first();

            if (!$latestReading || !$previousReading) {
                Log::warning("Insufficient readings for billing. Customer ID: {$customer->id}");
                return null;
            }

            $unitsUsed = $latestReading->reading - $previousReading->reading;
            $amountDue = $unitsUsed * $this->unitPrice;

            return Billing::create([
                'customer_id' => $customer->id,
                'billing_period' => Carbon::now()->format('Y-m'),
                'units_used' => $unitsUsed,
                'amount_due' => $amountDue,
                'status' => 'pending',
            ]);
        } catch (Exception $e) {
            Log::error("Failed to generate bill: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Reconcile a payment with a customer's bill.
     */
    public function reconcilePayment(Customer $customer, float $amountPaid): bool
    {
        $outstandingBill = Billing::where('customer_id', $customer->id)
            ->where('status', 'pending')
            ->first();

        if (!$outstandingBill) {
            Log::info("No pending bills for customer ID: {$customer->id}");
            return false;
        }

        if ($amountPaid < $outstandingBill->amount_due) {
            Log::warning("Payment insufficient for customer ID: {$customer->id}");
            return false;
        }

        $outstandingBill->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return true;
    }

    /**
     * Apply late fees to overdue bills.
     */
    public function applyLateFees(): void
    {
        $overdueBills = Bill::where('status', 'pending')
            ->where('created_at', '<', Carbon::now()->subDays(30))
            ->get();

        foreach ($overdueBills as $bill) {
            $lateFee = $bill->amount_due * 0.05; // 5% late fee
            $bill->update(['amount_due' => $bill->amount_due + $lateFee]);

            Log::info("Late fee applied: {$lateFee} to bill ID: {$bill->id}");
        }
    }

    /**
     * Automatically generate bills for all customers at the start of the month.
     */
    public function autoGenerateMonthlyBills(): void
    {
        $customers = Customer::all();

        foreach ($customers as $customer) {
            $this->generateBill($customer);
        }

        Log::info("Monthly billing completed for all customers.");
    }
}
