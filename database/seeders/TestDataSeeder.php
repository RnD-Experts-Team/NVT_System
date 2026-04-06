<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── 1. Ensure Compliance role exists ───────────────────────────────
        $allRoles = ['L1', 'L2', 'L2PM', 'L3', 'L4', 'L5', 'L6', 'Compliance'];
        foreach ($allRoles as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        // ─── 2. Departments ─────────────────────────────────────────────────
        $root = Department::firstOrCreate(
            ['name' => 'Head Office'],
            ['description' => 'Root department', 'parent_id' => null, 'is_active' => true]
        );
        if (!$root->path) {
            $root->path = '/' . $root->id . '/';
            $root->save();
        }

        $engineering = Department::firstOrCreate(
            ['name' => 'Engineering'],
            ['description' => 'Technology & Innovation', 'parent_id' => $root->id, 'is_active' => true]
        );
        if (!$engineering->path) {
            $engineering->path = '/' . $root->id . '/' . $engineering->id . '/';
            $engineering->save();
        }

        $operations = Department::firstOrCreate(
            ['name' => 'Operations'],
            ['description' => 'Day-to-day operations', 'parent_id' => $root->id, 'is_active' => true]
        );
        if (!$operations->path) {
            $operations->path = '/' . $root->id . '/' . $operations->id . '/';
            $operations->save();
        }

        // ─── 3. Levels ───────────────────────────────────────────────────────
        $l1  = UserLevel::where('code', 'L1')->first();
        $l2  = UserLevel::where('code', 'L2')->first();
        $l3  = UserLevel::where('code', 'L3')->first();
        $l6  = UserLevel::where('code', 'L6')->first();

        // ─── 4. Manager (L2) — Engineering ──────────────────────────────────
        $manager = User::firstOrCreate(
            ['email' => 'manager@nvtsystem.com'],
            [
                'name'          => 'Jane Manager',
                'nickname'      => 'Jane',
                'ac_no'         => '2001',
                'password'      => Hash::make('Manager@1234'),
                'department_id' => $engineering->id,
                'user_level_id' => $l2->id,
                'is_admin'      => false,
            ]
        );
        $manager->syncRoles(['L2']);

        // ─── 5. Senior Manager (L3) — Operations ────────────────────────────
        $seniorManager = User::firstOrCreate(
            ['email' => 'senior.manager@nvtsystem.com'],
            [
                'name'          => 'Bob Senior',
                'nickname'      => 'Bob',
                'ac_no'         => '2002',
                'password'      => Hash::make('Senior@1234'),
                'department_id' => $operations->id,
                'user_level_id' => $l3->id,
                'is_admin'      => false,
            ]
        );
        $seniorManager->syncRoles(['L3']);

        // ─── 6. Compliance Officer ───────────────────────────────────────────
        $compliance = User::firstOrCreate(
            ['email' => 'compliance@nvtsystem.com'],
            [
                'name'          => 'Alice Compliance',
                'nickname'      => 'Alice',
                'ac_no'         => '2003',
                'password'      => Hash::make('Comply@1234'),
                'department_id' => $root->id,
                'user_level_id' => $l6->id,
                'is_admin'      => false,
            ]
        );
        $compliance->syncRoles(['Compliance']);

        // ─── 7. Regular Employees (L1) — Engineering for schedule testing ───
        $employees = [
            ['name' => 'Employee One',   'email' => 'emp1@nvtsystem.com', 'ac_no' => '1001'],
            ['name' => 'Employee Two',   'email' => 'emp2@nvtsystem.com', 'ac_no' => '1002'],
            ['name' => 'Employee Three', 'email' => 'emp3@nvtsystem.com', 'ac_no' => '1003'],
            ['name' => 'Employee Four',  'email' => 'emp4@nvtsystem.com', 'ac_no' => '1004'],
            ['name' => 'Employee Five',  'email' => 'emp5@nvtsystem.com', 'ac_no' => '1005'],
        ];

        foreach ($employees as $empData) {
            $emp = User::firstOrCreate(
                ['email' => $empData['email']],
                [
                    'name'          => $empData['name'],
                    'ac_no'         => $empData['ac_no'],
                    'password'      => Hash::make('Employee@1234'),
                    'department_id' => $engineering->id,
                    'user_level_id' => $l1->id,
                    'is_admin'      => false,
                ]
            );
            $emp->syncRoles(['L1']);
        }

        $this->command->info('✔ TestDataSeeder complete.');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin (is_admin)',    'admin@nvtsystem.com',          'Admin@1234'],
                ['Manager (L2)',        'manager@nvtsystem.com',        'Manager@1234'],
                ['Senior Manager (L3)','senior.manager@nvtsystem.com', 'Senior@1234'],
                ['Compliance',          'compliance@nvtsystem.com',     'Comply@1234'],
                ['Employee (L1) x5',   'emp1–5@nvtsystem.com',         'Employee@1234'],
            ]
        );
    }
}
