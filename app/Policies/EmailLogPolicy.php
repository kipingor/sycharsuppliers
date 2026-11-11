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
        return true;
    }

    /**
     * View specific email log
     */
    public function view(User $user, EmailLog $emailLog): bool
    {
        if ($user->hasRole('admin') || $emailLog->sender_user_id === $user->id) {
            return true;
        }

        $addresses = array_filter([
            strtolower((string) $user->company_email),
            ...array_map('strtolower', config('mail.shared_addresses', [])),
        ]);

        return in_array(strtolower((string) $emailLog->from_email), $addresses, true)
            || in_array(strtolower((string) $emailLog->recipient_email), $addresses, true);
    }

    /**
     * Create email log (system generated, not user action)
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Update email log (admin only)
     */
    public function update(User $user, EmailLog $emailLog): bool
    {
        return $this->view($user, $emailLog);
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
