<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Payment Policy
 * 
 * Handles authorization for payment operations.
 * 
 * @package App\Policies
 */
class PaymentPolicy
{
    /**
     * Determine whether the user can view any payments.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission('process-payments');
    }

    /**
     * Determine whether the user can view the payment.
     */
    public function view(User $user, Payment $payment): bool
    {
        return $user->hasAnyPermission('process-payments');
    }

    /**
     * Determine whether the user can create payments.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission('process-payments');
    }

    /**
     * Determine whether the user can update the payment.
     */
    public function update(User $user, Payment $payment): bool
    {
        // Cannot update reconciled payments
        if ($payment->isReconciled()) {
            return false;
        }

        return $user->hasAnyPermission('process-payments');
    }

    /**
     * Determine whether the user can delete the payment.
     */
    public function delete(User $user, Payment $payment): bool
    {
        // Cannot delete payments with allocations
        if ($payment->allocations()->exists()) {
            return false;
        }

        // Cannot delete reconciled payments
        if ($payment->isReconciled()) {
            return false;
        }

        return $user->hasAnyPermission('process-payments');
    }

    /**
     * Determine whether the user can reconcile the payment.
     */
    public function reconcile(User $user, Payment $payment): bool
    {
        // Payment must be completed and not already reconciled
        if (!$payment->canBeReconciled()) {
            return false;
        }

        return $user->hasAnyPermission('process-payments');
    }

    /**
     * Determine whether the user can view reconciliation details.
     */
    public function viewReconciliation(User $user, Payment $payment): bool
    {
        return $user->hasAnyPermission('process-payments');
    }

    /**
     * Determine whether the user can reverse payment reconciliation.
     */
    public function reverseReconciliation(User $user, Payment $payment): bool
    {
        // Check if payment is reconciled
        if (!$payment->isReconciled()) {
            return false;
        }

        // Check config for who can reverse
        $whoCanReverse = config('reconciliation.reversal.who_can_reverse', 'admin_only');

        switch ($whoCanReverse) {
            case 'anyone':
                return $user->hasRole('admin');

            case 'same_user':
                return $payment->reconciled_by === $user->id
                    || $user->hasRole('admin');

            case 'admin_only':
            default:
                return $user->hasRole('admin');
        }
    }

    /**
     * Determine whether the user can export payments.
     */
    public function export(User $user): bool
    {
        return $user->hasAnyPermission('process-payments');
    }

    /**
     * Determine whether the user can bulk reconcile payments.
     */
    public function bulkReconcile(User $user): bool
    {
        return $user->hasAnyPermission('process-payments');
    }
}