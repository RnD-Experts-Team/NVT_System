<?php

namespace Database\Seeders;

use App\Models\UserLevel;
use Illuminate\Database\Seeder;

class UserLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['code' => 'L1',   'name' => 'Employee',        'hierarchy_rank' => 1],
            ['code' => 'L2',   'name' => 'Team Lead',       'hierarchy_rank' => 2],
            ['code' => 'L2PM', 'name' => 'Project Manager', 'hierarchy_rank' => 2],
            ['code' => 'L3',   'name' => 'Senior Manager',  'hierarchy_rank' => 3],
            ['code' => 'L4',   'name' => 'Department Head', 'hierarchy_rank' => 4],
            ['code' => 'L5',   'name' => 'Director',        'hierarchy_rank' => 5],
            ['code' => 'L6',   'name' => 'Executive',       'hierarchy_rank' => 6],
        ];

        foreach ($levels as $level) {
            UserLevel::firstOrCreate(['code' => $level['code']], $level);
        }
    }
}
