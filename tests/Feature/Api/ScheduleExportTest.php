<?php

namespace Tests\Feature\Api;

use App\Models\WeeklySchedule;
use Tests\Feature\ApiTestCase;

class ScheduleExportTest extends ApiTestCase
{
    public function test_export_returns_csv_content_type(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $week    = $this->currentMonday();

        $response = $this->actingAs($manager)->get("/api/schedules/export?week_start={$week}");

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_export_has_correct_filename(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $week    = $this->currentMonday();

        $response = $this->actingAs($manager)->get("/api/schedules/export?week_start={$week}");

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString("schedule_{$week}.csv", $disposition);
    }

    public function test_export_contains_employee_column_and_day_columns(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $week    = $this->currentMonday();

        $response = $this->actingAs($manager)->get("/api/schedules/export?week_start={$week}");

        $content = $response->streamedContent();
        // First line should be a header row containing "Employee"
        $this->assertStringContainsString('Employee', $content);
    }

    public function test_export_shows_shift_names_in_cells(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $shift   = $this->createShift('Morning');
        $week    = $this->currentMonday();

        $schedule = WeeklySchedule::create([
            'department_id' => $dept->id,
            'week_start'    => $week,
            'status'        => 'draft',
        ]);

        $this->fillWeekForUser($schedule, $manager, $shift);

        $content = $this->actingAs($manager)
            ->get("/api/schedules/export?week_start={$week}")
            ->streamedContent();

        $this->assertStringContainsString('Morning', $content);
    }

    public function test_export_requires_week_start(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);

        $this->actingAs($manager)->getJson('/api/schedules/export')->assertUnprocessable();
    }
}
