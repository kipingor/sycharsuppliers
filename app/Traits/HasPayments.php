<?php

namespace App\Traits;

use App\Models\Payment;
use App\Models\Billing;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

trait HasPayments
{
    /**
     * Establish a one-to-many relationship with payments.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Process a new payment for the user/meter.
     */
    public function processPayment(float $amount, string $method, string $transactionId = null): Payment
    {
        try {
            return $this->payments()->create([
                'amount' => $amount,
                'method' => $method,
                'transaction_id' => $transactionId,
                'status' => 'pending',
            ]);
        } catch (Exception $e) {
            Log::error("Payment processing failed: " . $e->getMessage());
            throw new Exception("Failed to process payment.");
        }
    }

    /**
     * Reconcile a payment with an outstanding bill.
     */
    public function reconcilePayment(Payment $payment): bool
    {
        $outstandingBill = $this->bills()->where('status', 'pending')->first();

        if (!$outstandingBill) {
            Log::info("No pending bills for user ID: {$this->id}");
            return false;
        }

        if ($payment->amount < $outstandingBill->amount_due) {
            Log::warning("Payment amount is less than the due bill.");
            return false;
        }

        $payment->update(['status' => 'completed']);
        $outstandingBill->update(['status' => 'paid', 'paid_at' => now()]);

        return true;
    }

    /**
     * Get the latest payment made by the user/meter.
     */
    public function latestPayment(): ?Payment
    {
        return $this->payments()->latest()->first();
    }

    /**
     * Get total amount paid by the user/meter.
     */
    public function totalAmountPaid(): float
    {
        return $this->payments()->where('status', 'completed')->sum('amount');
    }
    
    /**
     * Handle M-Pesa payment confirmation (Webhook Processing).
     */
    public function handleMpesaPayment(array $mpesaData): Payment
    {
        try {
            $payment = $this->payments()->create([
                'amount' => $mpesaData['amount'],
                'method' => 'M-Pesa',
                'transaction_id' => $mpesaData['transaction_id'],
                'status' => 'completed',
            ]);

            $this->reconcilePayment($payment);

            return $payment;
        } catch (Exception $e) {
            Log::error("M-Pesa payment processing failed: " . $e->getMessage());
            throw new Exception("Failed to process M-Pesa payment.");
        }
    }

    /**
     * Handle bank payment confirmation from NCBA API.
     */
    public function handleBankPayment(array $bankData): Payment
    {
        try {
            $payment = $this->payments()->create([
                'amount' => $bankData['amount'],
                'method' => 'Bank Transfer',
                'transaction_id' => $bankData['reference_number'],
                'status' => 'completed',
            ]);

            $this->reconcilePayment($payment);

            return $payment;
        } catch (Exception $e) {
            Log::error("Bank payment processing failed: " . $e->getMessage());
            throw new Exception("Failed to process bank payment.");
        }
    }
}
