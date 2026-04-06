<?php

use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\FingerprintImportController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ShiftAssignmentController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserLevelController;
use App\Http\Controllers\Api\UserLevelTierController;
use Illuminate\Support\Facades\Route;

// ─── Auth ────────────────────────────────────────────────────────────────────
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',     [AuthController::class, 'me']);

    // ─── Admin-only routes ────────────────────────────────────────────────
    Route::middleware('is_admin')->group(function () {

        // Departments
        Route::get('/departments',                          [DepartmentController::class, 'index']);
        Route::post('/departments/create',                  [DepartmentController::class, 'store']);
        Route::get('/departments/tree',                     [DepartmentController::class, 'tree']);
        Route::get('/departments/{department}',             [DepartmentController::class, 'show']);
        Route::get('/departments/{department}/users',       [DepartmentController::class, 'users']);
        Route::put('/departments/{department}/update',      [DepartmentController::class, 'update']);
        Route::delete('/departments/{department}/delete',   [DepartmentController::class, 'destroy']);

        // Levels
        Route::get('/levels',                               [UserLevelController::class, 'index']);
        Route::post('/levels/create',                       [UserLevelController::class, 'store']);
        Route::get('/levels/{userLevel}',                   [UserLevelController::class, 'show']);
        Route::put('/levels/{userLevel}/update',            [UserLevelController::class, 'update']);
        Route::delete('/levels/{userLevel}/delete',         [UserLevelController::class, 'destroy']);

        // Tiers (nested under levels)
        Route::get('/levels/{userLevel}/tiers',                         [UserLevelTierController::class, 'index']);
        Route::post('/levels/{userLevel}/tiers/create',                 [UserLevelTierController::class, 'store']);
        Route::get('/levels/{userLevel}/tiers/{tier}',                  [UserLevelTierController::class, 'show']);
        Route::put('/levels/{userLevel}/tiers/{tier}/update',           [UserLevelTierController::class, 'update']);
        Route::delete('/levels/{userLevel}/tiers/{tier}/delete',        [UserLevelTierController::class, 'destroy']);

        // Users
        Route::get('/users',                                [UserController::class, 'index']);
        Route::post('/users/create',                        [UserController::class, 'store']);
        Route::get('/users/{user}',                         [UserController::class, 'show']);
        Route::post('/users/{user}/roles',                  [UserController::class, 'assignRoles']);
        Route::put('/users/{user}/update',                  [UserController::class, 'update']);
        Route::delete('/users/{user}/delete',               [UserController::class, 'destroy']);

        // Roles
        Route::get('/roles',                                [RoleController::class, 'index']);
        Route::post('/roles/create',                        [RoleController::class, 'store']);
        Route::get('/roles/{role}',                         [RoleController::class, 'show']);
        Route::put('/roles/{role}/update',                  [RoleController::class, 'update']);
        Route::delete('/roles/{role}/delete',               [RoleController::class, 'destroy']);
        Route::post('/roles/{role}/permissions',            [RoleController::class, 'assignPermissions']);

        // Permissions
        Route::get('/permissions',                          [PermissionController::class, 'index']);
        Route::post('/permissions/create',                  [PermissionController::class, 'store']);
        Route::get('/permissions/{permission}',             [PermissionController::class, 'show']);
        Route::put('/permissions/{permission}/update',      [PermissionController::class, 'update']);
        Route::delete('/permissions/{permission}/delete',   [PermissionController::class, 'destroy']);
    });

    // ─── Shared authenticated routes (no role guard) ─────────────────────
    // GET /api/shifts is used by both Managers (picker) and Compliance (audit view)
    Route::get('/shifts', [ShiftController::class, 'index']);

    // ─── Manager-only routes (M2 — Schedule System) ───────────────────────
    Route::middleware(['is_manager'])->group(function () {

        // Weekly schedule grid + actions
        Route::get('/schedules',                [ScheduleController::class, 'index']);
        Route::get('/schedules/day',            [ScheduleController::class, 'day']);
        Route::get('/schedules/export',         [ScheduleController::class, 'export']);
        Route::get('/schedules/publish-status', [ScheduleController::class, 'publishStatus']);
        Route::post('/schedules/copy-last-week', [ScheduleController::class, 'copyLastWeek']);
        Route::post('/schedules/publish',        [ScheduleController::class, 'publish']);

        // Shift assignments
        Route::post('/schedules/assignments/create',               [ShiftAssignmentController::class, 'store']);
        Route::post('/schedules/assignments/bulk-create',          [ShiftAssignmentController::class, 'bulk']);
        Route::get('/schedules/assignments/{assignment}/history',   [ShiftAssignmentController::class, 'history']);
        Route::put('/schedules/assignments/{assignment}/update',    [ShiftAssignmentController::class, 'update']);
        Route::delete('/schedules/assignments/{assignment}/delete', [ShiftAssignmentController::class, 'destroy']);
    });

    // ─── Compliance-only routes (M3 — Audit & Attendance) ─────────────────────
    Route::middleware('compliance')->group(function () {

        // Shift catalog management (Compliance maintains the master shift list)
        Route::post('/shifts/create',                [ShiftController::class, 'store']);
        Route::put('/shifts/{shift}/update',         [ShiftController::class, 'update']);
        Route::delete('/shifts/{shift}/delete',      [ShiftController::class, 'destroy']);

        // Fingerprint import
        Route::get('/fingerprint/imports',           [FingerprintImportController::class, 'index']);
        Route::post('/fingerprint/imports/upload',   [FingerprintImportController::class, 'upload']);

        // Audit grid + cell detail
        Route::get('/audit',                         [AuditController::class, 'index']);
        Route::get('/audit/cell',                    [AuditController::class, 'cell']);
    });
});
