<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WeeklySchedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/schedules?week_start=YYYY-MM-DD
    //  Returns the full weekly grid for the manager's department.
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'week_start' => ['required', 'date_format:Y-m-d'],
        ]);

        $weekStart  = $request->input('week_start');
        $deptId     = $request->user()->department_id;

        $schedule = WeeklySchedule::with([
            'assignments.user.level',
            'assignments.user.tier',
            'assignments.shift',
            'assignments.coverForUser',
            'assignments.history',
        ])
            ->where('department_id', $deptId)
            ->forWeek($weekStart)
            ->first();

        // Build the 7-day date range (Mon–Sun)
        $dates = collect(range(0, 6))->map(
            fn ($i) => Carbon::parse($weekStart)->addDays($i)->toDateString()
        );

        // All employees in this department
        $employees = User::with(['level', 'tier'])
            ->where('department_id', $deptId)
            ->orderBy('name')
            ->get();

        // Index assignments by user_id + date for quick lookup
        $assignmentMap = [];
        if ($schedule) {
            foreach ($schedule->assignments as $a) {
                $assignmentMap[$a->user_id][$a->assignment_date->toDateString()] = $a;
            }
        }

        $grid = $employees->map(function (User $employee) use ($dates, $assignmentMap) {
            $days = $dates->map(function (string $date) use ($employee, $assignmentMap) {
                $a = $assignmentMap[$employee->id][$date] ?? null;
                return $this->serializeCell($date, $a);
            });

            return [
                'user_id'   => $employee->id,
                'name'      => $employee->name,
                'nickname'  => $employee->nickname,
                'level'     => $employee->level ? ['id' => $employee->level->id, 'code' => $employee->level->code, 'name' => $employee->level->name] : null,
                'tier'      => $employee->tier ? ['id' => $employee->tier->id, 'name' => $employee->tier->tier_name] : null,
                'days'      => $days,
            ];
        });

        return response()->json([
            'data' => [
                'week_start'     => $weekStart,
                'week_end'       => Carbon::parse($weekStart)->addDays(6)->toDateString(),
                'department_id'  => $deptId,
                'schedule_id'    => $schedule?->id,
                'status'         => $schedule?->status ?? 'none',
                'published_at'   => $schedule?->published_at,
                'employees'      => $grid,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/schedules/day?week_start=YYYY-MM-DD&date=YYYY-MM-DD
    // ─────────────────────────────────────────────────────────────────────────
    public function day(Request $request): JsonResponse
    {
        $request->validate([
            'week_start' => ['required', 'date_format:Y-m-d'],
            'date'       => ['required', 'date_format:Y-m-d'],
        ]);

        $weekStart  = $request->input('week_start');
        $date       = $request->input('date');
        $deptId     = $request->user()->department_id;

        $schedule = WeeklySchedule::where('department_id', $deptId)
            ->forWeek($weekStart)
            ->first();

        $employees = User::with(['level', 'tier'])
            ->where('department_id', $deptId)
            ->orderBy('name')
            ->get();

        $assignmentMap = [];
        if ($schedule) {
            $assignments = ShiftAssignment::with(['shift', 'coverForUser', 'history'])
                ->where('weekly_schedule_id', $schedule->id)
                ->where('assignment_date', $date)
                ->get();

            foreach ($assignments as $a) {
                $assignmentMap[$a->user_id] = $a;
            }
        }

        $counts = ['shift' => 0, 'day_off' => 0, 'sick_day' => 0, 'leave_request' => 0, 'unassigned' => 0];

        $rows = $employees->map(function (User $employee) use ($date, $assignmentMap, &$counts) {
            $a = $assignmentMap[$employee->id] ?? null;
            $type = $a ? $a->assignment_type : null;

            if ($type) {
                $counts[$type]++;
            } else {
                $counts['unassigned']++;
            }

            return array_merge(
                [
                    'user_id'  => $employee->id,
                    'name'     => $employee->name,
                    'nickname' => $employee->nickname,
                    'level'    => $employee->level?->code,
                ],
                $this->serializeCell($date, $a)
            );
        });

        return response()->json([
            'data' => [
                'date'      => $date,
                'week_start' => $weekStart,
                'summary'   => $counts,
                'employees' => $rows,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/schedules/export?week_start=YYYY-MM-DD
    //  Streams a CSV of shift assignments for the week.
    // ─────────────────────────────────────────────────────────────────────────
    public function export(Request $request)
    {
        $request->validate([
            'week_start' => ['required', 'date_format:Y-m-d'],
        ]);

        $weekStart  = $request->input('week_start');
        $deptId     = $request->user()->department_id;

        $schedule = WeeklySchedule::with(['assignments.user', 'assignments.shift'])
            ->where('department_id', $deptId)
            ->forWeek($weekStart)
            ->first();

        $dates = collect(range(0, 6))->map(
            fn ($i) => Carbon::parse($weekStart)->addDays($i)->toDateString()
        );

        $employees = User::where('department_id', $deptId)->orderBy('name')->get();

        $assignmentMap = [];
        if ($schedule) {
            foreach ($schedule->assignments as $a) {
                $assignmentMap[$a->user_id][$a->assignment_date->toDateString()] = $a;
            }
        }

        $filename = 'schedule_' . $weekStart . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($employees, $dates, $assignmentMap) {
            $handle = fopen('php://output', 'w');

            // Header row
            $dayLabels = $dates->map(fn ($d) => Carbon::parse($d)->format('D d/m'))->toArray();
            fputcsv($handle, array_merge(['Employee'], $dayLabels));

            foreach ($employees as $employee) {
                $row = [$employee->name];
                foreach ($dates as $date) {
                    $a    = $assignmentMap[$employee->id][$date] ?? null;
                    $cell = $a ? ($a->assignment_type === 'shift' && $a->shift ? $a->shift->name : $a->assignment_type) : '';
                    $row[] = $cell;
                }
                fputcsv($handle, $row);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/schedules/publish-status?week_start=YYYY-MM-DD
    // ─────────────────────────────────────────────────────────────────────────
    public function publishStatus(Request $request): JsonResponse
    {
        $request->validate([
            'week_start' => ['required', 'date_format:Y-m-d'],
        ]);

        $weekStart  = $request->input('week_start');
        $deptId     = $request->user()->department_id;

        $schedule = WeeklySchedule::withCount('assignments')
            ->where('department_id', $deptId)
            ->forWeek($weekStart)
            ->first();

        $totalEmployees  = User::where('department_id', $deptId)->count();
        $totalRequired   = $totalEmployees * 7;
        $totalFilled     = $schedule ? $schedule->assignments_count : 0;
        $missingCount    = max(0, $totalRequired - $totalFilled);

        return response()->json([
            'data' => [
                'week_start'       => $weekStart,
                'status'           => $schedule?->status ?? 'none',
                'published_at'     => $schedule?->published_at,
                'total_required'   => $totalRequired,
                'total_filled'     => $totalFilled,
                'missing_count'    => $missingCount,
                'can_publish'      => $missingCount === 0,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/schedules/copy-last-week
    //  Body: { week_start: "YYYY-MM-DD" }
    // ─────────────────────────────────────────────────────────────────────────
    public function copyLastWeek(Request $request): JsonResponse
    {
        $request->validate([
            'week_start' => ['required', 'date_format:Y-m-d'],
        ]);

        $weekStart     = $request->input('week_start');
        $prevWeekStart = Carbon::parse($weekStart)->subWeek()->toDateString();
        $deptId        = $request->user()->department_id;
        $userId        = $request->user()->id;

        $prevSchedule = WeeklySchedule::with('assignments')
            ->where('department_id', $deptId)
            ->forWeek($prevWeekStart)
            ->first();

        if (! $prevSchedule || $prevSchedule->assignments->isEmpty()) {
            return response()->json([
                'message' => 'No previous week schedule found to copy from.',
            ], 422);
        }

        $copied = 0;

        DB::transaction(function () use ($prevSchedule, $weekStart, $deptId, $userId, &$copied) {
            $newSchedule = WeeklySchedule::firstOrCreate(
                ['department_id' => $deptId, 'week_start' => $weekStart],
                ['status' => 'draft']
            );

            foreach ($prevSchedule->assignments as $old) {
                $dayOffset   = Carbon::parse($prevSchedule->week_start)->diffInDays(Carbon::parse($old->assignment_date));
                $newDate     = Carbon::parse($weekStart)->addDays($dayOffset)->toDateString();

                $newAssignment = ShiftAssignment::updateOrCreate(
                    [
                        'weekly_schedule_id' => $newSchedule->id,
                        'user_id'            => $old->user_id,
                        'assignment_date'    => $newDate,
                    ],
                    [
                        'assignment_type'  => $old->assignment_type,
                        'shift_id'         => $old->shift_id,
                        'is_cover'         => $old->is_cover,
                        'cover_for_user_id' => $old->cover_for_user_id,
                        'cover_shift_id'   => $old->cover_shift_id,
                        'comment'          => $old->comment,
                    ]
                );

                $newAssignment->history()->create([
                    'changed_by'   => $userId,
                    'previous_type' => null,
                    'new_type'     => $newAssignment->assignment_type,
                    'new_shift_id' => $newAssignment->shift_id,
                    'comment'      => 'Copied from previous week',
                    'changed_at'   => now(),
                ]);

                $copied++;
            }
        });

        return response()->json([
            'data' => [
                'message'            => 'Previous week copied successfully.',
                'assignments_copied' => $copied,
                'week_start'         => $weekStart,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/schedules/publish
    //  Body: { week_start: "YYYY-MM-DD" }
    // ─────────────────────────────────────────────────────────────────────────
    public function publish(Request $request): JsonResponse
    {
        $request->validate([
            'week_start' => ['required', 'date_format:Y-m-d'],
        ]);

        $weekStart = $request->input('week_start');
        $deptId    = $request->user()->department_id;

        $schedule = WeeklySchedule::with('assignments.user')
            ->where('department_id', $deptId)
            ->forWeek($weekStart)
            ->first();

        if (! $schedule) {
            return response()->json(['message' => 'No schedule found for this week.'], 422);
        }

        // Build date range
        $dates = collect(range(0, 6))->map(
            fn ($i) => Carbon::parse($weekStart)->addDays($i)->toDateString()
        );

        $employees = User::where('department_id', $deptId)->get();

        // Find missing cells
        $assignedPairs = $schedule->assignments->map(
            fn ($a) => $a->user_id . '_' . $a->assignment_date->toDateString()
        )->flip();

        $missingCells = [];
        foreach ($employees as $emp) {
            $missingDates = $dates->filter(
                fn ($d) => ! isset($assignedPairs[$emp->id . '_' . $d])
            )->values();

            if ($missingDates->isNotEmpty()) {
                $missingCells[] = [
                    'user_id'       => $emp->id,
                    'name'          => $emp->name,
                    'missing_dates' => $missingDates,
                ];
            }
        }

        if (! empty($missingCells)) {
            return response()->json([
                'message'       => 'Cannot publish: schedule is incomplete.',
                'missing_count' => collect($missingCells)->sum(fn ($m) => count($m['missing_dates'])),
                'missing_cells' => $missingCells,
            ], 422);
        }

        $schedule->update([
            'status'       => 'published',
            'published_at' => now(),
            'published_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => [
                'message'           => 'Schedule published successfully.',
                'week_start'        => $weekStart,
                'published_at'      => $schedule->published_at,
                'published_by_name' => $request->user()->name,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Private helper: serialize a shift assignment cell
    // ─────────────────────────────────────────────────────────────────────────
    private function serializeCell(string $date, ?ShiftAssignment $a): array
    {
        if (! $a) {
            return [
                'date'             => $date,
                'assignment_id'    => null,
                'assignment_type'  => null,
                'shift'            => null,
                'is_cover'         => false,
                'cover_for_user'   => null,
                'comment'          => null,
                'history_count'    => 0,
            ];
        }

        return [
            'date'            => $date,
            'assignment_id'   => $a->id,
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
            'history_count'   => $a->relationLoaded('history') ? $a->history->count() : 0,
        ];
    }
}
