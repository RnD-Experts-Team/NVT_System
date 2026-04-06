<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\Request;
 use \App\Http\Resources\UserResource;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::with('children')->get();

        return DepartmentResource::collection($departments);
    }

    public function tree()
    {
        // Return only root departments with nested children
        $roots = Department::with('children.children.children')
            ->whereNull('parent_id')
            ->get();

        return DepartmentResource::collection($roots);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|exists:departments,id',
            'is_active'   => 'boolean',
        ]);

        $department = Department::create($validated);

        // Build materialized path
        if ($department->parent_id) {
            $parent = Department::find($department->parent_id);
            $department->path = $parent->path . $department->id . '/';
        } else {
            $department->path = '/' . $department->id . '/';
        }
        $department->save();

        return new DepartmentResource($department);
    }

    public function show(Department $department)
    {
        return new DepartmentResource($department->load('children'));
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|exists:departments,id',
            'is_active'   => 'boolean',
        ]);

        $department->update($validated);

        return new DepartmentResource($department);
    }

    public function destroy(Department $department)
    {
        if ($department->children()->exists()) {
            return response()->json([
                'message' => 'Cannot delete department with child departments.',
            ], 422);
        }

        if ($department->users()->exists()) {
            return response()->json([
                'message' => 'Cannot delete department that has users assigned.',
            ], 422);
        }

        $department->delete();

        return response()->json(['message' => 'Department deleted successfully.']);
    }

    public function users(Department $department)
    {
        // Return all users in this department and its sub-departments via path
        $deptIds = Department::where('path', 'like', $department->path . '%')
            ->orWhere('id', $department->id)
            ->pluck('id');

        $users = \App\Models\User::with(['level', 'tier'])
            ->whereIn('department_id', $deptIds)
            ->get();

        return UserResource::collection($users);
    }
}
