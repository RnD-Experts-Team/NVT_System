<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['department', 'level', 'tier', 'roles'])->get();

        return UserResource::collection($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'nickname'            => 'nullable|string|max:100',
            'ac_no'               => 'required|string|max:50|unique:users,ac_no',
            'email'               => 'required|email|unique:users,email',
            'password'            => 'required|string|min:8',
            'department_id'       => 'required|exists:departments,id',
            'user_level_id'       => 'required|exists:user_levels,id',
            'user_level_tier_id'  => 'nullable|exists:user_level_tiers,id',
            'is_admin'            => 'boolean',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        // Auto-assign Spatie role matching the level code
        $this->syncLevelRole($user);

        return new UserResource($user->load(['department', 'level', 'tier', 'roles']));
    }

    public function show(User $user)
    {
        return new UserResource($user->load(['department', 'level', 'tier', 'roles']));
    }

    public function assignRoles(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles'   => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user->syncRoles($validated['roles']);

        return new UserResource($user->load(['department', 'level', 'tier', 'roles']));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'               => 'sometimes|required|string|max:255',
            'nickname'           => 'nullable|string|max:100',
            'ac_no'              => 'nullable|string|max:50|unique:users,ac_no,' . $user->id,
            'email'              => 'sometimes|required|email|unique:users,email,' . $user->id,
            'password'           => 'sometimes|required|string|min:8',
            'department_id'      => 'sometimes|required|exists:departments,id',
            'user_level_id'      => 'sometimes|required|exists:user_levels,id',
            'user_level_tier_id' => 'nullable|exists:user_level_tiers,id',
            'is_admin'           => 'boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $levelChanged = isset($validated['user_level_id']) && $validated['user_level_id'] != $user->user_level_id;

        $user->update($validated);

        if ($levelChanged) {
            $this->syncLevelRole($user);
        }

        return new UserResource($user->load(['department', 'level', 'tier', 'roles']));
    }

    public function destroy(User $user)
    {
        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    private function syncLevelRole(User $user): void
    {
        $level = UserLevel::find($user->user_level_id);
        if ($level) {
            // Ensure the Spatie role exists (creates it if not yet seeded)
            Role::firstOrCreate(
                ['name' => $level->code, 'guard_name' => 'web']
            );

            $levelRoleCodes = ['L1', 'L2', 'L2PM', 'L3', 'L4', 'L5', 'L6'];
            $nonLevelRoles  = $user->roles()
                ->whereNotIn('name', $levelRoleCodes)
                ->pluck('name')
                ->all();

            $user->syncRoles(array_values(array_unique([
                ...$nonLevelRoles,
                $level->code,
            ])));
        }
    }
}

