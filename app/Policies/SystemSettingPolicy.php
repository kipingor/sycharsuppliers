<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;

class SystemSettingPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole('admin')
            || (Permission::query()->where('name', 'manage-settings')->where('guard_name', 'web')->exists()
                && $user->hasPermissionTo('manage-settings'));
    }
}
