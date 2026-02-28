<?php

namespace App\Policies;

use App\Models\Resident;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ResidentPolicy
{
    /**
     * View all residents
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'accountant']);
    }

    /**
     * View specific resident
     */
    public function view(User $user, Resident $resident): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('accountant')) {
            return $user->account_id === $resident->account_id;
        }
        
        return false;
    }

    /**
     * Create resident
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'accountant']);
    }

    /**
     * Update resident
     */
    public function update(User $user, Resident $resident): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('accountant')) {
            return $user->account_id === $resident->account_id;
        }
        
        return false;
    }

    /**
     * Delete resident (admin only)
     */
    public function delete(User $user, Resident $resident): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Restore soft-deleted resident
     */
    public function restore(User $user, Resident $resident): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Permanently delete resident
     */
    public function forceDelete(User $user, Resident $resident): bool
    {
        return $user->hasRole('admin') && $user->email === 'admin@sycharsuppliers.com';
    }
}
