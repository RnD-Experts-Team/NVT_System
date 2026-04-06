<?php

namespace Tests\Feature\Api;

use App\Services\AttendanceComputeService;
use App\Models\Shift;
use App\Models\FingerprintRecord;
use Tests\Feature\ApiTestCase;
use Carbon\Carbon;

/**
 * M3 — AttendanceComputeService unit tests.
 * Verifies all SRS §3 M3-STAT rules using the service directly.
 */
class AttendanceStatusComputeTest extends ApiTestCase
{
    private AttendanceComputeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AttendanceComputeService::class);
    }

    // ─── Helper: build a mock FingerprintRecord ──────────────────────────────

    private function mockFp(string $clockIn, string $clockOut): FingerprintRecord
    {
        $fp            = new FingerprintRecord();
        $fp->clock_in  = $clockIn;
        $fp->clock_out = $clockOut;
        return $fp;
    }

    private function makeShift(string $start, string $end): Shift
    {
        return $this->createShift('Test Shift', $start, $end);
    }

    // ─── on_time ─────────────────────────────────────────────────────────────

    public function test_on_time_exact(): void
    {
        $shift  = $this->makeShift('08:00', '17:00');
        $fp     = $this->mockFp('08:00:00', '17:00:00');
        $result = $this->service->computeEntry($shift, $fp, '2026-06-02');

        $this->assertSame('on_time', $result['status']);
        $this->assertNull($result['late_minutes']);
        $this->assertNull($result['early_minutes']);
    }

    public function test_on_time_within_grace(): void
    {
        $shift  = $this->makeShift('08:00', '17:00');
        $fp     = $this->mockFp('08:05:00', '17:00:00'); // exactly at grace limit
        $result = $this->service->computeEntry($shift, $fp, '2026-06-02');

        $this->assertSame('on_time', $result['status']);
    }

    // ─── late ────────────────────────────────────────────────────────────────

    public function test_late_by_1_minute_after_grace(): void
    {
        $shift  = $this->makeShift('08:00', '17:00');
        $fp     = $this->mockFp('08:06:00', '17:00:00'); // 1 min past grace
        $result = $this->service->computeEntry($shift, $fp, '2026-06-02');

        $this->assertSame('late', $result['status']);
        $this->assertSame(1, $result['late_minutes']);
        $this->assertNull($result['early_minutes']);
    }

    public function test_late_by_30_minutes(): void
    {
        $shift  = $this->makeShift('08:00', '17:00');
        $fp     = $this->mockFp('08:35:00', '17:00:00');
        $result = $this->service->computeEntry($shift, $fp, '2026-06-02');

        $this->assertSame('late', $result['status']);
        $this->assertSame(30, $result['late_minutes']);
    }

    // ─── left_early_std (standard — after 60% threshold) ────────────────────

    public function test_left_early_standard_after_threshold(): void
    {
        // Shift: 08:00–17:00 = 540 min. 60% = 324 min = 13:24. Left at 16:00 (after 324 min).
        $shift  = $this->makeShift('08:00', '17:00');
        $fp     = $this->mockFp('08:00:00', '16:00:00');
        $result = $this->service->computeEntry($shift, $fp, '2026-06-02');

        $this->assertSame('left_early_std', $result['status']);
        $this->assertNull($result['late_minutes']);
        $this->assertSame(60, $result['early_minutes']); // 60 min before end
    }

    // ─── left_early_earl (early exit — before 60% threshold) ─────────────────

    public function test_early_exit_before_threshold(): void
    {
        // Shift 08:00–17:00, 60% = 13:24. Left at 12:00 (only 240 of 540 min = 44%).
        $shift  = $this->makeShift('08:00', '17:00');
        $fp     = $this->mockFp('08:00:00', '12:00:00');
        $result = $this->service->computeEntry($shift, $fp, '2026-06-02');

        $this->assertSame('left_early_earl', $result['status']);
    }

    // ─── combined (late + left early) ────────────────────────────────────────

    public function test_combined_late_and_left_early(): void
    {
        $shift  = $this->makeShift('08:00', '17:00');
        $fp     = $this->mockFp('09:00:00', '16:00:00'); // 55 min late, left 60 min early
        $result = $this->service->computeEntry($shift, $fp, '2026-06-02');

        $this->assertSame('combined', $result['status']);
        $this->assertSame(55, $result['late_minutes']);
        $this->assertSame(60, $result['early_minutes']);
    }

    // ─── absent ──────────────────────────────────────────────────────────────

    public function test_absent_created_when_no_fingerprint_record(): void
    {
        $dept       = $this->createDepartment('Absent Dept');
        $emp        = $this->createUser($dept);
        $shift      = $this->makeShift('08:00', '17:00');
        $date       = '2026-06-02';

        $this->createAssignmentForUser($emp, $shift, $date);

        // No fingerprint record created → service should mark absent
        $this->service->recompute(null, Carbon::parse($date)->startOfWeek(Carbon::MONDAY)->toDateString());

        $this->assertDatabaseHas('attendance_statuses', [
            'user_id'         => $emp->id,
            'attendance_date' => $date,
            'status'          => 'absent',
        ]);
    }

    // ─── off ─────────────────────────────────────────────────────────────────

    public function test_off_day_assignment_yields_off_status(): void
    {
        $dept  = $this->createDepartment('Off Dept');
        $emp   = $this->createUser($dept);
        $shift = $this->makeShift('08:00', '17:00');
        $date  = $this->currentMonday();

        $this->createAssignmentForUser($emp, $shift, $date, 'day_off');

        $this->service->recompute(null, $date);

        $this->assertDatabaseHas('attendance_statuses', [
            'user_id'         => $emp->id,
            'attendance_date' => $date,
            'status'          => 'off',
        ]);
    }

    // ─── recompute upserts ────────────────────────────────────────────────────

    public function test_recompute_upserts_existing_status(): void
    {
        $dept  = $this->createDepartment('Upsert Dept');
        $emp   = $this->createUser($dept);
        $shift = $this->makeShift('08:00', '17:00');
        $date  = $this->currentMonday();
        $week  = $date;

        $import = $this->createFingerprintRecord($emp, $date, '08:00:00', '17:00:00')->import_id
                       ? null : null; // just create the record

        $this->createAssignmentForUser($emp, $shift, $date);
        $this->createFingerprintRecord($emp, $date, '08:00:00', '17:00:00');

        $this->service->recompute(null, $week);
        $this->assertDatabaseHas('attendance_statuses', ['user_id' => $emp->id, 'status' => 'on_time']);

        // Now re-import with late clock-in and recompute
        \App\Models\FingerprintRecord::where('user_id', $emp->id)->update(['clock_in' => '09:00:00']);
        $this->service->recompute(null, $week);
        $this->assertDatabaseHas('attendance_statuses', ['user_id' => $emp->id, 'status' => 'late']);
    }
}
