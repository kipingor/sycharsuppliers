<?php

namespace App\Policies;

use App\Models\Billing;
use App\Models\User;

/**
 * Billing Policy
 * 
 * Handles authorization for billing operations.
 * 
 * @package App\Policies
 */
class BillingPolicy
{
    /**
     * Determine whether the user can view any billings.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can view the billing.
     */
    public function view(User $user, Billing $billing): bool
    {
        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can create billings.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can generate bills.
     */
    public function generate(User $user): bool
    {
        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can update the billing.
     */
    public function update(User $user, Billing $billing): bool
    {
        // Cannot update paid or voided bills
        if (!$billing->canBeModified()) {
            return false;
        }

        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can delete the billing.
     */
    public function delete(User $user, Billing $billing): bool
    {
        // Cannot delete bills with payments
        if ($billing->allocations()->exists()) {
            return false;
        }

        // Cannot delete paid or voided bills
        if (!$billing->canBeModified()) {
            return false;
        }

        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can void a billing.
     */
    public function void(User $user, Billing $billing): bool
    {
        // Cannot void already voided bills
        if ($billing->isVoided()) {
            return false;
        }

        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can rebill.
     */
    public function rebill(User $user, Billing $billing): bool
    {
        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can download statements.
     */
    public function downloadStatement(User $user, Billing $billing): bool
    {
        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can send statements.
     */
    public function sendStatement(User $user, Billing $billing): bool
    {
        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can export billings.
     */
    public function export(User $user): bool
    {
        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can generate bills for all accounts.
     */
    public function generateAll(User $user): bool
    {
        return $user->hasPermissionTo('manage-bills');
    }
}