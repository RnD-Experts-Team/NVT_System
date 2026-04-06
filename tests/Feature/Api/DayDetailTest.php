<?php

namespace Tests\Feature\Api;

use App\Models\ShiftAssignment;
use App\Models\WeeklySchedule;
use Carbon\Carbon;
use Tests\Feature\ApiTestCase;

class DayDetailTest extends ApiTestCase
{
    public function test_day_view_returns_all_employees_for_date(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $emp     = $this->createUser($dept);
        $week    = $this->currentMonday();

        $response = $this->actingAs($manager)->getJson(
            "/api/schedules/day?week_start={$week}&date={$week}"
        );

        $response->assertOk()
            ->assertJsonStructure(['data' => ['date', 'week_start', 'summary', 'employees']]);

        // 2 users in dept
        $this->assertCount(2, $response->json('data.employees'));
    }

    public function test_day_view_summary_counts_are_correct(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $emp     = $this->createUser($dept);
        $shift   = $this->createShift();
        $week    = $this->currentMonday();

        $schedule = WeeklySchedule::create([
            'department_id' => $dept->id,
            'week_start'    => $week,
            'status'        => 'draft',
        ]);

        // Assign manager a shift, emp a day_off
        ShiftAssignment::create([
            'weekly_schedule_id' => $schedule->id,
            'user_id'            => $manager->id,
            'assignment_date'    => $week,
            'assignment_type'    => 'shift',
            'shift_id'           => $shift->id,
        ]);

        ShiftAssignment::create([
            'weekly_schedule_id' => $schedule->id,
            'user_id'            => $emp->id,
            'assignment_date'    => $week,
            'assignment_type'    => 'day_off',
        ]);

        $response = $this->actingAs($manager)->getJson(
            "/api/schedules/day?week_start={$week}&date={$week}"
        );

        $summary = $response->json('data.summary');

        $this->assertEquals(1, $summary['shift']);
        $this->assertEquals(1, $summary['day_off']);
        $this->assertEquals(0, $summary['unassigned']);
    }

    public function test_day_view_shows_unassigned_when_no_schedule(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $week    = $this->currentMonday();

        $response = $this->actingAs($manager)->getJson(
            "/api/schedules/day?week_start={$week}&date={$week}"
        );

        $response->assertOk();
        $summary = $response->json('data.summary');

        $this->assertEquals(1, $summary['unassigned']); // 1 employee (manager), no schedule
    }

    public function test_day_view_requires_both_params(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $week    = $this->currentMonday();

        $this->actingAs($manager)->getJson("/api/schedules/day?week_start={$week}")
            ->assertUnprocessable();

        $this->actingAs($manager)->getJson("/api/schedules/day?date={$week}")
            ->assertUnprocessable();
    }
}
