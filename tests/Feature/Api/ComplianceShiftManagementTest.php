<?php

namespace Tests\Feature\Api;

use Tests\Feature\ApiTestCase;

/**
 * M3 — Compliance shift catalog management (store / update / destroy).
 * SRS requirements: M3-SHFT-03, M3-SHFT-04, M3-SHFT-05
 */
class ComplianceShiftManagementTest extends ApiTestCase
{
    // ─── POST /api/shifts/create ─────────────────────────────────────────────

    public function test_compliance_can_create_shift(): void
    {
        $compliance = $this->createCompliance();

        $response = $this->actingAs($compliance)->postJson('/api/shifts/create', [
            'name'       => 'Night Shift',
            'start_time' => '22:00',
            'end_time'   => '06:00',
            'is_active'  => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Night Shift')
            ->assertJsonPath('data.start_time', '22:00')
            ->assertJsonPath('data.end_time', '06:00')
            ->assertJsonPath('data.is_overnight', true);

        $this->assertDatabaseHas('shifts', ['name' => 'Night Shift', 'is_overnight' => true]);
    }

    public function test_create_shift_auto_detects_non_overnight(): void
    {
        $compliance = $this->createCompliance();

        $response = $this->actingAs($compliance)->postJson('/api/shifts/create', [
            'name'       => 'Day Shift',
            'start_time' => '08:00',
            'end_time'   => '17:00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_overnight', false);
    }

    public function test_create_shift_requires_unique_name(): void
    {
        $compliance = $this->createCompliance();
        $this->createShift('Morning');

        $this->actingAs($compliance)->postJson('/api/shifts/create', [
            'name'       => 'Morning',
            'start_time' => '06:00',
            'end_time'   => '14:00',
        ])->assertUnprocessable();
    }

    public function test_create_shift_validates_time_format(): void
    {
        $compliance = $this->createCompliance();

        $this->actingAs($compliance)->postJson('/api/shifts/create', [
            'name'       => 'Bad Shift',
            'start_time' => 'not-a-time',
            'end_time'   => '17:00',
        ])->assertUnprocessable();
    }

    public function test_non_compliance_cannot_create_shift(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);

        $this->actingAs($manager)->postJson('/api/shifts/create', [
            'name'       => 'Intruder Shift',
            'start_time' => '08:00',
            'end_time'   => '17:00',
        ])->assertForbidden();
    }

    public function test_unauthenticated_cannot_create_shift(): void
    {
        $this->postJson('/api/shifts/create', [
            'name'       => 'Ghost',
            'start_time' => '08:00',
            'end_time'   => '17:00',
        ])->assertUnauthorized();
    }

    // ─── PUT /api/shifts/{shift}/update ──────────────────────────────────────

    public function test_compliance_can_update_shift(): void
    {
        $compliance = $this->createCompliance();
        $shift      = $this->createShift('Old Name', '08:00', '16:00');

        $response = $this->actingAs($compliance)->putJson("/api/shifts/{$shift->id}/update", [
            'name'      => 'New Name',
            'end_time'  => '17:00',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.end_time', '17:00');

        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'name' => 'New Name']);
    }

    public function test_update_shift_ignores_duplicate_own_name(): void
    {
        $compliance = $this->createCompliance();
        $shift      = $this->createShift('Same');

        // Updating with the same name should not trigger unique violation
        $this->actingAs($compliance)->putJson("/api/shifts/{$shift->id}/update", [
            'name' => 'Same',
        ])->assertOk();
    }

    public function test_update_shift_recalculates_overnight_flag(): void
    {
        $compliance = $this->createCompliance();
        $shift      = $this->createShift('Morning', '08:00', '17:00');

        $response = $this->actingAs($compliance)->putJson("/api/shifts/{$shift->id}/update", [
            'start_time' => '22:00',
            'end_time'   => '06:00',
        ]);

        $response->assertOk()->assertJsonPath('data.is_overnight', true);
    }

    public function test_non_compliance_cannot_update_shift(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $shift   = $this->createShift('Protected');

        $this->actingAs($manager)->putJson("/api/shifts/{$shift->id}/update", [
            'name' => 'Hacked',
        ])->assertForbidden();
    }

    // ─── DELETE /api/shifts/{shift}/delete ───────────────────────────────────

    public function test_compliance_can_delete_unused_shift(): void
    {
        $compliance = $this->createCompliance();
        $shift      = $this->createShift('Orphan Shift');

        $this->actingAs($compliance)
            ->deleteJson("/api/shifts/{$shift->id}/delete")
            ->assertOk()
            ->assertJsonPath('message', 'Shift deleted.');

        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
    }

    public function test_compliance_cannot_delete_shift_in_use(): void
    {
        $compliance = $this->createCompliance();
        $dept       = $this->createDepartment('Ops');
        $emp        = $this->createUser($dept);
        $shift      = $this->createShift('Busy Shift');

        $this->createAssignmentForUser($emp, $shift, $this->currentMonday());

        $this->actingAs($compliance)
            ->deleteJson("/api/shifts/{$shift->id}/delete")
            ->assertUnprocessable();
    }

    public function test_non_compliance_cannot_delete_shift(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $shift   = $this->createShift('Guarded');

        $this->actingAs($manager)
            ->deleteJson("/api/shifts/{$shift->id}/delete")
            ->assertForbidden();
    }

    // ─── GET /api/shifts (shared read endpoint) ───────────────────────────────

    public function test_compliance_can_list_shifts(): void
    {
        $compliance = $this->createCompliance();
        $this->createShift('A');
        $this->createShift('B', '14:00', '22:00');

        $this->actingAs($compliance)
            ->getJson('/api/shifts')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'start_time', 'end_time', 'is_overnight', 'is_active']]]);
    }

    public function test_manager_can_list_shifts(): void
    {
        $dept    = $this->createDepartment('Sales');
        $manager = $this->createManager($dept);
        $this->createShift('Morning');

        $this->actingAs($manager)
            ->getJson('/api/shifts')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
