<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (RefreshDatabaseState::$migrated) {
            $this->seedTestPermissions();
        }
    }

    protected function seedTestPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'manage-users',
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
    }
}
