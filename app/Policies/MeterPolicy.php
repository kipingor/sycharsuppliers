<?php

namespace App\Policies;

use App\Models\Meter;
use App\Models\User;

/**
 * Meter Policy
 * 
 * Authorization logic for meter management.
 * 
 * @package App\Policies
 */
class MeterPolicy
{
    /**
     * Determine whether the user can view any meters.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can view the meter.
     */
    public function view(User $user, Meter $meter): bool
    {
        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can create meters.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can update the meter.
     */
    public function update(User $user, Meter $meter): bool
    {
        // Cannot update if meter is in use
        if ($meter->readings()->exists() || $meter->billingDetails()->exists()) {
            return $user->hasPermissionTo('manage-bills');
        }

        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can delete the meter.
     */
    public function delete(User $user, Meter $meter): bool
    {
        // Cannot delete meters with history
        if ($meter->readings()->exists() || $meter->billingDetails()->exists()) {
            return false;
        }

        // Cannot delete bulk meters with sub-meters
        if ($meter->isBulkMeter() && $meter->hasSubMeters()) {
            return false;
        }

        return $user->hasPermissionTo('manage-bills');
    }

    public function createReading(User $user, Meter $meter): bool
    {
        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can manage sub-meters for a bulk meter.
     */
    public function manageSubMeters(User $user, Meter $meter): bool
    {
        if (!$meter->isBulkMeter()) {
            return false;
        }

        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can adjust allocations for bulk meters.
     */
    public function adjustAllocations(User $user, Meter $meter): bool
    {
        if (!$meter->isBulkMeter()) {
            return false;
        }

        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can deactivate the meter.
     */
    public function deactivate(User $user, Meter $meter): bool
    {
        // Cannot deactivate bulk meters with active sub-meters
        if ($meter->isBulkMeter()) {
            $activeSubMeters = $meter->subMeters()->active()->count();
            if ($activeSubMeters > 0) {
                return false;
            }
        }

        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can activate the meter.
     */
    public function activate(User $user, Meter $meter): bool
    {
        // If sub-meter, parent must be active
        if ($meter->parent_meter_id) {
            $parentMeter = $meter->parentMeter;
            if (!$parentMeter || !$parentMeter->isActive()) {
                return false;
            }
        }

        return $user->hasPermissionTo('manage-bills');
    }
}