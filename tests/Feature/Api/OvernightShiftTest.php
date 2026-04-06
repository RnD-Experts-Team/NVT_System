<?php

namespace Tests\Feature\Api;

use App\Services\AttendanceComputeService;
use Tests\Feature\ApiTestCase;

/**
 * M3 — Overnight shift attendance computation.
 * SRS requirement: M3-SHFT-02 (overnight flag), M3-STAT rules for overnight shifts.
 */
class OvernightShiftTest extends ApiTestCase
{
    private AttendanceComputeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AttendanceComputeService::class);
    }

    // ─── computeEntry direct tests ────────────────────────────────────────────

    public function test_overnight_shift_is_detected(): void
    {
        $shift = $this->createShift('Night', '22:00', '06:00');
        $this->assertTrue($shift->is_overnight);
    }

    public function test_overnight_on_time(): void
    {
        $shift = $this->createShift('Night', '22:00', '06:00');

        $fp            = new \App\Models\FingerprintRecord();
        $fp->clock_in  = '22:00:00';
        $fp->clock_out = '06:00:00';

        $result = $this->service->computeEntry($shift, $fp, '2026-06-02');

        $this->assertSame('on_time', $result['status']);
        $this->assertNull($result['late_minutes']);
    }

    public function test_overnight_late_arrival(): void
    {
        $shift = $this->createShift('Night', '22:00', '06:00');

        $fp            = new \App\Models\FingerprintRecord();
        $fp->clock_in  = '22:10:00'; // 5 min past grace
        $fp->clock_out = '06:00:00';

        $result = $this->service->computeEntry($shift, $fp, '2026-06-02');

        $this->assertSame('late', $result['status']);
        $this->assertSame(5, $result['late_minutes']);
    }

    public function test_overnight_left_early_standard(): void
    {
        // Night 22:00–06:00 = 480 min. 60% = 288 min = 02:48.  Left at 05:00 (420 min > 288).
        $shift = $this->createShift('Night', '22:00', '06:00');

        $fp            = new \App\Models\FingerprintRecord();
        $fp->clock_in  = '22:00:00';
        $fp->clock_out = '05:00:00';

        $result = $this->service->computeEntry($shift, $fp, '2026-06-02');

        $this->assertSame('left_early_std', $result['status']);
        $this->assertSame(60, $result['early_minutes']);
    }

    public function test_overnight_early_exit_before_threshold(): void
    {
        // Night 22:00–06:00 = 480 min. 60% = 288 min = 02:48. Left at 02:00 (240 min < 288).
        $shift = $this->createShift('Night', '22:00', '06:00');

        $fp            = new \App\Models\FingerprintRecord();
        $fp->clock_in  = '22:00:00';
        $fp->clock_out = '02:00:00';

        $result = $this->service->computeEntry($shift, $fp, '2026-06-02');

        $this->assertSame('left_early_earl', $result['status']);
    }

    public function test_overnight_combined_status(): void
    {
        $shift = $this->createShift('Night', '22:00', '06:00');

        $fp            = new \App\Models\FingerprintRecord();
        $fp->clock_in  = '22:30:00'; // 25 min past grace
        $fp->clock_out = '05:00:00'; // 60 min early

        $result = $this->service->computeEntry($shift, $fp, '2026-06-02');

        $this->assertSame('combined', $result['status']);
        $this->assertSame(25, $result['late_minutes']);
        $this->assertSame(60, $result['early_minutes']);
    }

    // ─── Full recompute pipeline for overnight ────────────────────────────────

    public function test_recompute_handles_overnight_shift_absent(): void
    {
        $dept  = $this->createDepartment('Night Dept');
        $emp   = $this->createUser($dept);
        $shift = $this->createShift('Night', '22:00', '06:00');
        $date  = $this->currentMonday();

        $this->createAssignmentForUser($emp, $shift, $date);
        // No fingerprint record → absent
        $this->service->recompute(null, $date);

        $this->assertDatabaseHas('attendance_statuses', [
            'user_id'         => $emp->id,
            'attendance_date' => $date,
            'status'          => 'absent',
        ]);
    }

    public function test_recompute_handles_overnight_shift_on_time(): void
    {
        $dept  = $this->createDepartment('Night Dept B');
        $emp   = $this->createUser($dept);
        $shift = $this->createShift('Night B', '22:00', '06:00');
        $date  = $this->currentMonday();

        $this->createAssignmentForUser($emp, $shift, $date);
        $this->createFingerprintRecord($emp, $date, '22:00:00', '06:00:00');

        $this->service->recompute(null, $date);

        $this->assertDatabaseHas('attendance_statuses', [
            'user_id'         => $emp->id,
            'attendance_date' => $date,
            'status'          => 'on_time',
        ]);
    }
}
