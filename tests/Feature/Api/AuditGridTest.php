<?php

namespace Tests\Feature\Api;

use App\Models\WeeklySchedule;
use Tests\Feature\ApiTestCase;

/**
 * M3 — Audit grid endpoint.
 * SRS requirements: M3-AUD-01 through M3-AUD-04
 */
class AuditGridTest extends ApiTestCase
{
    // ─── Authentication / Authorization ──────────────────────────────────────

    public function test_unauthenticated_cannot_access_audit(): void
    {
        $this->getJson('/api/audit?week_start=2026-06-01')
            ->assertUnauthorized();
    }

    public function test_non_compliance_cannot_access_audit(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);

        $this->actingAs($manager)
            ->getJson('/api/audit?week_start=2026-06-01')
            ->assertForbidden();
    }

    public function test_compliance_can_access_audit(): void
    {
        $compliance = $this->createCompliance();

        $this->actingAs($compliance)
            ->getJson('/api/audit?week_start=2026-06-01')
            ->assertOk()
            ->assertJsonStructure(['data' => ['week_start', 'week_end', 'summary', 'employees']]);
    }

    // ─── Validation ──────────────────────────────────────────────────────────

    public function test_audit_requires_week_start(): void
    {
        $compliance = $this->createCompliance();

        $this->actingAs($compliance)
            ->getJson('/api/audit')
            ->assertUnprocessable();
    }

    public function test_audit_validates_week_start_format(): void
    {
        $compliance = $this->createCompliance();

        $this->actingAs($compliance)
            ->getJson('/api/audit?week_start=not-a-date')
            ->assertUnprocessable();
    }

    // ─── Grid content ─────────────────────────────────────────────────────────

    public function test_audit_grid_contains_employee_rows_with_days(): void
    {
        $compliance = $this->createCompliance();
        $dept       = $this->createDepartment('Audit Dept');
        $emp        = $this->createUser($dept);
        $week       = '2026-06-01';

        $this->actingAs($compliance)
            ->getJson("/api/audit?week_start={$week}&department_id={$dept->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.employees')
            ->assertJsonPath('data.employees.0.user_id', $emp->id)
            ->assertJsonPath('data.week_start', $week)
            ->assertJsonPath('data.week_end', '2026-06-07');
    }

    public function test_audit_grid_shows_attendance_status_in_cell(): void
    {
        $compliance = $this->createCompliance();
        $dept       = $this->createDepartment('Status Dept');
        $emp        = $this->createUser($dept);
        $date       = '2026-06-02'; // Tuesday
        $week       = '2026-06-01';

        $this->createAttendanceStatus($emp, $date, 'late', '08:30:00', '17:00:00', 25);

        $response = $this->actingAs($compliance)
            ->getJson("/api/audit?week_start={$week}&department_id={$dept->id}")
            ->assertOk();

        $employees = $response->json('data.employees');
        $row       = collect($employees)->firstWhere('user_id', $emp->id);

        $this->assertNotNull($row);
        $dayCell = collect($row['days'])->firstWhere('date', $date);
        $this->assertSame('late', $dayCell['status']);
        $this->assertSame(25, $dayCell['late_minutes']);
    }

    public function test_audit_summary_counts_status_types(): void
    {
        $compliance = $this->createCompliance();
        $dept       = $this->createDepartment('Summary Dept');
        $emp1       = $this->createUser($dept);
        $emp2       = $this->createUser($dept);
        $week       = '2026-06-01';

        $this->createAttendanceStatus($emp1, '2026-06-02', 'on_time');
        $this->createAttendanceStatus($emp2, '2026-06-02', 'late', '09:00:00', '17:00:00', 55);

        $response = $this->actingAs($compliance)
            ->getJson("/api/audit?week_start={$week}&department_id={$dept->id}")
            ->assertOk();

        $summary = $response->json('data.summary');
        $this->assertSame(1, $summary['on_time']);
        $this->assertSame(1, $summary['late']);
    }

    public function test_audit_empty_week_returns_null_status_cells(): void
    {
        $compliance = $this->createCompliance();
        $dept       = $this->createDepartment('Empty Dept');
        $emp        = $this->createUser($dept);
        $week       = '2026-06-01';

        $response = $this->actingAs($compliance)
            ->getJson("/api/audit?week_start={$week}&department_id={$dept->id}")
            ->assertOk();

        $employees = $response->json('data.employees');
        $row       = collect($employees)->firstWhere('user_id', $emp->id);
        $this->assertCount(7, $row['days']);

        foreach ($row['days'] as $day) {
            $this->assertNull($day['status']);
        }
    }

    // ─── Dept filter ──────────────────────────────────────────────────────────

    public function test_department_filter_excludes_other_departments(): void
    {
        $compliance = $this->createCompliance();
        $dept1      = $this->createDepartment('Dept One');
        $dept2      = $this->createDepartment('Dept Two');
        $emp1       = $this->createUser($dept1);
        $emp2       = $this->createUser($dept2);
        $week       = '2026-06-01';

        $response = $this->actingAs($compliance)
            ->getJson("/api/audit?week_start={$week}&department_id={$dept1->id}")
            ->assertOk();

        $employees = $response->json('data.employees');
        $ids       = collect($employees)->pluck('user_id')->all();

        $this->assertContains($emp1->id, $ids);
        $this->assertNotContains($emp2->id, $ids);
    }
}
