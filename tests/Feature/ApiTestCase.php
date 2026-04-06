<?php

namespace Tests\Feature;

use App\Models\AttendanceStatus;
use App\Models\Department;
use App\Models\FingerprintImport;
use App\Models\FingerprintRecord;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\UserLevel;
use App\Models\WeeklySchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear Spatie permission cache before every test
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // -------------------------------------------------------------------------
    // Helpers — M1
    // -------------------------------------------------------------------------

    protected function createLevel(string $code = 'L1', int $rank = 1): UserLevel
    {
        return UserLevel::firstOrCreate(
            ['code' => $code],
            ['name' => 'Level ' . $code, 'hierarchy_rank' => $rank]
        );
    }

    protected function createDepartment(string $name = 'Test Dept', ?Department $parent = null): Department
    {
        $dept = Department::create([
            'name'      => $name,
            'is_active' => true,
            'parent_id' => $parent?->id,
        ]);

        $dept->path = ($parent ? $parent->path : '/') . $dept->id . '/';
        $dept->save();

        return $dept;
    }

    protected function createAdmin(?Department $dept = null, ?UserLevel $level = null): User
    {
        $dept  = $dept  ?? $this->createDepartment('HQ');
        $level = $level ?? $this->createLevel('L6', 6);

        return User::factory()->create([
            'department_id' => $dept->id,
            'user_level_id' => $level->id,
            'is_admin'      => true,
        ]);
    }

    protected function createUser(?Department $dept = null, ?UserLevel $level = null): User
    {
        $dept  = $dept  ?? $this->createDepartment('Staff Dept');
        $level = $level ?? $this->createLevel('L1', 1);

        return User::factory()->create([
            'department_id' => $dept->id,
            'user_level_id' => $level->id,
            'is_admin'      => false,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers — M2
    // -------------------------------------------------------------------------

    /**
     * Create a manager user with a Spatie role matching their level code.
     * Defaults to L2 (minimum manager level).
     */
    protected function createManager(?Department $dept = null, string $levelCode = 'L2'): User
    {
        $dept  = $dept ?? $this->createDepartment('Manager Dept');
        $rank  = (int) filter_var($levelCode, FILTER_SANITIZE_NUMBER_INT);
        $level = $this->createLevel($levelCode, $rank > 0 ? $rank : 2);

        $manager = User::factory()->create([
            'department_id' => $dept->id,
            'user_level_id' => $level->id,
            'is_admin'      => false,
        ]);

        // Ensure the Spatie role exists, then assign (mirrors syncLevelRole in UserController)
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $levelCode, 'guard_name' => 'web']);
        $manager->syncRoles([$levelCode]);

        return $manager;
    }

    /**
     * Create an active Shift record.
     */
    protected function createShift(string $name = 'Morning', string $start = '08:00', string $end = '17:00'): Shift
    {
        return Shift::create([
            'name'        => $name,
            'start_time'  => $start,
            'end_time'    => $end,
            'is_overnight' => $end < $start,
            'is_active'   => true,
        ]);
    }

    /**
     * Return the Monday date string for the current (or specified) week.
     */
    protected function currentMonday(?string $date = null): string
    {
        return Carbon::parse($date ?? now())->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    /**
     * Create a full week of shift assignments for a user in a schedule.
     */
    protected function fillWeekForUser(WeeklySchedule $schedule, User $user, Shift $shift): void
    {
        for ($i = 0; $i < 7; $i++) {
            $date = Carbon::parse($schedule->week_start)->addDays($i)->toDateString();
            ShiftAssignment::create([
                'weekly_schedule_id' => $schedule->id,
                'user_id'            => $user->id,
                'assignment_date'    => $date,
                'assignment_type'    => 'shift',
                'shift_id'           => $shift->id,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers — M3
    // -------------------------------------------------------------------------

    /**
     * Create a Compliance user with the Spatie 'Compliance' role.
     */
    protected function createCompliance(?Department $dept = null): User
    {
        $dept  = $dept ?? $this->createDepartment('Compliance Dept');
        $level = $this->createLevel('L3', 3);

        $user = User::factory()->create([
            'department_id' => $dept->id,
            'user_level_id' => $level->id,
            'is_admin'      => false,
        ]);

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Compliance', 'guard_name' => 'web']);
        $user->syncRoles(['Compliance']);

        return $user;
    }

    /**
     * Create a FingerprintRecord for (user, date) with optional clock_in/clock_out.
     */
    protected function createFingerprintRecord(
        User $user,
        string $date,
        ?string $clockIn = '08:00:00',
        ?string $clockOut = '17:00:00',
        ?FingerprintImport $import = null
    ): FingerprintRecord {
        if (! $import) {
            $importer = $user; // self-import for test simplicity
            $import   = FingerprintImport::create([
                'imported_by'   => $importer->id,
                'week_start'    => Carbon::parse($date)->startOfWeek(Carbon::MONDAY)->toDateString(),
                'filename'      => 'test_import.csv',
                'status'        => 'processed',
                'rows_imported' => 1,
                'rows_failed'   => 0,
                'imported_at'   => now(),
            ]);
        }

        return FingerprintRecord::updateOrCreate(
            ['user_id' => $user->id, 'record_date' => $date],
            ['import_id' => $import->id, 'clock_in' => $clockIn, 'clock_out' => $clockOut]
        );
    }

    /**
     * Create a WeeklySchedule + one ShiftAssignment for the given user.
     */
    protected function createAssignmentForUser(
        User $user,
        Shift $shift,
        string $date,
        string $type = 'shift'
    ): ShiftAssignment {
        $dept     = Department::find($user->department_id) ?? $this->createDepartment();
        $schedule = WeeklySchedule::firstOrCreate(
            [
                'department_id' => $dept->id,
                'week_start'    => Carbon::parse($date)->startOfWeek(Carbon::MONDAY)->toDateString(),
            ],
            ['status' => 'published']
        );

        return ShiftAssignment::create([
            'weekly_schedule_id' => $schedule->id,
            'user_id'            => $user->id,
            'assignment_date'    => $date,
            'assignment_type'    => $type,
            'shift_id'           => $type === 'shift' ? $shift->id : null,
        ]);
    }

    /**
     * Create an AttendanceStatus row directly (for tests that skip compute service).
     */
    protected function createAttendanceStatus(
        User $user,
        string $date,
        string $status = 'on_time',
        ?string $clockIn = '08:00:00',
        ?string $clockOut = '17:00:00',
        int $lateMinutes = 0,
        int $earlyMinutes = 0
    ): AttendanceStatus {
        return AttendanceStatus::updateOrCreate(
            ['user_id' => $user->id, 'attendance_date' => $date],
            [
                'status'       => $status,
                'clock_in'     => $clockIn,
                'clock_out'    => $clockOut,
                'late_minutes' => $lateMinutes ?: null,
                'early_minutes' => $earlyMinutes ?: null,
            ]
        );
    }
}
