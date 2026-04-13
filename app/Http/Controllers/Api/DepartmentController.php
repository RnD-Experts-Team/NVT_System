<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Models\User;
use App\Services\DepartmentService;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct(private DepartmentService $service) {}

    public function index()
    {
        return DepartmentResource::collection($this->service->list());
    }

    public function tree()
    {
        return DepartmentResource::collection($this->service->tree());
    }

    public function store(StoreDepartmentRequest $request)
    {
        return new DepartmentResource($this->service->store($request->validated()));
    }

    public function show(Department $department)
    {
        $deptIds = Department::where('path', 'like', $department->path . '%')
            ->orWhere('id', $department->id)
            ->pluck('id');

        $department->load('children');
        $department->setRelation(
            'users',
            User::with(['level', 'tier', 'roles'])
                ->whereIn('department_id', $deptIds)
                ->orderBy('name')
                ->get()
        );

        return new DepartmentResource($department);
    }

    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        return new DepartmentResource($this->service->update($department, $request->validated()));
    }

    public function destroy(Department $department)
    {
        $this->service->destroy($department);

        return response()->json(['message' => 'Department deleted successfully.']);
    }
}

