<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShiftController extends Controller
{
    public function index(): JsonResponse
    {
        $shifts = Shift::orderBy('name')
            ->get()
            ->map(fn (Shift $s) => $this->serialize($s));

        return response()->json(['data' => $shifts]);
    }

    /**
     * POST /api/shifts/create
     * Compliance only.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100', 'unique:shifts,name'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i'],
            'is_active'  => ['boolean'],
        ]);

        $shift = Shift::create([
            'name'        => $validated['name'],
            'start_time'  => $validated['start_time'],
            'end_time'    => $validated['end_time'],
            'is_overnight' => $validated['end_time'] < $validated['start_time'],
            'is_active'   => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $this->serialize($shift)], 201);
    }

    /**
     * PUT /api/shifts/{shift}/update
     * Compliance only.
     */
    public function update(Request $request, Shift $shift): JsonResponse
    {
        
        $validated = $request->validate([
            'name'       => ['sometimes', 'required', 'string', 'max:100', Rule::unique('shifts', 'name')->ignore($shift->id)],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time'   => ['sometimes', 'required', 'date_format:H:i'],
            'is_active'  => ['boolean'],
        ]);

        $shift->update(array_merge($validated, [
            'is_overnight' => ($validated['end_time'] ?? $shift->end_time) < ($validated['start_time'] ?? $shift->start_time),
        ]));

        return response()->json(['data' => $this->serialize($shift->fresh())]);
    }

    /**
     * DELETE /api/shifts/{shift}/delete
     * Compliance only. Only allowed when no active assignments reference this shift.
     */
    public function destroy(Shift $shift): JsonResponse
    {
        $inUse = \App\Models\ShiftAssignment::where('shift_id', $shift->id)
            ->withTrashed()
            ->exists();

        if ($inUse) {
            return response()->json([
                'message' => 'Cannot delete: this shift is referenced by existing assignments.',
            ], 422);
        }

        $shift->delete();

        return response()->json(['message' => 'Shift deleted.']);
    }

    private function serialize(Shift $s): array
    {
        return [
            'id'           => $s->id,
            'name'         => $s->name,
            'start_time'   => $s->start_time,
            'end_time'     => $s->end_time,
            'is_overnight' => $s->is_overnight,
            'is_active'    => $s->is_active,
        ];
    }
}

