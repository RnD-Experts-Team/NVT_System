<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserLevelResource;
use App\Models\UserLevel;
use Illuminate\Http\Request;

class UserLevelController extends Controller
{
    public function index()
    {
        $levels = UserLevel::with('tiers')->orderBy('hierarchy_rank')->get();

        return UserLevelResource::collection($levels);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'           => 'required|string|max:50|unique:user_levels,code',
            'name'           => 'required|string|max:100',
            'hierarchy_rank' => 'required|integer|min:1',
            'description'    => 'nullable|string',
        ]);

        $level = UserLevel::create($validated);

        return new UserLevelResource($level);
    }

    public function show(UserLevel $userLevel)
    {
        return new UserLevelResource($userLevel->load('tiers'));
    }

    public function update(Request $request, UserLevel $userLevel)
    {
        $validated = $request->validate([
            'code'           => 'sometimes|required|string|max:50|unique:user_levels,code,' . $userLevel->id,
            'name'           => 'sometimes|required|string|max:100',
            'hierarchy_rank' => 'sometimes|required|integer|min:1',
            'description'    => 'nullable|string',
        ]);

        $userLevel->update($validated);

        return new UserLevelResource($userLevel->load('tiers'));
    }

    public function destroy(UserLevel $userLevel)
    {
        if ($userLevel->users()->exists()) {
            return response()->json([
                'message' => 'Cannot delete level that has users assigned.',
            ], 422);
        }

        $userLevel->delete();

        return response()->json(['message' => 'Level deleted successfully.']);
    }
}

