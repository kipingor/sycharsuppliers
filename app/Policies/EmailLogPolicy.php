<?php

namespace App\Policies;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EmailLogPolicy
{
    /**
     * View all email logs
     * Admin only (sensitive data)
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * View specific email log
     */
    public function view(User $user, EmailLog $emailLog): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Create email log (system generated, not user action)
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Update email log (admin only)
     */
    public function update(User $user, EmailLog $emailLog): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Delete email log (admin only, for compliance)
     */
    public function delete(User $user, EmailLog $emailLog): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Restore soft-deleted email log
     */
    public function restore(User $user, EmailLog $emailLog): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Permanently delete email log
     */
    public function forceDelete(User $user, EmailLog $emailLog): bool
    {
        return $user->hasRole('admin') && $user->email === 'admin@sycharsuppliers.com';
    }
}
