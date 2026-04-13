<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShiftRequest;
use App\Http\Requests\UpdateShiftRequest;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use Illuminate\Http\JsonResponse;

class ShiftController extends Controller
{
    public function index(): JsonResponse
    {
        $shifts = Shift::orderBy('name')
            ->get()
            ->map(fn (Shift $s) => $this->serialize($s));

        return response()->json(['data' => $shifts]);
    }

    public function store(StoreShiftRequest $request): JsonResponse
    {
        $data  = $request->validated();
        $shift = Shift::create([
            'name'        => $data['name'],
            'start_time'  => $data['start_time'],
            'end_time'    => $data['end_time'],
            'is_overnight' => $data['end_time'] < $data['start_time'],
            'is_active'   => $data['is_active'] ?? true,
        ]);

        return response()->json(['data' => $this->serialize($shift)], 201);
    }

    public function update(UpdateShiftRequest $request, Shift $shift): JsonResponse
    {
        $data = $request->validated();
        $shift->update(array_merge($data, [
            'is_overnight' => ($data['end_time'] ?? $shift->end_time) < ($data['start_time'] ?? $shift->start_time),
        ]));

        return response()->json(['data' => $this->serialize($shift->fresh())]);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        $inUse = ShiftAssignment::where('shift_id', $shift->id)->withTrashed()->exists();

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


