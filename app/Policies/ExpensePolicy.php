<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'accountant']);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->hasRole(['admin', 'accountant']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'accountant']);
    }

    /**
     * Only allow editing if the expense is still pending.
     * FIXED: previous code checked $expense->approved_at which does not exist.
     * The model uses a boolean $status column (true = approved).
     */
    public function update(User $user, Expense $expense): bool
    {
        if (!$user->hasRole(['admin', 'accountant'])) {
            return false;
        }
        return $expense->isPending();
    }

    public function delete(User $user, Expense $expense): bool
    {
        if (!$user->hasRole('admin')) {
            return false;
        }
        return !$expense->isApproved();
    }

    public function approve(User $user, Expense $expense): bool
    {
        return $user->hasRole('admin') && !$expense->isApproved();
    }

    public function reject(User $user, Expense $expense): bool
    {
        return $user->hasRole('admin') && !$expense->isRejected();
    }

    public function restore(User $user, Expense $expense): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Expense $expense): bool
    {
        return $user->hasRole('admin');
    }
}
