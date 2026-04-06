<?php

namespace Tests\Feature\Api;

use App\Models\ShiftAssignment;
use App\Models\WeeklySchedule;
use Tests\Feature\ApiTestCase;

class ScheduleGridTest extends ApiTestCase
{
    public function test_manager_can_load_empty_weekly_grid(): void
    {
        $dept    = $this->createDepartment('Alpha');
        $manager = $this->createManager($dept);
        $week    = $this->currentMonday();

        $response = $this->actingAs($manager)->getJson("/api/schedules?week_start={$week}");

        $response->assertOk()
            ->assertJsonPath('data.week_start', $week)
            ->assertJsonPath('data.status', 'none')
            ->assertJsonStructure(['data' => ['week_start', 'week_end', 'department_id', 'status', 'employees']]);
    }

    public function test_grid_shows_correct_employee_rows(): void
    {
        $dept    = $this->createDepartment('Beta');
        $manager = $this->createManager($dept);
        $emp1    = $this->createUser($dept);
        $emp2    = $this->createUser($dept);
        $week    = $this->currentMonday();

        $response = $this->actingAs($manager)->getJson("/api/schedules?week_start={$week}");

        $response->assertOk();
        // 3 users in dept (manager + 2 employees)
        $this->assertCount(3, $response->json('data.employees'));
    }

    public function test_grid_shows_assignments_when_schedule_exists(): void
    {
        $dept    = $this->createDepartment('Delta');
        $manager = $this->createManager($dept);
        $shift   = $this->createShift();
        $week    = $this->currentMonday();

        $schedule = WeeklySchedule::create([
            'department_id' => $dept->id,
            'week_start'    => $week,
            'status'        => 'draft',
        ]);

        ShiftAssignment::create([
            'weekly_schedule_id' => $schedule->id,
            'user_id'            => $manager->id,
            'assignment_date'    => $week,
            'assignment_type'    => 'shift',
            'shift_id'           => $shift->id,
        ]);

        $response = $this->actingAs($manager)->getJson("/api/schedules?week_start={$week}");

        $response->assertOk();
        $employees = $response->json('data.employees');
        $managerRow = collect($employees)->firstWhere('user_id', $manager->id);

        $this->assertNotNull($managerRow);
        $this->assertEquals('shift', $managerRow['days'][0]['assignment_type']);
        $this->assertEquals($shift->name, $managerRow['days'][0]['shift']['name']);
    }

    public function test_unauthenticated_cannot_access_grid(): void
    {
        $week = $this->currentMonday();
        $this->getJson("/api/schedules?week_start={$week}")->assertUnauthorized();
    }

    public function test_non_manager_l1_cannot_access_grid(): void
    {
        $dept  = $this->createDepartment('Gamma');
        $user  = $this->createUser($dept); // L1 — no manager role
        $week  = $this->currentMonday();

        $this->actingAs($user)->getJson("/api/schedules?week_start={$week}")->assertForbidden();
    }

    public function test_week_start_is_required(): void
    {
        $dept    = $this->createDepartment('Zeta');
        $manager = $this->createManager($dept);

        $this->actingAs($manager)->getJson('/api/schedules')->assertUnprocessable();
    }
}
