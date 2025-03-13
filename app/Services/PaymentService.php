<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Billing;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class PaymentService
{
    /**
     * Process a new payment for a customer.
     */
    public function processPayment(Customer $customer, float $amount, string $method, ?string $transactionId = null): Payment
    {
        try {
            $payment = Payment::create([
                'customer_id' => $customer->id,
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
     * Reconcile a payment with the customer's outstanding Billing.
     */
    public function reconcilePayment(Payment $payment): bool
    {
        $bill = Billing::where('customer_id', $payment->customer_id)
            ->where('status', 'pending')
            ->first();

        if (!$bill) {
            Log::info("No pending bills for customer ID: {$payment->customer_id}");
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
                'customer_id' => $mpesaData['customer_id'],
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
                'customer_id' => $bankData['customer_id'],
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
    public function sendMpesaStkPush(Customer $customer, float $amount): array
    {
        try {
            $response = Http::post('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest', [
                'BusinessShortCode' => env('MPESA_SHORTCODE'),
                'Password' => base64_encode(env('MPESA_SHORTCODE') . env('MPESA_PASSKEY') . now()->format('YmdHis')),
                'Timestamp' => now()->format('YmdHis'),
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => $amount,
                'PartyA' => $customer->phone,
                'PartyB' => env('MPESA_SHORTCODE'),
                'PhoneNumber' => $customer->phone,
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
     * Get total amount paid by a customer.
     */
    public function getTotalPayments(Customer $customer): float
    {
        return Payment::where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->sum('amount');
    }
}
