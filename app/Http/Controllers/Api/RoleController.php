<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->get()->map(fn ($role) => [
            'id'          => $role->id,
            'name'        => $role->name,
            'permissions' => $role->permissions->pluck('name'),
        ]);

        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name',
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);

        return response()->json($role, 201);
    }

    public function show(Role $role)
    {
        return response()->json([
            'id'          => $role->id,
            'name'        => $role->name,
            'permissions' => $role->permissions->pluck('name'),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name,' . $role->id,
        ]);

        $role->update($validated);

        return response()->json($role);
    }

    public function destroy(Role $role)
    {
        $role->delete();

        return response()->json(['message' => 'Role deleted successfully.']);
    }

    public function assignPermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permissions'   => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role->syncPermissions($validated['permissions']);

        return response()->json([
            'message'     => 'Permissions assigned successfully.',
            'role'        => $role->name,
            'permissions' => $role->permissions->pluck('name'),
        ]);
    }
}

