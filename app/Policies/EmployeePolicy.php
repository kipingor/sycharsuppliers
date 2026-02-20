<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EmployeePolicy
{
    /**
     * View all employees
     * Admin only
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * View specific employee
     */
    public function view(User $user, Employee $employee): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Create employee (admin only)
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Update employee (admin only)
     */
    public function update(User $user, Employee $employee): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Delete employee (admin only)
     */
    public function delete(User $user, Employee $employee): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Restore soft-deleted employee
     */
    public function restore(User $user, Employee $employee): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Permanently delete employee
     */
    public function forceDelete(User $user, Employee $employee): bool
    {
        return $user->hasRole('admin') && $user->email === 'admin@sycharsuppliers.com';
    }
}
