<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (RefreshDatabaseState::$migrated && Schema::hasTable('permissions')) {
            $this->seedTestPermissions();
        }
    }

    protected function seedTestPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'manage-users',
            'manage-settings',
            'manage-residents',
            'view-reports',
            'manage-bills',
            'process-payments',
            'record-meter-readings',
            'view_audit_logs',
            'view_all_user_activity',
            'export_audit_logs',
            'view_audit_statistics',
            'delete_audit_logs',
            'create-meter-readings',
            'manage-meters',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (['admin', 'accountant', 'field_officer'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }
    }
}
