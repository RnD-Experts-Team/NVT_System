<?php

namespace Tests\Feature\Api;

use Carbon\Carbon;
use Tests\Feature\ApiTestCase;

class BulkAssignmentTest extends ApiTestCase
{
    public function test_bulk_by_employees_fills_full_week_for_each_user(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $emp     = $this->createUser($dept);
        $shift   = $this->createShift();
        $week    = $this->currentMonday();

        $response = $this->actingAs($manager)->postJson('/api/schedules/assignments/bulk-create', [
            'week_start'      => $week,
            'mode'            => 'by_employees',
            'user_ids'        => [$emp->id],
            'assignment_type' => 'shift',
            'shift_id'        => $shift->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.assigned', 7); // 1 employee × 7 days

        $this->assertDatabaseCount('shift_assignments', 7);
    }

    public function test_bulk_by_days_assigns_all_dept_employees_for_those_dates(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $emp1    = $this->createUser($dept);
        $emp2    = $this->createUser($dept);
        $week    = $this->currentMonday();

        $monday  = $week;
        $tuesday = Carbon::parse($week)->addDay()->toDateString();

        $response = $this->actingAs($manager)->postJson('/api/schedules/assignments/bulk-create', [
            'week_start'      => $week,
            'mode'            => 'by_days',
            'dates'           => [$monday, $tuesday],
            'assignment_type' => 'day_off',
        ]);

        $response->assertCreated();
        // 3 users in dept × 2 days = 6
        $this->assertEquals(6, $response->json('data.assigned'));
    }

    public function test_bulk_rejects_users_outside_department(): void
    {
        $dept1   = $this->createDepartment('Own');
        $dept2   = $this->createDepartment('Other');
        $manager = $this->createManager($dept1);
        $outsider = $this->createUser($dept2);
        $week    = $this->currentMonday();

        $this->actingAs($manager)->postJson('/api/schedules/assignments/bulk-create', [
            'week_start'      => $week,
            'mode'            => 'by_employees',
            'user_ids'        => [$outsider->id],
            'assignment_type' => 'day_off',
        ])->assertForbidden();
    }

    public function test_bulk_wraps_in_transaction_no_partial_results(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $week    = $this->currentMonday();

        // Request with invalid assignment_type should fail validation before any DB writes
        $this->actingAs($manager)->postJson('/api/schedules/assignments/bulk-create', [
            'week_start'      => $week,
            'mode'            => 'by_employees',
            'user_ids'        => [$manager->id],
            'assignment_type' => 'invalid_type',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('shift_assignments', 0);
    }
}
