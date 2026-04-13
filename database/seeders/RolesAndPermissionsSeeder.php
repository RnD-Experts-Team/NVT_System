<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Departments
            'manage-departments',

            // Users
            'manage-users',
            'view-users',

            // Levels (read-only lookup)
            'view-levels',

            // Roles & Permissions
            'manage-roles',
            'manage-permissions',

            // Shifts
            'view-shifts',
            'manage-shifts',

            // Schedule
            'view-schedule',
            'manage-schedule',
            'import-schedule-excel',

            // Fingerprint & Audit
            'manage-fingerprint',
            'view-audit',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ── Roles ─────────────────────────────────────────────────────────────

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($permissions); // full access

        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->syncPermissions([
            'view-users',
            'view-levels',
            'view-shifts',
            'view-schedule',
            'manage-schedule',
            'import-schedule-excel',
        ]);

        $compliance = Role::firstOrCreate(['name' => 'compliance', 'guard_name' => 'web']);
        $compliance->syncPermissions([
            'view-users',
            'view-levels',
            'view-shifts',
            'manage-shifts',
            'view-schedule',
            'manage-fingerprint',
            'view-audit',
        ]);
    }
}
