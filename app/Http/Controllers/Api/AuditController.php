<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\AuditIndexRequest;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuditController extends Controller
{
    public function __construct(private AuditService $service) {}

    public function index(AuditIndexRequest $request): JsonResponse
    {
        $user        = $request->user();
        $forceDeptId = $user->hasRole('manager') ? $user->department_id : null;

        $grid = $this->service->grid($request->validated(), $forceDeptId);

        return response()->json(['data' => $grid]);
    }

    public function cell(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date'    => ['required', 'date_format:Y-m-d'],
        ]);

        $result = $this->service->cell(
            $request->integer('user_id'),
            $request->input('date')
        );

        return response()->json(['data' => $result]);
    }
}
