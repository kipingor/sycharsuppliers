<?php

namespace App\Policies;

use App\Models\User;

class AccountPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine whether the user can create billings.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can create billings.
     */
    public function generateFromResidents(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
