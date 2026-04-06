<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'manage_hierarchy',
            'manage_own_department_schedule',
            'view_all_schedules',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create level-based roles
        $levelRoles = ['L1', 'L2', 'L2PM', 'L3', 'L4', 'L5', 'L6'];
        foreach ($levelRoles as $roleCode) {
            Role::firstOrCreate(['name' => $roleCode, 'guard_name' => 'web']);
        }

        // Create auditor role
        $auditor = Role::firstOrCreate(['name' => 'auditor', 'guard_name' => 'web']);
        $auditor->syncPermissions(['view_all_schedules']);

        // Manager roles (L2–L6) can manage their department schedule
        $managerRoles = ['L2', 'L3', 'L4', 'L5', 'L6'];
        foreach ($managerRoles as $roleCode) {
            $role = Role::where('name', $roleCode)->first();
            $role->syncPermissions(['manage_own_department_schedule']);
        }
    }
}
