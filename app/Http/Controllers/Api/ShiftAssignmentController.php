<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShiftAssignment;
use App\Models\WeeklySchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ShiftAssignmentController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/schedules/assignments/create
    // ─────────────────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'week_start'        => ['required', 'date_format:Y-m-d'],
            'user_id'           => ['required', 'integer', 'exists:users,id'],
            'assignment_date'   => ['required', 'date_format:Y-m-d'],
            'assignment_type'   => ['required', Rule::in(['shift', 'day_off', 'sick_day', 'leave_request'])],
            'shift_id'          => ['nullable', 'integer', 'exists:shifts,id', Rule::requiredIf(fn () => $request->assignment_type === 'shift')],
            'is_cover'          => ['boolean'],
            'cover_for_user_id' => ['nullable', 'integer', 'exists:users,id', Rule::requiredIf(fn () => $request->boolean('is_cover'))],
            'cover_shift_id'    => ['nullable', 'integer', 'exists:shifts,id', Rule::requiredIf(fn () => $request->boolean('is_cover'))],
            'comment'           => ['nullable', 'string', 'max:1000'],
        ]);

        $deptId = $request->user()->department_id;
        $userId = $request->user()->id;

        // Verify target user belongs to manager's department
        $this->abortIfOutsideDepartment($validated['user_id'], $deptId);

        $assignment = DB::transaction(function () use ($validated, $deptId, $userId) {
            $schedule = WeeklySchedule::firstOrCreate(
                ['department_id' => $deptId, 'week_start' => $validated['week_start']],
                ['status' => 'draft']
            );

            $previous = ShiftAssignment::where([
                'weekly_schedule_id' => $schedule->id,
                'user_id'            => $validated['user_id'],
                'assignment_date'    => $validated['assignment_date'],
            ])->first();

            $assignment = ShiftAssignment::updateOrCreate(
                [
                    'weekly_schedule_id' => $schedule->id,
                    'user_id'            => $validated['user_id'],
                    'assignment_date'    => $validated['assignment_date'],
                ],
                [
                    'assignment_type'   => $validated['assignment_type'],
                    'shift_id'          => $validated['shift_id'] ?? null,
                    'is_cover'          => $validated['is_cover'] ?? false,
                    'cover_for_user_id' => $validated['cover_for_user_id'] ?? null,
                    'cover_shift_id'    => $validated['cover_shift_id'] ?? null,
                    'comment'           => $validated['comment'] ?? null,
                ]
            );

            $assignment->history()->create([
                'changed_by'        => $userId,
                'previous_type'     => $previous?->assignment_type,
                'previous_shift_id' => $previous?->shift_id,
                'new_type'          => $assignment->assignment_type,
                'new_shift_id'      => $assignment->shift_id,
                'comment'           => $validated['comment'] ?? null,
                'changed_at'        => now(),
            ]);

            return $assignment->load(['shift', 'coverForUser', 'history.changedByUser']);
        });

        return response()->json(['data' => $this->serializeAssignment($assignment)], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  PUT /api/schedules/assignments/{assignment}/update
    // ─────────────────────────────────────────────────────────────────────────
    public function update(Request $request, ShiftAssignment $assignment): JsonResponse
    {
        $this->abortIfAssignmentOutsideDepartment($assignment, $request->user()->department_id);

        $validated = $request->validate([
            'assignment_type'   => ['sometimes', 'required', Rule::in(['shift', 'day_off', 'sick_day', 'leave_request'])],
            'shift_id'          => ['nullable', 'integer', 'exists:shifts,id'],
            'is_cover'          => ['boolean'],
            'cover_for_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'cover_shift_id'    => ['nullable', 'integer', 'exists:shifts,id'],
            'comment'           => ['nullable', 'string', 'max:1000'],
        ]);

        $userId = $request->user()->id;

        DB::transaction(function () use ($assignment, $validated, $userId) {
            $prevType    = $assignment->assignment_type;
            $prevShiftId = $assignment->shift_id;

            $assignment->update($validated);

            $assignment->history()->create([
                'changed_by'        => $userId,
                'previous_type'     => $prevType,
                'previous_shift_id' => $prevShiftId,
                'new_type'          => $assignment->assignment_type,
                'new_shift_id'      => $assignment->shift_id,
                'comment'           => $validated['comment'] ?? null,
                'changed_at'        => now(),
            ]);
        });

        return response()->json([
            'data' => $this->serializeAssignment(
                $assignment->fresh(['shift', 'coverForUser', 'history.changedByUser'])
            ),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  DELETE /api/schedules/assignments/{assignment}/delete
    // ─────────────────────────────────────────────────────────────────────────
    public function destroy(Request $request, ShiftAssignment $assignment): JsonResponse
    {
        $this->abortIfAssignmentOutsideDepartment($assignment, $request->user()->department_id);

        $assignmentId = $assignment->id;

        DB::transaction(function () use ($assignment, $request) {
            $assignment->history()->create([
                'changed_by'        => $request->user()->id,
                'previous_type'     => $assignment->assignment_type,
                'previous_shift_id' => $assignment->shift_id,
                'new_type'          => null,
                'new_shift_id'      => null,
                'comment'           => 'Assignment cleared.',
                'changed_at'        => now(),
            ]);

            $assignment->delete();
        });

        return response()->json([
            'data' => [
                'message'       => 'Assignment cleared.',
                'assignment_id' => $assignmentId,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/schedules/assignments/{assignment}/history
    // ─────────────────────────────────────────────────────────────────────────
    public function history(Request $request, ShiftAssignment $assignment): JsonResponse
    {
        $this->abortIfAssignmentOutsideDepartment($assignment, $request->user()->department_id);

        $history = $assignment->history()->with('changedByUser', 'previousShift', 'newShift')->get();

        return response()->json([
            'data' => $history->map(fn ($h) => [
                'id'               => $h->id,
                'changed_by_name'  => $h->changedByUser?->name,
                'previous_type'    => $h->previous_type,
                'previous_shift'   => $h->previousShift?->name,
                'new_type'         => $h->new_type,
                'new_shift'        => $h->newShift?->name,
                'comment'          => $h->comment,
                'changed_at'       => $h->changed_at,
            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/schedules/assignments/bulk-create
    // ─────────────────────────────────────────────────────────────────────────
    public function bulk(Request $request): JsonResponse
    {
        // dd($request);
        $validated = $request->validate([
            'week_start'      => ['required', 'date_format:Y-m-d'],
            'mode'            => ['required', Rule::in(['by_employees', 'by_days'])],
            'user_ids'        => ['array', Rule::requiredIf(fn () => $request->input('mode') === 'by_employees')],
            'user_ids.*'      => ['integer', 'exists:users,id'],
            'dates'           => ['array', Rule::requiredIf(fn () => $request->input('mode') === 'by_days')],
            'dates.*'         => ['date_format:Y-m-d'],
            'assignment_type' => ['required', Rule::in(['shift', 'day_off', 'sick_day', 'leave_request'])],
            'shift_id'        => ['nullable', 'integer', 'exists:shifts,id'],
            'comment'         => ['nullable', 'string', 'max:1000'],
        ]);

        $deptId  = $request->user()->department_id;
        $userId  = $request->user()->id;
        $mode    = $validated['mode'];

        // Determine targets: pairs of (user_id, date)
        $cells = collect();

        if ($mode === 'by_employees') {
            // Same date range (Mon–Sun of the week), multiple employees
            $dates = collect(range(0, 6))->map(
                fn ($i) => \Carbon\Carbon::parse($validated['week_start'])->addDays($i)->toDateString()
            );
            foreach ($validated['user_ids'] as $uid) {
                $this->abortIfOutsideDepartment($uid, $deptId);
                foreach ($dates as $date) {
                    $cells->push(['user_id' => $uid, 'date' => $date]);
                }
            }
        } else {
            // Specific dates, all employees in department
            $employees = \App\Models\User::where('department_id', $deptId)->pluck('id');
            foreach ($validated['dates'] as $date) {
                foreach ($employees as $uid) {
                    $cells->push(['user_id' => $uid, 'date' => $date]);
                }
            }
        }

        $assigned = 0;
        $results  = [];

        DB::transaction(function () use ($validated, $deptId, $userId, $cells, &$assigned, &$results) {
            $schedule = WeeklySchedule::firstOrCreate(
                ['department_id' => $deptId, 'week_start' => $validated['week_start']],
                ['status' => 'draft']
            );

            foreach ($cells as $cell) {
                $previous = ShiftAssignment::where([
                    'weekly_schedule_id' => $schedule->id,
                    'user_id'            => $cell['user_id'],
                    'assignment_date'    => $cell['date'],
                ])->first();

                $a = ShiftAssignment::updateOrCreate(
                    [
                        'weekly_schedule_id' => $schedule->id,
                        'user_id'            => $cell['user_id'],
                        'assignment_date'    => $cell['date'],
                    ],
                    [
                        'assignment_type' => $validated['assignment_type'],
                        'shift_id'        => $validated['shift_id'] ?? null,
                        'is_cover'        => false,
                        'comment'         => $validated['comment'] ?? null,
                    ]
                );

                $a->history()->create([
                    'changed_by'        => $userId,
                    'previous_type'     => $previous?->assignment_type,
                    'previous_shift_id' => $previous?->shift_id,
                    'new_type'          => $a->assignment_type,
                    'new_shift_id'      => $a->shift_id,
                    'comment'           => 'Bulk assignment' . (($validated['comment'] ?? null) ? ': ' . $validated['comment'] : ''),
                    'changed_at'        => now(),
                ]);

                $assigned++;
                $results[] = ['user_id' => $cell['user_id'], 'date' => $cell['date'], 'assignment_id' => $a->id];
            }
        });

        return response()->json([
            'data' => [
                'assigned' => $assigned,
                'cells'    => $results,
            ],
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────
    private function abortIfOutsideDepartment(int $targetUserId, int $deptId): void
    {
        $belongs = \App\Models\User::where('id', $targetUserId)
            ->where('department_id', $deptId)
            ->exists();

        abort_if(! $belongs, 403, 'Target user does not belong to your department.');
    }

    private function abortIfAssignmentOutsideDepartment(ShiftAssignment $assignment, int $deptId): void
    {
        $assignment->load('weeklySchedule');
        abort_if($assignment->weeklySchedule->department_id !== $deptId, 403, 'Assignment does not belong to your department.');
    }

    private function serializeAssignment(ShiftAssignment $a): array
    {
        return [
            'id'              => $a->id,
            'user_id'         => $a->user_id,
            'assignment_date' => $a->assignment_date?->toDateString(),
            'assignment_type' => $a->assignment_type,
            'shift'           => $a->shift ? [
                'id'           => $a->shift->id,
                'name'         => $a->shift->name,
                'start_time'   => $a->shift->start_time,
                'end_time'     => $a->shift->end_time,
                'is_overnight' => $a->shift->is_overnight,
            ] : null,
            'is_cover'        => $a->is_cover,
            'cover_for_user'  => $a->coverForUser ? [
                'id'   => $a->coverForUser->id,
                'name' => $a->coverForUser->name,
            ] : null,
            'comment'         => $a->comment,
            'history'         => $a->relationLoaded('history') ? $a->history->map(fn ($h) => [
                'id'              => $h->id,
                'changed_by_name' => $h->changedByUser?->name,
                'previous_type'   => $h->previous_type,
                'new_type'        => $h->new_type,
                'comment'         => $h->comment,
                'changed_at' => $h->changed_at?->format('Y-m-d H:i:s'),
            ]) : [],
        ];
    }
}
