<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserLevelResource;
use App\Models\UserLevel;
use Illuminate\Http\Request;

class UserLevelController extends Controller
{
    public function index(Request $request)
    {
        $levels = UserLevel::with('tiers')->orderBy('hierarchy_rank')->get();

        if ($request->boolean('with_counts')) {
            $levels->each(function ($level) {
                $level->tiers->each(function ($tier) {
                    $tier->loadCount('users');
                });
            });
        }

        return UserLevelResource::collection($levels);
    }
}
