<?php

namespace App\Http\Controllers\Api;

use App\Models\AttendanceStatus;
use App\Models\Department;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuditController extends Controller
{
    /**
     * GET /api/audit?week_start=YYYY-MM-DD[&department_id=&status=&search=]
     *
     * Returns a weekly attendance grid identical in shape to the schedule grid:
     * one row per employee, 7 day columns with status and clock times.
     * Also returns summary counts for the week.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'week_start'    => ['required', 'date_format:Y-m-d'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'status'        => ['nullable', 'string', 'in:on_time,late,left_early_std,left_early_earl,combined,absent,off'],
            'search'        => ['nullable', 'string', 'max:100'],
        ]);

        $weekStart = $request->input('week_start');
        $deptId    = $request->input('department_id');
        $status    = $request->input('status');
        $search    = $request->input('search');

        $weekEnd = Carbon::parse($weekStart)->addDays(6)->toDateString();
        $dates   = collect(range(0, 6))->map(
            fn ($i) => Carbon::parse($weekStart)->addDays($i)->toDateString()
        );

        // Build employee query
        $employeeQuery = User::with(['level', 'tier'])
            ->when($deptId, fn ($q) => $q->where('department_id', $deptId))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nickname', 'like', "%{$search}%");
            }))
            ->orderBy('name');

        $employees = $employeeQuery->get();
        $userIds   = $employees->pluck('id')->all();

        // Fetch all attendance statuses for those employees in the week
        $statusQuery = AttendanceStatus::whereIn('user_id', $userIds)
            ->whereBetween('attendance_date', [$weekStart, $weekEnd]);

        if ($status) {
            $statusQuery->where('status', $status);
        }

        $statuses = $statusQuery->get();

        // When a status filter is applied, restrict employees to those with ≥1 matching record
        if ($status) {
            $matchedUserIds = $statuses->pluck('user_id')->unique()->all();
            $employees      = $employees->filter(fn ($e) => in_array($e->id, $matchedUserIds))->values();
        }

        // Index: user_id → date → AttendanceStatus
        $statusIndex = [];
        foreach ($statuses as $s) {
            $statusIndex[$s->user_id][$s->attendance_date->toDateString()] = $s;
        }

        // Summary counts for the week (across all listed employees)
        $summary = [
            'on_time'         => 0,
            'late'            => 0,
            'left_early_std'  => 0,
            'left_early_earl' => 0,
            'combined'        => 0,
            'absent'          => 0,
            'off'             => 0,
        ];

        $grid = $employees->map(function (User $employee) use ($dates, $statusIndex, &$summary) {
            $days = $dates->map(function (string $date) use ($employee, $statusIndex, &$summary) {
                $s = $statusIndex[$employee->id][$date] ?? null;

                if ($s && isset($summary[$s->status])) {
                    $summary[$s->status]++;
                }

                return [
                    'date'          => $date,
                    'status'        => $s?->status,
                    'clock_in'      => $s?->clock_in,
                    'clock_out'     => $s?->clock_out,
                    'late_minutes'  => $s?->late_minutes,
                    'early_minutes' => $s?->early_minutes,
                ];
            });

            return [
                'user_id'  => $employee->id,
                'name'     => $employee->name,
                'nickname' => $employee->nickname,
                'level'    => $employee->level ? ['id' => $employee->level->id, 'code' => $employee->level->code, 'name' => $employee->level->name] : null,
                'tier'     => $employee->tier ? ['id' => $employee->tier->id, 'name' => $employee->tier->tier_name] : null,
                'days'     => $days,
            ];
        });

        return response()->json([
            'data' => [
                'week_start' => $weekStart,
                'week_end'   => $weekEnd,
                'summary'    => $summary,
                'employees'  => $grid,
            ],
        ]);
    }

    /**
     * GET /api/audit/cell?user_id=&date=YYYY-MM-DD
     * Returns the detailed attendance entry for a single employee-day cell.
     */
    public function cell(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date'    => ['required', 'date_format:Y-m-d'],
        ]);

        $userId = $request->integer('user_id');
        $date   = $request->input('date');

        $status = AttendanceStatus::with(['user', 'shiftAssignment.shift', 'fingerprintRecord'])
            ->where('user_id', $userId)
            ->where('attendance_date', $date)
            ->first();

        if (! $status) {
            return response()->json(['data' => null]);
        }

        $shift = $status->shiftAssignment?->shift;

        return response()->json([
            'data' => [
                'user_id'        => $status->user_id,
                'name'           => $status->user?->name,
                'attendance_date' => $date,
                'status'         => $status->status,
                'clock_in'       => $status->clock_in,
                'clock_out'      => $status->clock_out,
                'late_minutes'   => $status->late_minutes,
                'early_minutes'  => $status->early_minutes,
                'shift'          => $shift ? [
                    'id'         => $shift->id,
                    'name'       => $shift->name,
                    'start_time' => $shift->start_time,
                    'end_time'   => $shift->end_time,
                ] : null,
                'fingerprint'    => $status->fingerprintRecord ? [
                    'id'        => $status->fingerprintRecord->id,
                    'clock_in'  => $status->fingerprintRecord->clock_in,
                    'clock_out' => $status->fingerprintRecord->clock_out,
                ] : null,
            ],
        ]);
    }
}
