<?php

namespace Tests\Feature\Api;

use App\Models\WeeklySchedule;
use Tests\Feature\ApiTestCase;

class AssignmentTest extends ApiTestCase
{
    private function createPayload(array $overrides = []): array
    {
        return array_merge([
            'week_start'      => $this->currentMonday(),
            'assignment_date' => $this->currentMonday(),
            'assignment_type' => 'day_off',
        ], $overrides);
    }

    public function test_create_shift_assignment(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $shift   = $this->createShift();

        $payload = $this->createPayload([
            'user_id'         => $manager->id,
            'assignment_type' => 'shift',
            'shift_id'        => $shift->id,
        ]);

        $response = $this->actingAs($manager)->postJson('/api/schedules/assignments/create', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.assignment_type', 'shift')
            ->assertJsonPath('data.shift.id', $shift->id);
    }

    public function test_create_day_off_assignment(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);

        $payload = $this->createPayload([
            'user_id'         => $manager->id,
            'assignment_type' => 'day_off',
        ]);

        $this->actingAs($manager)->postJson('/api/schedules/assignments/create', $payload)
            ->assertCreated()
            ->assertJsonPath('data.assignment_type', 'day_off');
    }

    public function test_create_sick_day_assignment(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);

        $payload = $this->createPayload([
            'user_id'         => $manager->id,
            'assignment_type' => 'sick_day',
        ]);

        $this->actingAs($manager)->postJson('/api/schedules/assignments/create', $payload)
            ->assertCreated()
            ->assertJsonPath('data.assignment_type', 'sick_day');
    }

    public function test_create_leave_request_assignment(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);

        $payload = $this->createPayload([
            'user_id'         => $manager->id,
            'assignment_type' => 'leave_request',
        ]);

        $this->actingAs($manager)->postJson('/api/schedules/assignments/create', $payload)
            ->assertCreated()
            ->assertJsonPath('data.assignment_type', 'leave_request');
    }

    public function test_create_cover_assignment(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $emp     = $this->createUser($dept);
        $shift   = $this->createShift();
        $cover   = $this->createShift('Evening', '17:00', '22:00');

        $payload = $this->createPayload([
            'user_id'           => $manager->id,
            'assignment_type'   => 'shift',
            'shift_id'          => $shift->id,
            'is_cover'          => true,
            'cover_for_user_id' => $emp->id,
            'cover_shift_id'    => $cover->id,
        ]);

        $response = $this->actingAs($manager)->postJson('/api/schedules/assignments/create', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.is_cover', true)
            ->assertJsonPath('data.cover_for_user.id', $emp->id);
    }

    public function test_update_assignment_type(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $shift   = $this->createShift();

        $created = $this->actingAs($manager)->postJson('/api/schedules/assignments/create', $this->createPayload([
            'user_id'         => $manager->id,
            'assignment_type' => 'shift',
            'shift_id'        => $shift->id,
        ]));

        $assignmentId = $created->json('data.id');

        $response = $this->actingAs($manager)->putJson(
            "/api/schedules/assignments/{$assignmentId}/update",
            ['assignment_type' => 'day_off', 'shift_id' => null]
        );

        $response->assertOk()->assertJsonPath('data.assignment_type', 'day_off');
    }

    public function test_delete_clears_assignment(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);

        $created = $this->actingAs($manager)->postJson('/api/schedules/assignments/create', $this->createPayload([
            'user_id'         => $manager->id,
            'assignment_type' => 'day_off',
        ]));

        $assignmentId = $created->json('data.id');

        $this->actingAs($manager)->deleteJson("/api/schedules/assignments/{$assignmentId}/delete")
            ->assertOk()
            ->assertJsonPath('data.assignment_id', $assignmentId);

        $this->assertSoftDeleted('shift_assignments', ['id' => $assignmentId]);
    }

    public function test_create_fails_without_shift_id_when_type_is_shift(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);

        $payload = $this->createPayload([
            'user_id'         => $manager->id,
            'assignment_type' => 'shift',
            // shift_id intentionally omitted
        ]);

        $this->actingAs($manager)->postJson('/api/schedules/assignments/create', $payload)
            ->assertUnprocessable();
    }

    public function test_cannot_assign_user_from_other_department(): void
    {
        $dept1   = $this->createDepartment('Dept A');
        $dept2   = $this->createDepartment('Dept B');
        $manager = $this->createManager($dept1);
        $outsider = $this->createUser($dept2);

        $payload = $this->createPayload([
            'user_id'         => $outsider->id,
            'assignment_type' => 'day_off',
        ]);

        $this->actingAs($manager)->postJson('/api/schedules/assignments/create', $payload)
            ->assertForbidden();
    }

    public function test_auto_creates_weekly_schedule_on_first_assignment(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $week    = $this->currentMonday();

        $this->assertDatabaseMissing('weekly_schedules', ['department_id' => $dept->id, 'week_start' => $week]);

        $this->actingAs($manager)->postJson('/api/schedules/assignments/create', $this->createPayload([
            'user_id'         => $manager->id,
            'assignment_type' => 'day_off',
        ]))->assertCreated();

        $this->assertDatabaseHas('weekly_schedules', ['department_id' => $dept->id, 'week_start' => $week]);
    }
}
