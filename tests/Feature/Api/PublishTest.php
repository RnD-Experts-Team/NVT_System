<?php

namespace Tests\Feature\Api;

use App\Models\WeeklySchedule;
use Tests\Feature\ApiTestCase;

class PublishTest extends ApiTestCase
{
    public function test_publish_succeeds_when_all_cells_are_filled(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $shift   = $this->createShift();
        $week    = $this->currentMonday();

        $schedule = WeeklySchedule::create([
            'department_id' => $dept->id,
            'week_start'    => $week,
            'status'        => 'draft',
        ]);

        // Only 1 employee in dept (manager), fill all 7 days
        $this->fillWeekForUser($schedule, $manager, $shift);

        $response = $this->actingAs($manager)->postJson('/api/schedules/publish', [
            'week_start' => $week,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.week_start', $week)
            ->assertJsonStructure(['data' => ['message', 'week_start', 'published_at', 'published_by_name']]);

        $this->assertDatabaseHas('weekly_schedules', [
            'department_id' => $dept->id,
            'week_start'    => $week,
            'status'        => 'published',
        ]);
    }

    public function test_publish_returns_422_when_cells_are_missing(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $emp     = $this->createUser($dept); // another employee → cells will be missing
        $shift   = $this->createShift();
        $week    = $this->currentMonday();

        $schedule = WeeklySchedule::create([
            'department_id' => $dept->id,
            'week_start'    => $week,
            'status'        => 'draft',
        ]);

        // Only fill manager's row, leave emp's row empty
        $this->fillWeekForUser($schedule, $manager, $shift);

        $response = $this->actingAs($manager)->postJson('/api/schedules/publish', [
            'week_start' => $week,
        ]);

        $response->assertUnprocessable()
            ->assertJsonStructure(['message', 'missing_count', 'missing_cells']);
    }

    public function test_publish_returns_422_when_no_schedule_exists(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $week    = $this->currentMonday();

        $this->actingAs($manager)->postJson('/api/schedules/publish', [
            'week_start' => $week,
        ])->assertUnprocessable();
    }

    public function test_manager_can_edit_assignment_after_publish(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $shift   = $this->createShift();
        $week    = $this->currentMonday();

        $schedule = WeeklySchedule::create([
            'department_id' => $dept->id,
            'week_start'    => $week,
            'status'        => 'draft',
        ]);

        $this->fillWeekForUser($schedule, $manager, $shift);

        $this->actingAs($manager)->postJson('/api/schedules/publish', ['week_start' => $week])->assertOk();

        // Now try to update one assignment — should still be allowed
        $assignment = $schedule->assignments()->first();

        $this->actingAs($manager)->putJson("/api/schedules/assignments/{$assignment->id}/update", [
            'assignment_type' => 'day_off',
        ])->assertOk();
    }

    public function test_non_manager_cannot_publish(): void
    {
        $dept  = $this->createDepartment();
        $user  = $this->createUser($dept);
        $week  = $this->currentMonday();

        $this->actingAs($user)->postJson('/api/schedules/publish', [
            'week_start' => $week,
        ])->assertForbidden();
    }
}
