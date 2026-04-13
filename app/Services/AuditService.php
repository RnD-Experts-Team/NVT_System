<?php

namespace App\Services;

use App\Models\AttendanceStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AuditService
{
    /**
     * Build the full attendance grid.
     *
     * @param  array      $filters        Validated input fields
     * @param  int|null   $forceDeptId    When set, overrides any department_id in $filters (manager auto-scope)
     * @return array{date_from: string, date_to: string, summary: array, employees: array}
     */
    public function grid(array $filters, ?int $forceDeptId = null): array
    {
        // Resolve date range
        if (! empty($filters['week_start'])) {
            $dateFrom = $filters['week_start'];
            $dateTo   = Carbon::parse($dateFrom)->addDays(6)->toDateString();
        } else {
            $dateFrom = $filters['date_from'];
            $dateTo   = $filters['date_to'] ?? $dateFrom;
        }

        $deptId    = $forceDeptId ?? ($filters['department_id'] ?? null);
        $userId    = $filters['user_id'] ?? null;
        $search    = $filters['search'] ?? null;
        $perPage   = (int) ($filters['per_page'] ?? 50);

        $statusFilter = ! empty($filters['status'])
            ? array_filter(array_map('trim', explode(',', $filters['status'])))
            : null;

        $dates = $this->buildDateRange($dateFrom, $dateTo);

        // Employee query
        $employeeQuery = User::with(['level', 'tier'])
            ->when($deptId, fn ($q) => $q->where('department_id', $deptId))
            ->when($userId, fn ($q) => $q->where('id', $userId))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nickname', 'like', "%{$search}%");
            }))
            ->orderBy('name');

        $employees = $employeeQuery->get();
        $userIds   = $employees->pluck('id')->all();

        // Attendance statuses
        $statusQuery = AttendanceStatus::with(['shiftAssignment.shift'])
            ->whereIn('user_id', $userIds)
            ->whereBetween('attendance_date', [$dateFrom, $dateTo]);

        if ($statusFilter) {
            $statusQuery->whereIn('status', $statusFilter);
        }

        $statuses = $statusQuery->get();

        if ($statusFilter) {
            $matchedUserIds = $statuses->pluck('user_id')->unique()->all();
            $employees      = $employees->filter(fn ($e) => in_array($e->id, $matchedUserIds))->values();
        }

        // Index: user_id → date → AttendanceStatus
        $statusIndex = [];
        foreach ($statuses as $s) {
            $statusIndex[$s->user_id][$s->attendance_date->toDateString()] = $s;
        }

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
                $s     = $statusIndex[$employee->id][$date] ?? null;
                $shift = $s?->shiftAssignment?->shift;

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
                    'shift'         => $shift ? [
                        'id'         => $shift->id,
                        'name'       => $shift->name,
                        'start_time' => $shift->start_time,
                        'end_time'   => $shift->end_time,
                    ] : null,
                ];
            });

            return [
                'user'  => [
                    'id'    => $employee->id,
                    'name'  => $employee->name,
                    'ac_no' => $employee->ac_no,
                ],
                'level' => $employee->level ? [
                    'id'   => $employee->level->id,
                    'code' => $employee->level->code,
                    'name' => $employee->level->name,
                ] : null,
                'tier'  => $employee->tier ? [
                    'id'   => $employee->tier->id,
                    'name' => $employee->tier->tier_name,
                ] : null,
                'days'  => $days,
            ];
        });

        return [
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'summary'   => $summary,
            'employees' => $grid->values()->all(),
        ];
    }

    /**
     * Return detailed attendance entry for a single employee-day cell.
     */
    public function cell(int $userId, string $date): ?array
    {
        $status = AttendanceStatus::with(['user', 'shiftAssignment.shift', 'fingerprintRecord'])
            ->where('user_id', $userId)
            ->where('attendance_date', $date)
            ->first();

        if (! $status) {
            return null;
        }

        $shift = $status->shiftAssignment?->shift;

        return [
            'user_id'         => $status->user_id,
            'name'            => $status->user?->name,
            'attendance_date' => $date,
            'status'          => $status->status,
            'clock_in'        => $status->clock_in,
            'clock_out'       => $status->clock_out,
            'late_minutes'    => $status->late_minutes,
            'early_minutes'   => $status->early_minutes,
            'shift'           => $shift ? [
                'id'         => $shift->id,
                'name'       => $shift->name,
                'start_time' => $shift->start_time,
                'end_time'   => $shift->end_time,
            ] : null,
            'fingerprint'     => $status->fingerprintRecord ? [
                'id'        => $status->fingerprintRecord->id,
                'clock_in'  => $status->fingerprintRecord->clock_in,
                'clock_out' => $status->fingerprintRecord->clock_out,
            ] : null,
        ];
    }

    private function buildDateRange(string $from, string $to): Collection
    {
        $dates   = collect();
        $current = Carbon::parse($from);
        $end     = Carbon::parse($to);

        while ($current->lte($end)) {
            $dates->push($current->toDateString());
            $current->addDay();
        }

        return $dates;
    }
}
