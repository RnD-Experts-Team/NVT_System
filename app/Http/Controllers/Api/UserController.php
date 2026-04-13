<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private UserService $service) {}

    public function index(Request $request)
    {
        $request->validate([
            'department_id'      => ['nullable', 'integer', 'exists:departments,id'],
            'user_level_id'      => ['nullable', 'integer', 'exists:user_levels,id'],
            'user_level_tier_id' => ['nullable', 'integer', 'exists:user_level_tiers,id'],
            'role'               => ['nullable', 'string', 'exists:roles,name'],
            'search'             => ['nullable', 'string', 'max:100'],
            'per_page'           => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $paginated = $this->service->list($request->all());
        $summary   = $this->service->stats();

        return UserResource::collection($paginated)->additional(['summary' => $summary]);
    }

    public function store(StoreUserRequest $request)
    {
        return new UserResource($this->service->store($request->validated()));
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

        return new UserResource($this->service->assignRoles($user, $validated['roles']));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        return new UserResource($this->service->update($user, $request->validated()));
    }

    public function destroy(User $user)
    {
        $this->service->destroy($user);

        return response()->json(['message' => 'User deleted successfully.']);
    }
}
