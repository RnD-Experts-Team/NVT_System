<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserLevelSeeder::class,
            UserLevelTierSeeder::class,
            RolesAndPermissionsSeeder::class,
            AdminSeeder::class,
            TestDataSeeder::class,
        ]);
    }
}
