<?php

namespace App\Policies;

use App\Models\Tax;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaxPolicy
{
    /**
     * View all taxes
     * Admin and accountant
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'accountant']);
    }

    /**
     * View specific tax
     */
    public function view(User $user, Tax $tax): bool
    {
        return $user->hasRole(['admin', 'accountant']);
    }

    /**
     * Create tax (admin only)
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Update tax (admin only)
     */
    public function update(User $user, Tax $tax): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Delete tax (admin only)
     */
    public function delete(User $user, Tax $tax): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Restore soft-deleted tax
     */
    public function restore(User $user, Tax $tax): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Permanently delete tax
     */
    public function forceDelete(User $user, Tax $tax): bool
    {
        return $user->hasRole('admin') && $user->email === 'admin@sycharsuppliers.com';
    }
}
