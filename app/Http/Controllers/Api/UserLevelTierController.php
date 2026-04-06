<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserLevel;
use App\Models\UserLevelTier;
use Illuminate\Http\Request;

class UserLevelTierController extends Controller
{
    public function index(UserLevel $userLevel)
    {
        return response()->json($userLevel->tiers);
    }

    public function store(Request $request, UserLevel $userLevel)
    {
        $validated = $request->validate([
            'tier_name'   => 'required|string|max:100',
            'tier_order'  => 'required|integer|min:1',
            'description' => 'nullable|string',
        ]);

        $tier = $userLevel->tiers()->create($validated);

        return response()->json($tier, 201);
    }

    public function show(UserLevel $userLevel, UserLevelTier $tier)
    {
        
        abort_if($tier->user_level_id !== $userLevel->id, 404);
        
        return response()->json($tier);
    }

    public function update(Request $request, UserLevel $userLevel, UserLevelTier $tier)
    {
        abort_if($tier->user_level_id !== $userLevel->id, 404);

        $validated = $request->validate([
            'tier_name'   => 'sometimes|required|string|max:100',
            'tier_order'  => 'sometimes|required|integer|min:1',
            'description' => 'nullable|string',
        ]);

        $tier->update($validated);

        return response()->json($tier);
    }

    public function destroy(UserLevel $userLevel, UserLevelTier $tier)
    {
        abort_if($tier->user_level_id !== $userLevel->id, 404);

        if ($tier->users()->exists()) {
            return response()->json([
                'message' => 'Cannot delete tier that has users assigned.',
            ], 422);
        }

        $tier->delete();

        return response()->json(['message' => 'Tier deleted successfully.']);
    }
}

