<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Billing;
use App\Models\Resident;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class PaymentService
{
    /**
     * Process a new payment for a resident.
     */
    public function processPayment(Resident $resident, float $amount, string $method, ?string $transactionId = null): Payment
    {
        try {
            $payment = Payment::create([
                'resident_id' => $resident->id,
                'amount' => $amount,
                'method' => $method,
                'transaction_id' => $transactionId,
                'status' => 'pending',
            ]);

            return $payment;
        } catch (Exception $e) {
            Log::error("Payment processing failed: " . $e->getMessage());
            throw new Exception("Failed to process payment.");
        }
    }

    /**
     * Reconcile a payment with the resident's outstanding Billing.
     */
    public function reconcilePayment(Payment $payment): bool
    {
        $bill = Billing::where('resident_id', $payment->resident_id)
            ->where('status', 'pending')
            ->first();

        if (!$bill) {
            Log::info("No pending bills for resident ID: {$payment->resident_id}");
            return false;
        }

        if ($payment->amount < $bill->amount_due) {
            Log::warning("Payment amount is less than the due bill.");
            return false;
        }

        $payment->update(['status' => 'completed']);
        $bill->update(['status' => 'paid', 'paid_at' => now()]);

        return true;
    }

    /**
     * Handle M-Pesa payment confirmation (STK Push or C2B Webhook).
     */
    public function handleMpesaPayment(array $mpesaData): Payment
    {
        try {
            $payment = Payment::create([
                'resident_id' => $mpesaData['resident_id'],
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
     * Handle NCBA bank payment confirmation.
     */
    public function handleBankPayment(array $bankData): Payment
    {
        try {
            $payment = Payment::create([
                'resident_id' => $bankData['resident_id'],
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

    /**
     * Send M-Pesa STK Push for a payment request.
     */
    public function sendMpesaStkPush(Resident $resident, float $amount): array
    {
        try {
            $response = Http::post('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest', [
                'BusinessShortCode' => env('MPESA_SHORTCODE'),
                'Password' => base64_encode(env('MPESA_SHORTCODE') . env('MPESA_PASSKEY') . now()->format('YmdHis')),
                'Timestamp' => now()->format('YmdHis'),
                'TransactionType' => 'ResidentPayBillOnline',
                'Amount' => $amount,
                'PartyA' => $resident->phone,
                'PartyB' => env('MPESA_SHORTCODE'),
                'PhoneNumber' => $resident->phone,
                'CallBackURL' => env('MPESA_CALLBACK_URL'),
                'AccountReference' => 'Water Billing Payment',
                'TransactionDesc' => 'Water Bill Payment',
            ]);

            return $response->json();
        } catch (Exception $e) {
            Log::error("M-Pesa STK Push failed: " . $e->getMessage());
            throw new Exception("Failed to initiate M-Pesa STK Push.");
        }
    }

    /**
     * Get total amount paid by a resident.
     */
    public function getTotalPayments(Resident $resident): float
    {
        return Payment::where('resident_id', $resident->id)
            ->where('status', 'completed')
            ->sum('amount');
    }
}
