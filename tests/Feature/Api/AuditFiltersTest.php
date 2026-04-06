<?php

namespace Tests\Feature\Api;

use Tests\Feature\ApiTestCase;

/**
 * M3 — Audit grid filter tests (status filter, search filter).
 * SRS requirements: M3-AUD-03, M3-AUD-04
 */
class AuditFiltersTest extends ApiTestCase
{
    // ─── Status filter ────────────────────────────────────────────────────────

    public function test_status_filter_returns_only_matching_employees(): void
    {
        $compliance = $this->createCompliance();
        $dept       = $this->createDepartment('Filter Dept');
        $emp1       = $this->createUser($dept);
        $emp2       = $this->createUser($dept);
        $week       = '2026-06-01';
        $date       = '2026-06-02';

        $this->createAttendanceStatus($emp1, $date, 'on_time');
        $this->createAttendanceStatus($emp2, $date, 'late', '09:00:00', '17:00:00', 55);

        // Filter for 'late' only
        $response = $this->actingAs($compliance)
            ->getJson("/api/audit?week_start={$week}&department_id={$dept->id}&status=late")
            ->assertOk();

        $employees = $response->json('data.employees');
        $ids       = collect($employees)->pluck('user_id')->all();

        $this->assertContains($emp2->id, $ids);
        $this->assertNotContains($emp1->id, $ids);
    }

    public function test_status_filter_validates_enum(): void
    {
        $compliance = $this->createCompliance();

        $this->actingAs($compliance)
            ->getJson('/api/audit?week_start=2026-06-01&status=invalid_status')
            ->assertUnprocessable();
    }

    public function test_all_valid_status_values_accepted(): void
    {
        $compliance = $this->createCompliance();
        $statuses   = ['on_time', 'late', 'left_early_std', 'left_early_earl', 'combined', 'absent', 'off'];

        foreach ($statuses as $status) {
            $this->actingAs($compliance)
                ->getJson("/api/audit?week_start=2026-06-01&status={$status}")
                ->assertOk("Status '{$status}' should be accepted");
        }
    }

    // ─── Search filter ────────────────────────────────────────────────────────

    public function test_search_filter_by_name(): void
    {
        $compliance = $this->createCompliance();
        $dept       = $this->createDepartment('Search Dept');
        $emp1       = \App\Models\User::factory()->create([
            'department_id' => $dept->id,
            'user_level_id' => $this->createLevel('L1', 1)->id,
            'name'          => 'Alice Johnson',
            'is_admin'      => false,
        ]);
        $emp2       = \App\Models\User::factory()->create([
            'department_id' => $dept->id,
            'user_level_id' => $this->createLevel('L1', 1)->id,
            'name'          => 'Bob Smith',
            'is_admin'      => false,
        ]);
        $week = '2026-06-01';

        $response = $this->actingAs($compliance)
            ->getJson("/api/audit?week_start={$week}&department_id={$dept->id}&search=Alice")
            ->assertOk();

        $employees = $response->json('data.employees');
        $ids       = collect($employees)->pluck('user_id')->all();

        $this->assertContains($emp1->id, $ids);
        $this->assertNotContains($emp2->id, $ids);
    }

    // ─── Audit cell endpoint ──────────────────────────────────────────────────

    public function test_audit_cell_returns_employee_day_detail(): void
    {
        $compliance = $this->createCompliance();
        $dept       = $this->createDepartment('Cell Dept');
        $emp        = $this->createUser($dept);
        $date       = '2026-06-02';

        $this->createAttendanceStatus($emp, $date, 'late', '08:30:00', '17:00:00', 25);

        $response = $this->actingAs($compliance)
            ->getJson("/api/audit/cell?user_id={$emp->id}&date={$date}")
            ->assertOk();

        $data = $response->json('data');
        $this->assertNotNull($data);
        $this->assertSame('late', $data['status']);
        $this->assertSame(25, $data['late_minutes']);
    }

    public function test_audit_cell_returns_null_when_no_record(): void
    {
        $compliance = $this->createCompliance();
        $dept       = $this->createDepartment('Empty Cell Dept');
        $emp        = $this->createUser($dept);
        $date       = '2026-06-02';

        $this->actingAs($compliance)
            ->getJson("/api/audit/cell?user_id={$emp->id}&date={$date}")
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_audit_cell_requires_user_id_and_date(): void
    {
        $compliance = $this->createCompliance();

        $this->actingAs($compliance)
            ->getJson('/api/audit/cell')
            ->assertUnprocessable();
    }

    public function test_audit_cell_validates_user_exists(): void
    {
        $compliance = $this->createCompliance();

        $this->actingAs($compliance)
            ->getJson('/api/audit/cell?user_id=99999&date=2026-06-02')
            ->assertUnprocessable();
    }

    public function test_non_compliance_cannot_access_audit_cell(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $emp     = $this->createUser($dept);

        $this->actingAs($manager)
            ->getJson("/api/audit/cell?user_id={$emp->id}&date=2026-06-02")
            ->assertForbidden();
    }
}
