<?php

namespace Tests\Feature\Api;

use Tests\Feature\ApiTestCase;

class AssignmentHistoryTest extends ApiTestCase
{
    public function test_history_created_when_assignment_is_created(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);

        $response = $this->actingAs($manager)->postJson('/api/schedules/assignments/create', [
            'week_start'      => $this->currentMonday(),
            'user_id'         => $manager->id,
            'assignment_date' => $this->currentMonday(),
            'assignment_type' => 'day_off',
        ]);

        $response->assertCreated();
        $assignmentId = $response->json('data.id');

        $this->assertDatabaseHas('shift_assignment_history', [
            'shift_assignment_id' => $assignmentId,
            'changed_by'          => $manager->id,
            'previous_type'       => null,
            'new_type'            => 'day_off',
        ]);
    }

    public function test_history_records_previous_and_new_values_on_update(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $shift   = $this->createShift();

        $created = $this->actingAs($manager)->postJson('/api/schedules/assignments/create', [
            'week_start'      => $this->currentMonday(),
            'user_id'         => $manager->id,
            'assignment_date' => $this->currentMonday(),
            'assignment_type' => 'shift',
            'shift_id'        => $shift->id,
        ]);

        $assignmentId = $created->json('data.id');

        $this->actingAs($manager)->putJson("/api/schedules/assignments/{$assignmentId}/update", [
            'assignment_type' => 'day_off',
            'shift_id'        => null,
        ]);

        $history = \App\Models\ShiftAssignmentHistory::where('shift_assignment_id', $assignmentId)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertEquals('shift', $history->previous_type);
        $this->assertEquals('day_off', $history->new_type);
        $this->assertEquals($shift->id, $history->previous_shift_id);
        $this->assertNull($history->new_shift_id);
    }

    public function test_history_created_on_delete(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);

        $created = $this->actingAs($manager)->postJson('/api/schedules/assignments/create', [
            'week_start'      => $this->currentMonday(),
            'user_id'         => $manager->id,
            'assignment_date' => $this->currentMonday(),
            'assignment_type' => 'day_off',
        ]);

        $assignmentId = $created->json('data.id');

        $this->actingAs($manager)->deleteJson("/api/schedules/assignments/{$assignmentId}/delete");

        $this->assertDatabaseHas('shift_assignment_history', [
            'shift_assignment_id' => $assignmentId,
            'previous_type'       => 'day_off',
            'new_type'            => null,
        ]);
    }

    public function test_history_endpoint_returns_all_entries(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $shift   = $this->createShift();

        $created = $this->actingAs($manager)->postJson('/api/schedules/assignments/create', [
            'week_start'      => $this->currentMonday(),
            'user_id'         => $manager->id,
            'assignment_date' => $this->currentMonday(),
            'assignment_type' => 'shift',
            'shift_id'        => $shift->id,
        ]);

        $assignmentId = $created->json('data.id');

        // Update once → 2 history rows total
        $this->actingAs($manager)->putJson("/api/schedules/assignments/{$assignmentId}/update", [
            'assignment_type' => 'day_off',
        ]);

        $response = $this->actingAs($manager)->getJson("/api/schedules/assignments/{$assignmentId}/history");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $response->assertJsonStructure(['data' => [['id', 'changed_by_name', 'previous_type', 'new_type', 'changed_at']]]);
    }
}
