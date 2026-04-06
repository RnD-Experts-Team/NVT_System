<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create a root department for the admin
        $rootDept = Department::firstOrCreate(
            ['name' => 'Head Office'],
            [
                'description' => 'Root department',
                'parent_id'   => null,
                'path'        => null,
                'is_active'   => true,
            ]
        );

        // Set materialized path after creation
        if (!$rootDept->path) {
            $rootDept->path = '/' . $rootDept->id . '/';
            $rootDept->save();
        }

        $l6Level = UserLevel::where('code', 'L6')->first();

        $admin = User::firstOrCreate(
            ['email' => 'admin@nvtsystem.com'],
            [
                'name'          => 'Super Admin',
                'password'      => Hash::make('Admin@1234'),
                'department_id' => $rootDept->id,
                'user_level_id' => $l6Level->id,
                'is_admin'      => true,
            ]
        );

        // Assign matching Spatie role
        $admin->syncRoles(['L6']);
    }
}
