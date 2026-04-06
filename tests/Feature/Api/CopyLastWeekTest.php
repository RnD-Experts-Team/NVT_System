<?php

namespace Tests\Feature\Api;

use App\Models\WeeklySchedule;
use Carbon\Carbon;
use Tests\Feature\ApiTestCase;

class CopyLastWeekTest extends ApiTestCase
{
    public function test_copy_succeeds_when_previous_week_exists(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $shift   = $this->createShift();
        $week    = $this->currentMonday();
        $prevWeek = Carbon::parse($week)->subWeek()->toDateString();

        // Create previous week schedule with 1 assignment
        $prevSchedule = WeeklySchedule::create([
            'department_id' => $dept->id,
            'week_start'    => $prevWeek,
            'status'        => 'published',
        ]);

        $this->fillWeekForUser($prevSchedule, $manager, $shift);

        $response = $this->actingAs($manager)->postJson('/api/schedules/copy-last-week', [
            'week_start' => $week,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.assignments_copied', 7)
            ->assertJsonPath('data.week_start', $week);

        $this->assertDatabaseHas('weekly_schedules', [
            'department_id' => $dept->id,
            'week_start'    => $week,
            'status'        => 'draft',
        ]);
    }

    public function test_copy_returns_422_when_no_previous_week(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $week    = $this->currentMonday();

        $this->actingAs($manager)->postJson('/api/schedules/copy-last-week', [
            'week_start' => $week,
        ])->assertUnprocessable();
    }

    public function test_copy_creates_correct_assignment_dates_for_new_week(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $shift   = $this->createShift();
        $week    = $this->currentMonday();
        $prevWeek = Carbon::parse($week)->subWeek()->toDateString();

        $prevSchedule = WeeklySchedule::create([
            'department_id' => $dept->id,
            'week_start'    => $prevWeek,
            'status'        => 'draft',
        ]);

        $this->fillWeekForUser($prevSchedule, $manager, $shift);

        $this->actingAs($manager)->postJson('/api/schedules/copy-last-week', [
            'week_start' => $week,
        ])->assertOk();

        // Verify the new assignments are in the new week range
        $newSchedule = WeeklySchedule::where('department_id', $dept->id)
            ->where('week_start', $week)
            ->with('assignments')
            ->first();

        $this->assertNotNull($newSchedule);
        $this->assertCount(7, $newSchedule->assignments);

        foreach ($newSchedule->assignments as $a) {
            $date = $a->assignment_date->toDateString();
            $this->assertGreaterThanOrEqual($week, $date);
            $this->assertLessThanOrEqual(Carbon::parse($week)->addDays(6)->toDateString(), $date);
        }
    }

    public function test_copy_is_idempotent_does_not_duplicate(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $shift   = $this->createShift();
        $week    = $this->currentMonday();
        $prevWeek = Carbon::parse($week)->subWeek()->toDateString();

        $prevSchedule = WeeklySchedule::create([
            'department_id' => $dept->id,
            'week_start'    => $prevWeek,
            'status'        => 'draft',
        ]);

        $this->fillWeekForUser($prevSchedule, $manager, $shift);

        // Copy twice
        $this->actingAs($manager)->postJson('/api/schedules/copy-last-week', ['week_start' => $week]);
        $this->actingAs($manager)->postJson('/api/schedules/copy-last-week', ['week_start' => $week]);

        // Should not duplicate — still 7 rows
        $this->assertDatabaseCount('shift_assignments', 7 /* prev */ + 7 /* new, no dup */);
    }
}
