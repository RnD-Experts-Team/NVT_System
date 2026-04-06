<?php

namespace App\Jobs;

use App\Mail\TuesdayReminderMail;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WeeklySchedule;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendTuesdayReminderAndAutoCopyJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $managerRoles  = ['L2', 'L2PM', 'L3', 'L4', 'L5', 'L6'];
        $currentWeek   = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $prevWeek      = Carbon::now()->subWeek()->startOfWeek(Carbon::MONDAY)->toDateString();

        // Collect unique department IDs managed by manager-level users
        $deptIds = User::whereHas('roles', fn ($q) => $q->whereIn('name', $managerRoles))
            ->whereNotNull('department_id')
            ->distinct()
            ->pluck('department_id');

        foreach ($deptIds as $deptId) {
            $scheduleExists = WeeklySchedule::where('department_id', $deptId)
                ->where('week_start', $currentWeek)
                ->exists();

            if ($scheduleExists) {
                continue; // Already has a schedule — skip
            }

            // Send reminder to all managers of this department
            $managers = User::with('department')
                ->whereHas('roles', fn ($q) => $q->whereIn('name', $managerRoles))
                ->where('department_id', $deptId)
                ->get();

            foreach ($managers as $manager) {
                Mail::to($manager->email)->send(new TuesdayReminderMail($manager, $currentWeek));
            }

            // Auto-copy from previous week if it exists
            $prevSchedule = WeeklySchedule::with('assignments')
                ->where('department_id', $deptId)
                ->where('week_start', $prevWeek)
                ->first();

            if (! $prevSchedule || $prevSchedule->assignments->isEmpty()) {
                continue;
            }

            // Use first manager of the dept as the "system" user for history
            $systemUser = $managers->first();
            if (! $systemUser) {
                continue;
            }

            DB::transaction(function () use ($prevSchedule, $deptId, $currentWeek, $systemUser) {
                $newSchedule = WeeklySchedule::firstOrCreate(
                    ['department_id' => $deptId, 'week_start' => $currentWeek],
                    ['status' => 'draft']
                );

                foreach ($prevSchedule->assignments as $old) {
                    $dayOffset = Carbon::parse($old->assignment_date)->diffInDays($prevSchedule->week_start);
                    $newDate   = Carbon::parse($currentWeek)->addDays($dayOffset)->toDateString();

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
                        'changed_by'    => $systemUser->id,
                        'previous_type' => null,
                        'new_type'      => $newAssignment->assignment_type,
                        'new_shift_id'  => $newAssignment->shift_id,
                        'comment'       => 'Auto-copied by system (Tuesday reminder)',
                        'changed_at'    => now(),
                    ]);
                }
            });
        }
    }
}
