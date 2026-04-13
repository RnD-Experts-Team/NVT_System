<?php

namespace App\Services;

use App\Models\Department;
use Illuminate\Database\Eloquent\Collection;

class DepartmentService
{
    public function list(): Collection
    {
        return Department::with('children')->get();
    }

    public function tree(): Collection
    {
        return Department::with('children.children.children')
            ->whereNull('parent_id')
            ->get();
    }

    public function store(array $data): Department
    {
        $department = Department::create($data);

        if ($department->parent_id) {
            $parent = Department::find($department->parent_id);
            $department->path = $parent->path . $department->id . '/';
        } else {
            $department->path = '/' . $department->id . '/';
        }

        $department->save();

        return $department;
    }

    public function update(Department $department, array $data): Department
    {
        $department->update($data);

        return $department;
    }

    public function destroy(Department $department): void
    {
        if ($department->children()->exists()) {
            abort(422, 'Cannot delete department with child departments.');
        }

        if ($department->users()->exists()) {
            abort(422, 'Cannot delete department that has users assigned.');
        }

        $department->delete();
    }
}
