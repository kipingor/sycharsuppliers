<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ExpensePolicy
{
    /**
     * View all expenses
     * Admin and accountant
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'accountant']);
    }

    /**
     * View specific expense
     */
    public function view(User $user, Expense $expense): bool
    {
        return $user->hasRole(['admin', 'accountant']);
    }

    /**
     * Create expense (admin and accountant)
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'accountant']);
    }

    /**
     * Update expense (admin and accountant, not if approved)
     */
    public function update(User $user, Expense $expense): bool
    {
        if (!$user->hasRole(['admin', 'accountant'])) {
            return false;
        }
        
        // Prevent updating approved expenses
        return !$expense->approved_at;
    }

    /**
     * Delete expense (admin only, not if approved)
     */
    public function delete(User $user, Expense $expense): bool
    {
        if (!$user->hasRole('admin')) {
            return false;
        }
        
        // Prevent deleting approved expenses
        return !$expense->approved_at;
    }

    /**
     * Restore soft-deleted expense
     */
    public function restore(User $user, Expense $expense): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Permanently delete expense
     */
    public function forceDelete(User $user, Expense $expense): bool
    {
        return $user->hasRole('admin') && $user->email === 'admin@sycharsuppliers.com';
    }
}
