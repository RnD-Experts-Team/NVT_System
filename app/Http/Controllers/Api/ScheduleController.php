<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CopyLastWeekRequest;
use App\Http\Requests\DayScheduleRequest;
use App\Http\Requests\ImportExcelRequest;
use App\Http\Requests\SaveScheduleRequest;
use App\Http\Resources\WeeklyScheduleResource;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WeeklySchedule;
use App\Services\ExcelScheduleImportService;
use App\Services\ScheduleSaveService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function __construct(
        private ScheduleSaveService $saveService,
        private ExcelScheduleImportService $importService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'department_id'   => ['required', 'integer', 'exists:departments,id'],
            'week_start'      => ['required', 'date_format:Y-m-d'],
            'search'          => ['nullable', 'string', 'max:100'],
            'shift_id'        => ['nullable', 'integer', 'exists:shifts,id'],
            'assignment_type' => ['nullable', 'string', 'in:shift,day_off,sick_day,leave_request'],
        ]);

        $deptId    = $request->integer('department_id');
        $weekStart = $request->input('week_start');

        $schedule = WeeklySchedule::with([
            'department', 'publisher',
            'assignments.user', 'assignments.shift',
            'assignments.coverForUser', 'assignments.history',
        ])
            ->where('department_id', $deptId)
            ->forWeek($weekStart)
            ->first();

        // ── Summary (always based on full unfiltered data) ────────────────────
        $totalEmployees = User::where('department_id', $deptId)->count();

        $typeKeys = ['shift', 'day_off', 'sick_day', 'leave_request'];

        $dates = collect(range(0, 6))
            ->map(fn ($i) => Carbon::parse($weekStart)->addDays($i)->toDateString());

        $perDay = $dates->map(function (string $date) use ($schedule, $totalEmployees, $typeKeys) {
            $counts = array_fill_keys($typeKeys, 0);
            if ($schedule) {
                foreach ($schedule->assignments as $a) {
                    if ($a->assignment_date->toDateString() === $date && isset($counts[$a->assignment_type])) {
                        $counts[$a->assignment_type]++;
                    }
                }
            }
            $assigned             = array_sum($counts);
            $counts['unassigned'] = max(0, $totalEmployees - $assigned);
            return ['date' => $date] + $counts;
        })->values()->all();

        $overall = array_fill_keys($typeKeys, 0);
        if ($schedule) {
            foreach ($schedule->assignments as $a) {
                if (isset($overall[$a->assignment_type])) {
                    $overall[$a->assignment_type]++;
                }
            }
        }
        $overall['unassigned'] = max(0, $totalEmployees * 7 - array_sum($overall));

        $summary = [
            'total_employees' => $totalEmployees,
            'overall'         => $overall,
            'per_day'         => $perDay,
        ];
        // ─────────────────────────────────────────────────────────────────────

        // ── In-memory filters (applied after loading, no extra queries) ───────
        if ($schedule && $request->hasAny(['search', 'shift_id', 'assignment_type'])) {
            $assignments = $schedule->assignments;

            if ($request->filled('search')) {
                $search  = strtolower($request->input('search'));
                $userIds = $assignments->filter(
                    fn ($a) => str_contains(strtolower($a->user?->name ?? ''), $search)
                            || str_contains(strtolower($a->user?->nickname ?? ''), $search)
                )->pluck('user_id')->unique();
                $assignments = $assignments->whereIn('user_id', $userIds->all());
            }

            if ($request->filled('shift_id')) {
                $shiftId = $request->integer('shift_id');
                $userIds = $assignments->where('shift_id', $shiftId)->pluck('user_id')->unique();
                $assignments = $assignments->whereIn('user_id', $userIds->all());
            }

            if ($request->filled('assignment_type')) {
                $type    = $request->input('assignment_type');
                $userIds = $assignments->where('assignment_type', $type)->pluck('user_id')->unique();
                $assignments = $assignments->whereIn('user_id', $userIds->all());
            }

            $schedule->setRelation('assignments', $assignments->values());
        }
        // ─────────────────────────────────────────────────────────────────────

        return response()->json([
            'data'    => $schedule ? new WeeklyScheduleResource($schedule) : null,
            'summary' => $summary,
        ]);
    }

    public function save(SaveScheduleRequest $request): JsonResponse
    {
        $data    = $request->validated();
        $publish = $request->boolean('publish', false);

        $result = $this->saveService->save([
            'department_id' => $data['department_id'],
            'week_start'    => $data['week_start'],
            'assignments'   => $data['assignments'],
            'changed_by'    => $request->user()->id,
        ], $publish);

        if ($result['publish_error'] !== null) {
            return response()->json($result['publish_error'], 422);
        }

        return response()->json([
            'data'   => new WeeklyScheduleResource($result['schedule']),
            'errors' => $result['errors'],
        ]);
    }

    public function day(DayScheduleRequest $request): JsonResponse
    {
        $deptId      = $request->integer('department_id');
        $date        = $request->input('date');
        $weekStart   = Carbon::parse($date)->startOfWeek(Carbon::MONDAY)->toDateString();
        $withHistory = $request->boolean('with_history', false);

        $schedule = WeeklySchedule::where('department_id', $deptId)
            ->forWeek($weekStart)
            ->first();

        $employees = User::with(['level', 'tier'])
            ->where('department_id', $deptId)
            ->orderBy('name')
            ->get();

        $assignmentMap = [];
        if ($schedule) {
            $eagerRelations = ['shift', 'coverForUser', 'history'];

            if ($withHistory) {
                $eagerRelations = array_merge($eagerRelations, [
                    'history.changedByUser', 'history.previousShift', 'history.newShift',
                ]);
            }

            $assignments = ShiftAssignment::with($eagerRelations)
                ->where('weekly_schedule_id', $schedule->id)
                ->where('assignment_date', $date)
                ->get();

            foreach ($assignments as $a) {
                $assignmentMap[$a->user_id] = $a;
            }
        }

        $counts = ['shift' => 0, 'day_off' => 0, 'sick_day' => 0, 'leave_request' => 0, 'unassigned' => 0];

        $rows = $employees->map(function (User $employee) use ($date, $assignmentMap, &$counts, $withHistory) {
            $a    = $assignmentMap[$employee->id] ?? null;
            $type = $a?->assignment_type;

            if ($type) {
                $counts[$type]++;
            } else {
                $counts['unassigned']++;
            }

            return array_merge([
                'user_id'  => $employee->id,
                'name'     => $employee->name,
                'nickname' => $employee->nickname,
                'level'    => $employee->level?->code,
            ], $this->serializeCell($date, $a, $withHistory));
        });

        return response()->json([
            'data' => [
                'date'       => $date,
                'week_start' => $weekStart,
                'summary'    => $counts,
                'employees'  => $rows,
            ],
        ]);
    }

    public function copyLastWeek(CopyLastWeekRequest $request): JsonResponse
    {
        $result = $this->saveService->copyLastWeek(
            $request->integer('department_id'),
            $request->input('week_start'),
            $request->user()->id
        );

        return response()->json(['data' => $result]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'week_start'    => ['required', 'date_format:Y-m-d'],
        ]);

        $deptId    = $request->integer('department_id');
        $weekStart = $request->input('week_start');

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
            $handle    = fopen('php://output', 'w');
            $dayLabels = $dates->map(fn ($d) => Carbon::parse($d)->format('D d/m'))->toArray();
            fputcsv($handle, array_merge(['Employee'], $dayLabels));

            foreach ($employees as $employee) {
                $row = [$employee->name];
                foreach ($dates as $date) {
                    $a     = $assignmentMap[$employee->id][$date] ?? null;
                    $row[] = $a
                        ? ($a->assignment_type === 'shift' && $a->shift ? $a->shift->name : $a->assignment_type)
                        : '';
                }
                fputcsv($handle, $row);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importExcel(ImportExcelRequest $request): JsonResponse
    {
        $result = $this->importService->import(
            $request->file('file'),
            $request->integer('department_id'),
            $request->input('week_start'),
            $request->user()->id
        );

        return response()->json([
            'message'       => 'Schedule imported as draft. Review and publish when ready.',
            'success_count' => $result['success_count'],
            'failed_count'  => $result['failed_count'],
            'failed_rows'   => $result['failed_rows'],
        ]);
    }

    public function downloadTemplate(Request $request)
    {
        $user = $request->user();

        // Admins can pass an explicit department_id; managers always use their own
        $deptId = $user->hasRole('admin') && $request->filled('department_id')
            ? $request->integer('department_id')
            : (int) $user->department_id;

        $weekStart = $request->filled('week_start')
            ? Carbon::parse($request->input('week_start'))->startOfWeek(Carbon::MONDAY)
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        // Pre-load real employees for this department ordered by name
        $employees = User::where('department_id', $deptId)
            ->orderBy('name')
            ->get(['name']);

        $dayHeaders = [];
        for ($i = 0; $i < 7; $i++) {
            $dayHeaders[] = $weekStart->copy()->addDays($i)->format('D d/m');
        }
        $colHeaders = array_merge(['Employee Name'], $dayHeaders);

        $filename = 'schedule_template_' . $weekStart->toDateString() . '.csv';

        return response()->streamDownload(function () use ($colHeaders, $weekStart, $employees) {
            $handle = fopen('php://output', 'w');
            // Rows 1-3 are skipped by the importer (banner, meta, spacer)
            fputcsv($handle, ['Weekly Schedule Import Template']);
            fputcsv($handle, ['Week Start:', $weekStart->toDateString()]);
            fputcsv($handle, []);
            // Row 4 — header row
            fputcsv($handle, $colHeaders);
            // Rows 5+ — one row per employee, all days default to "off"
            foreach ($employees as $employee) {
                fputcsv($handle, array_merge([$employee->name], array_fill(0, 7, 'off')));
            }
            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Private helpers
    // ─────────────────────────────────────────────────────────────────────────
    private function serializeCell(string $date, ?ShiftAssignment $a, bool $withHistory = false): array
    {
        if (! $a) {
            return [
                'date'            => $date,
                'assignment_id'   => null,
                'assignment_type' => null,
                'shift'           => null,
                'is_cover'        => false,
                'cover_for_user'  => null,
                'comment'         => null,
                'history_count'   => 0,
            ];
        }

        $cell = [
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
            'cover_for_user'  => $a->coverForUser
                ? ['id' => $a->coverForUser->id, 'name' => $a->coverForUser->name]
                : null,
            'comment'         => $a->comment,
            'history_count'   => $a->relationLoaded('history') ? $a->history->count() : 0,
        ];

        if ($withHistory && $a->relationLoaded('history')) {
            $cell['history'] = $a->history->map(fn ($h) => [
                'changed_at' => $h->changed_at,
                'changed_by' => $h->changedByUser
                    ? ['id' => $h->changedByUser->id, 'name' => $h->changedByUser->name]
                    : null,
                'old_type'   => $h->previous_type,
                'old_shift'  => $h->previousShift
                    ? ['id' => $h->previousShift->id, 'name' => $h->previousShift->name]
                    : null,
                'new_type'   => $h->new_type,
                'new_shift'  => $h->newShift
                    ? ['id' => $h->newShift->id, 'name' => $h->newShift->name]
                    : null,
                'comment'    => $h->comment,
            ])->values()->all();
        }

        return $cell;
    }
}
