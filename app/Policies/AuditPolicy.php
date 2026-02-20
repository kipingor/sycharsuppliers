<?php

namespace App\Policies;

use App\Models\User;
use OwenIt\Auditing\Models\Audit;

/**
 * Audit Policy
 * 
 * Authorization logic for audit log access.
 * Controls who can view, export, and analyze audit trails.
 * 
 * @package App\Policies
 */
class AuditPolicy
{
    /**
     * Determine whether the user can view any audits.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_audit_logs');
    }

    /**
     * Determine whether the user can view the audit.
     */
    public function view(User $user, Audit $audit): bool
    {
        return $user->hasPermissionTo('view_audit_logs');
    }

    /**
     * Determine whether the user can view audit trails for entities.
     */
    public function viewEntityTrail(User $user): bool
    {
        return $user->hasPermissionTo('view_audit_logs');
    }

    /**
     * Determine whether the user can view user activity.
     */
    public function viewUserActivity(User $user, ?int $targetUserId = null): bool
    {
        // Admins can view any user's activity
        if ($user->hasPermissionTo('view_all_user_activity')) {
            return true;
        }

        // Users can view their own activity
        if ($targetUserId && $targetUserId === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can export audit logs.
     */
    public function export(User $user): bool
    {
        return $user->hasPermissionTo('export_audit_logs');
    }

    /**
     * Determine whether the user can view audit statistics.
     */
    public function viewStatistics(User $user): bool
    {
        return $user->hasPermissionTo('view_audit_statistics');
    }

    /**
     * Determine whether the user can delete audit logs.
     */
    public function delete(User $user, Audit $audit): bool
    {
        // Only super admins can delete audit logs
        return $user->hasPermissionTo('delete_audit_logs');
    }
}
