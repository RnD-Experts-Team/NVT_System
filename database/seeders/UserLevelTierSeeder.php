<?php

namespace Database\Seeders;

use App\Models\UserLevel;
use App\Models\UserLevelTier;
use Illuminate\Database\Seeder;

class UserLevelTierSeeder extends Seeder
{
    public function run(): void
    {
        $levels = UserLevel::all()->keyBy('code');

        $tiers = [
            ['tier_name' => 'Tier 1', 'tier_order' => 1],
            ['tier_name' => 'Tier 2', 'tier_order' => 2],
            ['tier_name' => 'Tier 3', 'tier_order' => 3],
        ];

        foreach ($levels as $level) {
            foreach ($tiers as $tier) {
                UserLevelTier::firstOrCreate(
                    [
                        'user_level_id' => $level->id,
                        'tier_order'    => $tier['tier_order'],
                    ],
                    [
                        'tier_name' => $tier['tier_name'],
                    ]
                );
            }
        }
    }
}
