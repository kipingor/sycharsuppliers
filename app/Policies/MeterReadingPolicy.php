<?php

namespace App\Policies;

use App\Models\MeterReading;
use App\Models\User;

/**
 * Meter Reading Policy
 *
 * Authorization logic for meter reading management.
 *
 * @package App\Policies
 */
class MeterReadingPolicy
{
    /**
     * Determine whether the user can view any meter readings.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['manage-bills', 'record-meter-readings']);
    }

    /**
     * Determine whether the user can view the meter reading.
     */
    public function view(User $user, MeterReading $meterReading): bool
    {
        return $user->hasAnyPermission(['manage-bills', 'record-meter-readings']);
    }

    /**
     * Determine whether the user can create meter readings.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('record-meter-readings');
    }

    /**
     * Determine whether the user can update the meter reading.
     */
    public function update(User $user, MeterReading $meterReading): bool
    {
        // Cannot update readings that have been used in billing
        try {
            if ($meterReading->meter->readingHasBeenBilled($meterReading->reading)) {
                return $user->hasPermissionTo('manage-bills');
            }
        } catch (\Exception $e) {
            // If there's an error checking billing status, be conservative
            // and require manage-bills permission
            logger()->warning('Error checking billing status for meter reading', [
                'meter_reading_id' => $meterReading->id,
                'error' => $e->getMessage()
            ]);
            
            return $user->hasPermissionTo('manage-bills');
        }

        // Cannot update distributed bulk readings
        if ($meterReading->is_distributed) {
            return false;
        }

        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can delete the meter reading.
     */
    public function delete(User $user, MeterReading $meterReading): bool
    {
        // Cannot delete readings that have been used in billing
        try {
            if ($meterReading->meter->readingHasBeenBilled($meterReading->reading)) {
                return false;
            }
        } catch (\Exception $e) {
            // If there's an error checking billing status, be conservative
            // and prevent deletion
            logger()->warning('Error checking billing status for meter reading deletion', [
                'meter_reading_id' => $meterReading->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }

        // Cannot delete distributed bulk readings
        if ($meterReading->is_distributed) {
            return false;
        }

        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can distribute a bulk meter reading
     */
    public function distribute(User $user, MeterReading $meterReading): bool
    {
        // Must have permission to create readings
        if (!$user->hasAnyPermission('create-meter-readings', 'manage-meters')) {
            return false;
        }

        // Must be a bulk meter reading
        if (!$meterReading->meter->isBulkMeter()) {
            return false;
        }

        // Must not already be distributed
        if ($meterReading->is_distributed) {
            return false;
        }

        // Must be an actual reading
        if ($meterReading->reading_type !== 'actual') {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can create estimated readings.
     */
    public function createEstimated(User $user): bool
    {
        return $user->hasPermissionTo('manage-bills');
    }

    /**
     * Determine whether the user can export readings.
     */
    public function export(User $user): bool
    {
        return $user->hasPermissionTo('manage-bills');
    }
}