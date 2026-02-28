<?php

namespace App\Contracts\Payment;

use App\Models\Payment;
use App\Models\Billing;
use App\Models\Account;
use Illuminate\Support\Collection;
use App\DTOs\PaymentAllocation;
use App\DTOs\ReconciliationResult;
use App\DTOs\CarryForwardBalance;

interface PaymentReconciliationServiceInterface
{
    /**
     * Reconcile a payment against account bills
     */
    public function reconcilePayment(
        Payment $payment,
        ?array $manualAllocations = null
    ): ReconciliationResult;
    
    /**
     * Allocate payment to oldest bills (FIFO)
     */
    public function allocateToOldestBills(
        Payment $payment
    ): Collection; // Collection of PaymentAllocation
    
    /**
     * Allocate payment to specific bill
     */
    public function allocateToSpecificBill(
        Payment $payment,
        Billing $billing,
        float $amount
    ): PaymentAllocation;
    
    /**
     * Handle partial payment scenario
     */
    public function handlePartialPayment(
        Payment $payment,
        Billing $billing
    ): void;
    
    /**
     * Handle overpayment scenario
     */
    public function handleOverpayment(
        Payment $payment,
        float $excessAmount
    ): CarryForwardBalance;
    
    /**
     * Create carry-forward balance
     */
    public function createCarryForward(
        Account $account,
        float $amount,
        string $reason
    ): CarryForwardBalance;
    
    /**
     * Get current account balance
     */
    public function getAccountBalance(Account $account): array;
    
    /**
     * Generate reconciliation report
     */
    public function generateReconciliationReport(
        Payment $payment
    ): array;
    
    /**
     * Reverse reconciliation (for corrections)
     */
    public function reverseReconciliation(
        Payment $payment,
        string $reason
    ): void;
}
