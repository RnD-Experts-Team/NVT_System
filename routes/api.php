<?php

use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\FingerprintImportController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserLevelController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',     [AuthController::class, 'me']);

    Route::middleware('permission:manage-departments')->group(function () {
        Route::get('/departments',                        [DepartmentController::class, 'index']);
        Route::post('/departments/create',                [DepartmentController::class, 'store']);
        Route::get('/departments/tree',                   [DepartmentController::class, 'tree']);
        Route::get('/departments/{department}',           [DepartmentController::class, 'show']);
        Route::put('/departments/{department}/update',    [DepartmentController::class, 'update']);
        Route::delete('/departments/{department}/delete', [DepartmentController::class, 'destroy']);
    });

    Route::get('/users',              [UserController::class, 'index'])->middleware('permission:view-users');
    Route::get('/users/{user}',       [UserController::class, 'show'])->middleware('permission:view-users');

    Route::middleware('permission:manage-users')->group(function () {
        Route::post('/users/create',           [UserController::class, 'store']);
        Route::put('/users/{user}/update',     [UserController::class, 'update']);
        Route::delete('/users/{user}/delete',  [UserController::class, 'destroy']);
    });

    Route::post('/users/{user}/roles', [UserController::class, 'assignRoles'])
        ->middleware('permission:manage-roles');

    Route::get('/levels', [UserLevelController::class, 'index'])
        ->middleware('permission:view-levels');

    Route::middleware('permission:manage-roles')->group(function () {
        Route::get('/roles',                         [RoleController::class, 'index']);
        Route::post('/roles/create',                 [RoleController::class, 'store']);
        Route::get('/roles/{role}',                  [RoleController::class, 'show']);
        Route::put('/roles/{role}/update',           [RoleController::class, 'update']);
        Route::delete('/roles/{role}/delete',        [RoleController::class, 'destroy']);
        Route::post('/roles/{role}/permissions',     [RoleController::class, 'assignPermissions']);
    });

    Route::middleware('permission:manage-permissions')->group(function () {
        Route::get('/permissions',                          [PermissionController::class, 'index']);
        Route::post('/permissions/create',                  [PermissionController::class, 'store']);
        Route::get('/permissions/{permission}',             [PermissionController::class, 'show']);
        Route::put('/permissions/{permission}/update',      [PermissionController::class, 'update']);
        Route::delete('/permissions/{permission}/delete',   [PermissionController::class, 'destroy']);
    });

    Route::get('/shifts', [ShiftController::class, 'index']); // no extra permission  all authenticated users

    Route::middleware('permission:manage-shifts')->group(function () {
        Route::post('/shifts/create',           [ShiftController::class, 'store']);
        Route::put('/shifts/{shift}/update',    [ShiftController::class, 'update']);
        Route::delete('/shifts/{shift}/delete', [ShiftController::class, 'destroy']);
    });

    Route::middleware(['permission:view-schedule', 'dept.scope'])->group(function () {
        Route::get('/schedules',        [ScheduleController::class, 'index']);
        Route::get('/schedules/day',    [ScheduleController::class, 'day']);
        Route::get('/schedules/export', [ScheduleController::class, 'export']);
    });

    Route::middleware(['permission:manage-schedule', 'dept.scope'])->group(function () {
        Route::post('/schedules/save',           [ScheduleController::class, 'save']);
        Route::post('/schedules/copy-last-week', [ScheduleController::class, 'copyLastWeek']);
        Route::get('/schedules/template',        [ScheduleController::class, 'downloadTemplate']);
    });

    Route::post('/schedules/import-excel', [ScheduleController::class, 'importExcel'])
        ->middleware(['permission:import-schedule-excel', 'dept.scope']);

    Route::middleware('permission:manage-fingerprint')->group(function () {
        Route::get('/fingerprint/imports',         [FingerprintImportController::class, 'index']);
        Route::post('/fingerprint/imports/upload', [FingerprintImportController::class, 'upload']);
    });

    Route::middleware('permission:view-audit')->group(function () {
        Route::get('/audit',      [AuditController::class, 'index']);
        Route::get('/audit/cell', [AuditController::class, 'cell']);
    });
});


