<?php

namespace App\Policies;

use App\Models\TaxDocument;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaxDocumentPolicy
{
    /**
     * View all tax documents
     * Admin and accountant
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'accountant']);
    }

    /**
     * View specific tax document
     */
    public function view(User $user, TaxDocument $taxDocument): bool
    {
        return $user->hasRole(['admin', 'accountant']);
    }

    /**
     * Create tax document (admin only)
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Update tax document (admin only)
     */
    public function update(User $user, TaxDocument $taxDocument): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Delete tax document (admin only)
     */
    public function delete(User $user, TaxDocument $taxDocument): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Restore soft-deleted tax document
     */
    public function restore(User $user, TaxDocument $taxDocument): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Permanently delete tax document
     */
    public function forceDelete(User $user, TaxDocument $taxDocument): bool
    {
        return $user->hasRole('admin') && $user->email === 'admin@sycharsuppliers.com';
    }
}
