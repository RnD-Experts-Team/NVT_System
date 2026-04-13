<?php

namespace App\Services;

use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WeeklySchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ScheduleSaveService
{
    /**
     * Full-week replace for a department's schedule.
     *
     * Status rules (status never goes backwards via this service):
     *   draft   + publish false → keep draft
     *   draft   + publish true  → validate all employees assigned, flip to published
     *   published + publish false → keep published
     *   published + publish true  → re-validate, refresh published_at + published_by
     *
     * @param  array{
     *   department_id: int,
     *   week_start: string,
     *   assignments: array,
     *   changed_by: int,
     * }  $data
     * @param  bool  $publish
     * @param  bool  $forceDraft  Used by Excel import to always reset to draft
     * @return array{ schedule: WeeklySchedule, errors: array }
     */
    public function save(array $data, bool $publish, bool $forcedraft = false): array
    {
        $deptId    = $data['department_id'];
        $weekStart = $data['week_start'];
        $changedBy = $data['changed_by'];
        $assignments = $data['assignments'];

        $errors = [];

        DB::beginTransaction();

        try {
            // ── Find or create the weekly schedule ─────────────────────────
            $schedule = WeeklySchedule::firstOrCreate(
                ['department_id' => $deptId, 'week_start' => $weekStart],
                ['status' => 'draft']
            );

            // ── Snapshot existing assignments for history diff ──────────────
            $previous = ShiftAssignment::where('weekly_schedule_id', $schedule->id)
                ->get()
                ->keyBy(fn ($a) => $a->user_id . '_' . $a->assignment_date->toDateString());

            // ── Delete all existing assignments for this week + dept ────────
            ShiftAssignment::where('weekly_schedule_id', $schedule->id)->forceDelete();

            // ── Insert new assignments ─────────────────────────────────────
            $now = now();

            foreach ($assignments as $index => $row) {
                // Validate required fields per row
                if (empty($row['user_id']) || empty($row['date']) || empty($row['type'])) {
                    $errors[] = ['row' => $index + 1, 'reason' => 'Missing user_id, date, or type'];
                    continue;
                }

                if ($row['type'] === 'shift' && empty($row['shift_id'])) {
                    $errors[] = ['row' => $index + 1, 'reason' => 'shift_id required when type is shift'];
                    continue;
                }

                $assignment = ShiftAssignment::create([
                    'weekly_schedule_id' => $schedule->id,
                    'user_id'            => $row['user_id'],
                    'assignment_date'    => $row['date'],
                    'assignment_type'    => $row['type'],
                    'shift_id'           => $row['shift_id'] ?? null,
                    'is_cover'           => $row['is_cover'] ?? false,
                    'cover_for_user_id'  => $row['cover_for_user_id'] ?? null,
                    'cover_shift_id'     => $row['cover_shift_id'] ?? null,
                    'comment'            => $row['comment'] ?? null,
                ]);

                // ── Record history entry ────────────────────────────────────
                $key = $row['user_id'] . '_' . $row['date'];
                $prev = $previous->get($key);

                $assignment->history()->create([
                    'changed_by'        => $changedBy,
                    'previous_type'     => $prev?->assignment_type,
                    'previous_shift_id' => $prev?->shift_id,
                    'new_type'          => $assignment->assignment_type,
                    'new_shift_id'      => $assignment->shift_id,
                    'comment'           => null,
                    'changed_at'        => $now,
                ]);
            }

            // ── Status logic ───────────────────────────────────────────────
            if ($forcedraft) {
                // Excel import: always reset to draft
                $schedule->update(['status' => 'draft', 'published_at' => null, 'published_by' => null]);
            } elseif ($publish) {
                // Validate all active employees have an assignment for every day
                $validationError = $this->validatePublish($schedule, $deptId, $weekStart);

                if ($validationError !== null) {
                    DB::rollBack();
                    return ['schedule' => null, 'errors' => [], 'publish_error' => $validationError];
                }

                $schedule->update([
                    'status'       => 'published',
                    'published_at' => $now,
                    'published_by' => $changedBy,
                ]);
            }
            // If publish:false and schedule is already published → leave it published (no change)

            DB::commit();

            $schedule->load(['department', 'publisher', 'assignments.user', 'assignments.shift', 'assignments.coverForUser']);

            return ['schedule' => $schedule, 'errors' => $errors, 'publish_error' => null];

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Validate that every active employee in the department has an assignment
     * for every day of the week. Returns an error message or null if valid.
     */
    private function validatePublish(WeeklySchedule $schedule, int $deptId, string $weekStart): ?array
    {
        $dates = collect(range(0, 6))->map(
            fn ($i) => Carbon::parse($weekStart)->addDays($i)->toDateString()
        );

        $employees = User::where('department_id', $deptId)
            ->where('is_active', true)
            ->get();

        $assignedPairs = ShiftAssignment::where('weekly_schedule_id', $schedule->id)
            ->get()
            ->map(fn ($a) => $a->user_id . '_' . $a->assignment_date->toDateString())
            ->flip();

        $missingCells = [];

        foreach ($employees as $emp) {
            $missingDates = $dates->filter(
                fn ($d) => ! isset($assignedPairs[$emp->id . '_' . $d])
            )->values();

            if ($missingDates->isNotEmpty()) {
                $missingCells[] = [
                    'user_id'       => $emp->id,
                    'name'          => $emp->name,
                    'missing_dates' => $missingDates->all(),
                ];
            }
        }

        if (! empty($missingCells)) {
            return [
                'message'       => 'Cannot publish: schedule is incomplete.',
                'missing_count' => collect($missingCells)->sum(fn ($m) => count($m['missing_dates'])),
                'missing_cells' => $missingCells,
            ];
        }

        return null;
    }

    /**
     * Copy the previous week's assignments to the target week as a draft.
     *
     * @return array{message: string, assignments_copied: int, week_start: string}
     */
    public function copyLastWeek(int $deptId, string $weekStart, int $changedBy): array
    {
        $prevWeekStart = Carbon::parse($weekStart)->subWeek()->toDateString();

        $prevSchedule = WeeklySchedule::with('assignments')
            ->where('department_id', $deptId)
            ->forWeek($prevWeekStart)
            ->first();

        if (! $prevSchedule || $prevSchedule->assignments->isEmpty()) {
            abort(422, 'No previous week schedule found to copy from.');
        }

        $copied = 0;

        DB::transaction(function () use ($prevSchedule, $weekStart, $deptId, $changedBy, &$copied) {
            $newSchedule = WeeklySchedule::firstOrCreate(
                ['department_id' => $deptId, 'week_start' => $weekStart],
                ['status' => 'draft']
            );

            foreach ($prevSchedule->assignments as $old) {
                $dayOffset = Carbon::parse($prevSchedule->week_start)->diffInDays(
                    Carbon::parse($old->assignment_date)
                );
                $newDate = Carbon::parse($weekStart)->addDays($dayOffset)->toDateString();

                $newAssignment = ShiftAssignment::updateOrCreate(
                    [
                        'weekly_schedule_id' => $newSchedule->id,
                        'user_id'            => $old->user_id,
                        'assignment_date'    => $newDate,
                    ],
                    [
                        'assignment_type'   => $old->assignment_type,
                        'shift_id'          => $old->shift_id,
                        'is_cover'          => $old->is_cover,
                        'cover_for_user_id' => $old->cover_for_user_id,
                        'cover_shift_id'    => $old->cover_shift_id,
                        'comment'           => $old->comment,
                    ]
                );

                $newAssignment->history()->create([
                    'changed_by'        => $changedBy,
                    'previous_type'     => null,
                    'previous_shift_id' => null,
                    'new_type'          => $newAssignment->assignment_type,
                    'new_shift_id'      => $newAssignment->shift_id,
                    'comment'           => 'Copied from previous week',
                    'changed_at'        => now(),
                ]);

                $copied++;
            }
        });

        return [
            'message'            => 'Previous week copied successfully as draft.',
            'assignments_copied' => $copied,
            'week_start'         => $weekStart,
        ];
    }
}
