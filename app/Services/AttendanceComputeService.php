<?php

namespace App\Services;

use App\Models\AttendanceStatus;
use App\Models\FingerprintRecord;
use App\Models\ShiftAssignment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Computes attendance status for a given set of shift assignments
 * and their matching fingerprint records.
 *
 * Status definitions (SRS §3 M3):
 *   on_time         – clock_in ≤ shift_start + 5 min  AND  clock_out ≥ shift_end
 *   late            – clock_in > shift_start + 5 min  AND  clock_out ≥ shift_end
 *   left_early_std  – clock_out after 60 % of shift but before shift_end (standard)
 *   left_early_earl – clock_out before 60 % of shift has elapsed (early exit)
 *   combined        – both late AND left early (any variant)
 *   absent          – no fingerprint record exists for that working day
 *   off             – assignment_type is day_off / sick_day / leave_request → skip
 */
class AttendanceComputeService
{
    public const GRACE_MINUTES   = 5;
    public const THRESHOLD_PCT   = 0.60;   // 60 % of shift duration must elapse

    /**
     * Recompute attendance for all shift-type assignments in the given date range.
     * Upserts one AttendanceStatus row per (user_id, attendance_date).
     *
     * @param  string|null  $deptId   Scope to a single department (null = all)
     * @param  string|null  $weekStart  Scope to a single week (null = recompute all)
     */
    public function recompute(?int $deptId = null, ?string $weekStart = null): void
    {
        $query = ShiftAssignment::with(['weeklySchedule', 'shift'])
            ->whereHas('weeklySchedule', function ($q) use ($deptId, $weekStart) {
                if ($deptId) {
                    $q->where('department_id', $deptId);
                }
                if ($weekStart) {
                    $q->where('week_start', $weekStart);
                }
            });

        $assignments = $query->get();

        // Gather all relevant (user_id, date) pairs so we can batch-fetch fingerprint records
        $userDatePairs = $assignments->map(fn ($a) => [
            'user_id' => $a->user_id,
            'date'    => $a->assignment_date->toDateString(),
        ])->unique(fn ($item) => $item['user_id'] . '_' . $item['date']);

        // Index fingerprint records by user_id → date
        $fingerprintIndex = $this->buildFingerprintIndex($userDatePairs);

        foreach ($assignments as $assignment) {
            $date   = $assignment->assignment_date->toDateString();
            $userId = $assignment->user_id;
            $type   = $assignment->assignment_type;

            // Off days: record status = 'off', skip computation
            if ($type !== 'shift') {
                AttendanceStatus::updateOrCreate(
                    ['user_id' => $userId, 'attendance_date' => $date],
                    [
                        'fingerprint_record_id' => null,
                        'shift_assignment_id'   => $assignment->id,
                        'status'                => 'off',
                        'clock_in'              => null,
                        'clock_out'             => null,
                        'late_minutes'          => null,
                        'early_minutes'         => null,
                    ]
                );
                continue;
            }

            /** @var \App\Models\Shift|null $shift */
            $shift = $assignment->shift;

            if (! $shift) {
                continue; // data integrity issue — skip
            }

            /** @var FingerprintRecord|null $fp */
            $fp = $fingerprintIndex[$userId][$date] ?? null;

            if (! $fp) {
                // Absent: working day but no fingerprint record
                AttendanceStatus::updateOrCreate(
                    ['user_id' => $userId, 'attendance_date' => $date],
                    [
                        'fingerprint_record_id' => null,
                        'shift_assignment_id'   => $assignment->id,
                        'status'                => 'absent',
                        'clock_in'              => null,
                        'clock_out'             => null,
                        'late_minutes'          => null,
                        'early_minutes'         => null,
                    ]
                );
                continue;
            }

            $result = $this->computeEntry($shift, $fp, $date);

            AttendanceStatus::updateOrCreate(
                ['user_id' => $userId, 'attendance_date' => $date],
                array_merge($result, [
                    'fingerprint_record_id' => $fp->id,
                    'shift_assignment_id'   => $assignment->id,
                ])
            );
        }
    }

    /**
     * Compute a single attendance entry.
     * Returns an array ready to be upserted into AttendanceStatus.
     */
    public function computeEntry(\App\Models\Shift $shift, FingerprintRecord $fp, string $date): array
    {
        $shiftStart = Carbon::parse($date . ' ' . $shift->start_time);
        $shiftEnd   = Carbon::parse($date . ' ' . $shift->end_time);

        // Overnight shift: end time < start time → shift ends next day
        if ($shift->end_time < $shift->start_time) {
            $shiftEnd->addDay();
        }

        $shiftDurationMinutes = $shiftStart->diffInMinutes($shiftEnd); // always positive

        // Grace boundary: clock_in allowed up to start + GRACE_MINUTES
        $graceBoundary = $shiftStart->copy()->addMinutes(self::GRACE_MINUTES);

        // 60 % threshold: the earliest allowed clock_out that counts as "standard left early"
        $threshold60 = $shiftStart->copy()->addMinutes((int) round($shiftDurationMinutes * self::THRESHOLD_PCT));

        $clockIn  = $fp->clock_in  ? Carbon::parse($date . ' ' . $fp->clock_in)  : null;
        $clockOut = $fp->clock_out ? Carbon::parse($date . ' ' . $fp->clock_out) : null;

        // Overnight adjustments for actual clock times
        if ($clockIn  && $shift->end_time < $shift->start_time && $clockIn->lt($shiftStart)) {
            $clockIn->addDay();
        }
        if ($clockOut && $shift->end_time < $shift->start_time && $clockOut->lt($shiftStart)) {
            $clockOut->addDay();
        }

        $isLate       = $clockIn  && $clockIn->gt($graceBoundary);
        $isLeftEarly  = $clockOut && $clockOut->lt($shiftEnd);
        $isEarlyExit  = $clockOut && $clockOut->lt($threshold60); // before 60 %

        // Carbon 3: diffInMinutes is signed — use abs() for minutes that represent a magnitude
        $lateMinutes  = $isLate      ? (int) abs($graceBoundary->diffInMinutes($clockIn)) : null;
        $earlyMinutes = $isLeftEarly ? (int) abs($clockOut->diffInMinutes($shiftEnd))      : null;

        // Status logic
        if ($isLate && $isLeftEarly) {
            $status = 'combined';
        } elseif ($isLate) {
            $status = 'late';
        } elseif ($isEarlyExit) {
            $status = 'left_early_earl';
        } elseif ($isLeftEarly) {
            $status = 'left_early_std';
        } else {
            $status = 'on_time';
        }

        return [
            'status'        => $status,
            'clock_in'      => $fp->clock_in,
            'clock_out'     => $fp->clock_out,
            'late_minutes'  => $lateMinutes,
            'early_minutes' => $earlyMinutes,
        ];
    }

    /**
     * Build a 2-D array [ user_id => [ 'Y-m-d' => FingerprintRecord ] ]
     * from a collection of {user_id, date} pairs.
     */
    private function buildFingerprintIndex(Collection $pairs): array
    {
        if ($pairs->isEmpty()) {
            return [];
        }

        $records = FingerprintRecord::whereIn(
            DB::raw("CONCAT(user_id, '_', record_date)"),
            $pairs->map(fn ($p) => $p['user_id'] . '_' . $p['date'])->all()
        )->get();

        $index = [];
        foreach ($records as $fp) {
            $index[$fp->user_id][$fp->record_date->toDateString()] = $fp;
        }

        return $index;
    }
}
