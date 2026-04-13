<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function stats(): array
    {
        $total = User::count();

        // Count users per role (including a "no_role" bucket)
        $roleCounts = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->select('roles.name', DB::raw('count(*) as count'))
            ->groupBy('roles.name')
            ->pluck('count', 'name')
            ->toArray();

        $assignedCount = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->distinct('model_id')
            ->count('model_id');

        $roleCounts['no_role'] = $total - $assignedCount;

        // Count users per department (top departments by count)
        $byDepartment = User::select('department_id', DB::raw('count(*) as count'))
            ->with('department:id,name')
            ->groupBy('department_id')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'id'    => $row->department_id,
                'name'  => $row->department?->name ?? 'Unassigned',
                'count' => $row->count,
            ])
            ->values()
            ->all();

        return [
            'total'         => $total,
            'by_role'       => $roleCounts,
            'by_department' => $byDepartment,
        ];
    }

    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 50);

        return User::with(['department', 'level', 'tier', 'roles'])
            ->when(! empty($filters['department_id']), fn ($q) => $q->where('department_id', $filters['department_id']))
            ->when(! empty($filters['user_level_id']), fn ($q) => $q->where('user_level_id', $filters['user_level_id']))
            ->when(! empty($filters['user_level_tier_id']), fn ($q) => $q->where('user_level_tier_id', $filters['user_level_tier_id']))
            ->when(! empty($filters['role']), fn ($q) => $q->role($filters['role']))
            ->when(! empty($filters['search']), fn ($q) => $q->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            }))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function store(array $data): User
    {
        $data['password'] = Hash::make($data['password']);

        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $user = User::create($data);

        if (! empty($roles)) {
            $user->syncRoles($roles);
        }

        return $user->load(['department', 'level', 'tier', 'roles']);
    }

    public function update(User $user, array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return $user->load(['department', 'level', 'tier', 'roles']);
    }

    public function destroy(User $user): void
    {
        $user->delete();
    }

    public function assignRoles(User $user, array $roles): User
    {
        $user->syncRoles($roles);

        return $user->load(['department', 'level', 'tier', 'roles']);
    }
}
