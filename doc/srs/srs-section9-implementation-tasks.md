---
**SOFTWARE REQUIREMENTS SPECIFICATION**
**Employee Schedule Management System**

| | |
|---|---|
| **Organization** | NVT — New Vision Team |
| **Document type** | Software Requirements Specification (SRS) |
| **Current section** | Section 9 — Implementation Task List |
| **Document status** | Active — In use during development |
| **Version** | 1.0 |
| **Prepared by** | NVT Development Team |
| **Date** | 31 March 2026 |

---

**SRS Progress — Section 9 of 9**

| | |
|---|---|
| ✅ Section 1: Introduction | ✅ Section 2: Overall Description |
| ✅ Section 3: Functional Requirements | ✅ Section 4: Non-Functional Requirements |
| ✅ Section 5: System Users & Roles | ✅ Section 6: Use Cases |
| ✅ Section 7: Technical Flow (Backend) | ✅ Section 8: Database Schema |
| ✅ Section 9: Implementation Tasks *(this document)* | |

---

## About This Document

This is the master implementation task list for the Employee Schedule Management System. Every task maps directly to SRS requirements. Use this file to track progress during development.

**Task status legend:**
- `[x]` — Completed
- `[ ]` — Not started
- `[~]` — In progress

**Module status:**
- ✅ M1 — Core Admin & Hierarchy — **Fully implemented and tested**
- ✅ M2 — Manager Schedule System — **Fully implemented and tested**
- ⬜ M3 — Audit & Compliance System — **Not started**

---

## M1 — Core Admin & Hierarchy ✅

---

### 1. Database — M1 Migrations

- [x] 1.1 Migration: `create_users_table`
  - Columns: `id`, `name`, `nickname`, `email`, `password`, `department_id`, `user_level_id`, `user_level_tier_id`, `is_admin`, timestamps
  - _Requirements: M1-USR-01, M1-USR-07_

- [x] 1.2 Migration: `create_departments_table`
  - Columns: `id`, `name`, `description`, `parent_id` (self-ref FK), `path`, `is_active`, timestamps
  - _Requirements: M1-DEPT-01, M1-DEPT-03_

- [x] 1.3 Migration: `create_user_levels_table`
  - Columns: `id`, `code`, `name`, `hierarchy_rank`, `description`, timestamps
  - _Requirements: M1-LVL-01_

- [x] 1.4 Migration: `create_user_level_tiers_table`
  - Columns: `id`, `user_level_id`, `tier_name`, `tier_order`, `description`, timestamps
  - _Requirements: M1-TIER-01_

- [x] 1.5 Migration: `create_cache_table` + `create_jobs_table`
  - Standard Laravel cache and queue infrastructure
  - _Requirements: NFR-REL-02_

- [x] 1.6 Spatie Permission migrations (via package)
  - Tables: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
  - _Requirements: M1-ROLE-01, M1-PERM-01_

---

### 2. Models — M1

- [x] 2.1 Model: `User`
  - Relationships: `belongsTo Department`, `belongsTo UserLevel`, `belongsTo UserLevelTier`
  - Traits: `HasRoles` (Spatie), `HasApiTokens` (Sanctum)
  - Fillable: `name`, `nickname`, `email`, `password`, `department_id`, `user_level_id`, `user_level_tier_id`, `is_admin`
  - _Requirements: M1-USR-01 to M1-USR-07_

- [x] 2.2 Model: `Department`
  - Relationships: `hasMany children` (self-ref), `belongsTo parent`, `hasMany users`
  - Materialized path: `path` column built on create
  - _Requirements: M1-DEPT-01 to M1-DEPT-07_

- [x] 2.3 Model: `UserLevel`
  - Relationships: `hasMany tiers`, `hasMany users`
  - _Requirements: M1-LVL-01 to M1-LVL-05_

- [x] 2.4 Model: `UserLevelTier`
  - Relationships: `belongsTo UserLevel`, `hasMany users`
  - _Requirements: M1-TIER-01 to M1-TIER-05_

---

### 3. Middleware — M1

- [x] 3.1 Middleware: `EnsureIsAdmin`
  - Checks `auth()->user()->is_admin === true`
  - Returns `403 Forbidden` if not admin
  - _Requirements: NFR-SEC-03, M1-AUTH-04_

---

### 4. Resources — M1

- [x] 4.1 Resource: `UserResource`
  - Fields: `id`, `name`, `nickname`, `email`, `is_admin`, `department` (id+name), `level` (id+code+name), `tier` (id+tier_name), `roles` (array of names)
  - _Requirements: M1-AUTH-03, M1-USR-03_

- [x] 4.2 Resource: `DepartmentResource`
  - Fields: `id`, `name`, `description`, `parent_id`, `path`, `is_active`, `children` (recursive)
  - _Requirements: M1-DEPT-03, M1-DEPT-04_

- [x] 4.3 Resource: `UserLevelResource`
  - Fields: `id`, `code`, `name`, `hierarchy_rank`, `description`, `tiers` (array)
  - _Requirements: M1-LVL-03_

---

### 5. Controllers & Routes — M1

- [x] 5.1 Auth routes + `AuthController`
  - `POST /api/auth/login` → `login()` — validate credentials, create Sanctum token, return `UserResource` + token
  - `POST /api/auth/logout` → `logout()` — delete current token
  - `GET /api/auth/me` → `me()` — return authenticated user with all relations
  - _Requirements: M1-AUTH-01, M1-AUTH-02, M1-AUTH-03_

- [x] 5.2 Department routes + `DepartmentController`
  - `GET /api/departments` → `index()` — all departments with children
  - `POST /api/departments/create` → `store()` — validate, create, set path
  - `GET /api/departments/tree` → `tree()` — root nodes with recursive children
  - `GET /api/departments/{department}` → `show()` — single dept with children
  - `GET /api/departments/{department}/users` → `users()` — users in dept + sub-depts via path
  - `PUT /api/departments/{department}/update` → `update()` — update name/parent
  - `DELETE /api/departments/{department}/delete` → `destroy()` — block if has children or users
  - _Requirements: M1-DEPT-01 to M1-DEPT-07_

- [x] 5.3 Level routes + `UserLevelController`
  - `GET /api/levels` → `index()` — all levels ordered by `hierarchy_rank` with tiers
  - `POST /api/levels/create` → `store()` — validate unique code, create
  - `GET /api/levels/{userLevel}` → `show()` — single level with tiers
  - `PUT /api/levels/{userLevel}/update` → `update()` — update code/name/rank
  - `DELETE /api/levels/{userLevel}/delete` → `destroy()` — block if has users
  - _Requirements: M1-LVL-01 to M1-LVL-05_

- [x] 5.4 Tier routes + `UserLevelTierController`
  - `GET /api/levels/{userLevel}/tiers` → `index()` — all tiers for level
  - `POST /api/levels/{userLevel}/tiers/create` → `store()` — create tier under level
  - `GET /api/levels/{userLevel}/tiers/{tier}` → `show()` — single tier (404 if wrong level)
  - `PUT /api/levels/{userLevel}/tiers/{tier}/update` → `update()` — update tier fields
  - `DELETE /api/levels/{userLevel}/tiers/{tier}/delete` → `destroy()` — block if has users
  - _Requirements: M1-TIER-01 to M1-TIER-05_

- [x] 5.5 User routes + `UserController`
  - `GET /api/users` → `index()` — all users with all relations
  - `POST /api/users/create` → `store()` — validate, hash password, create, call `syncLevelRole()`
  - `GET /api/users/{user}` → `show()` — single user with all relations
  - `PUT /api/users/{user}/update` → `update()` — validate, update, re-sync role if level changed
  - `DELETE /api/users/{user}/delete` → `destroy()` — delete user
  - Private: `syncLevelRole(User)` — `Role::firstOrCreate(['name'=>$level->code])` then `$user->syncRoles([$code])`
  - _Requirements: M1-USR-01 to M1-USR-07_

- [x] 5.6 Role routes + `RoleController`
  - `GET /api/roles` → `index()` — all Spatie roles with permissions
  - `POST /api/roles/create` → `store()` — create role with `guard_name=web`
  - `GET /api/roles/{role}` → `show()` — single role with permissions
  - `PUT /api/roles/{role}/update` → `update()` — rename role
  - `DELETE /api/roles/{role}/delete` → `destroy()` — delete role
  - `POST /api/roles/{role}/permissions` → `assignPermissions()` — `syncPermissions()`
  - _Requirements: M1-ROLE-01 to M1-ROLE-06_

- [x] 5.7 Permission routes + `PermissionController`
  - `GET /api/permissions` → `index()` — all permissions
  - `POST /api/permissions/create` → `store()` — create permission
  - `GET /api/permissions/{permission}` → `show()` — single permission
  - `PUT /api/permissions/{permission}/update` → `update()` — rename
  - `DELETE /api/permissions/{permission}/delete` → `destroy()` — delete
  - _Requirements: M1-PERM-01 to M1-PERM-05_

---

### 6. Tests — M1

- [x] 6.1 `AuthTest.php` — 10 tests
  - Login success, wrong password, missing fields, logout, me endpoint, unauthenticated access
  - _Requirements: M1-AUTH-01 to M1-AUTH-04_

- [x] 6.2 `DepartmentTest.php` — 20 tests
  - CRUD, tree structure, users endpoint, block delete if children/users exist, 403 for non-admin
  - _Requirements: M1-DEPT-01 to M1-DEPT-07_

- [x] 6.3 `UserLevelTest.php` — 14 tests
  - CRUD, block delete if users assigned, unique code enforcement
  - _Requirements: M1-LVL-01 to M1-LVL-05_

- [x] 6.4 `UserLevelTierTest.php` — 11 tests
  - CRUD nested under level, 404 on level mismatch, block delete if users assigned
  - _Requirements: M1-TIER-01 to M1-TIER-05_

- [x] 6.5 `UserTest.php` — 17 tests
  - CRUD, password hashing, role auto-sync on create, role re-sync on level change
  - _Requirements: M1-USR-01 to M1-USR-07_

- [x] 6.6 `RoleTest.php` — 14 tests
  - CRUD, sync permissions, 403 for non-admin
  - _Requirements: M1-ROLE-01 to M1-ROLE-06_

- [x] 6.7 `PermissionTest.php` — 15 tests
  - CRUD, unique name enforcement, 403 for non-admin
  - _Requirements: M1-PERM-01 to M1-PERM-05_

> **M1 Total: 101 tests, 267 assertions — all passing ✅**

---

---

## M2 — Manager Schedule System ✅

---

### 7. Database — M2 Migrations

- [x] 7.1 Migration: `create_shifts_table`
  - Columns: `id`, `name`, `start_time` (TIME), `end_time` (TIME), `is_overnight` (BOOLEAN, computed), `is_active` (BOOLEAN), timestamps
  - Index: `is_active`
  - _Requirements: M3-SHFT-06, Section 8 schema_

- [x] 7.2 Migration: `create_weekly_schedules_table`
  - Columns: `id`, `department_id` (FK), `week_start` (DATE), `status` (ENUM: `draft`, `published`), `published_at` (TIMESTAMP NULL), `published_by` (FK → users), timestamps
  - Unique: `(department_id, week_start)`
  - Indexes: `department_id`, `week_start`, `status`
  - _Requirements: M2-PUB-01, Section 8 schema_

- [x] 7.3 Migration: `create_shift_assignments_table`
  - Columns: `id`, `weekly_schedule_id` (FK), `user_id` (FK), `assignment_date` (DATE), `shift_id` (FK NULL), `assignment_type` (ENUM: `shift`, `day_off`, `sick_day`, `leave_request`), `is_cover` (BOOLEAN), `cover_for_user_id` (FK NULL → users), `cover_shift_id` (FK NULL → shifts), timestamps
  - Unique: `(weekly_schedule_id, user_id, assignment_date)`
  - Indexes: `user_id`, `assignment_date`, `shift_id`
  - _Requirements: M2-ASGN-01 to M2-ASGN-06, Section 8 schema_

- [x] 7.4 Migration: `create_shift_assignment_history_table`
  - Columns: `id`, `shift_assignment_id` (FK), `changed_by` (FK → users), `previous_type`, `previous_shift_id`, `new_type`, `new_shift_id`, `comment` (TEXT NULL), `changed_at` (TIMESTAMP)
  - No `updated_at` — append-only table
  - Index: `shift_assignment_id`
  - _Requirements: M2-ASGN-08, M2-ASGN-09, Section 8 schema_

---

### 8. Models — M2

- [x] 8.1 Model: `Shift`
  - Fillable: `name`, `start_time`, `end_time`, `is_overnight`, `is_active`
  - Accessor: auto-compute `is_overnight` = `end_time < start_time`
  - Relationships: `hasMany ShiftAssignment`, `hasMany ShiftAssignment (cover_shift)`
  - _Requirements: M3-SHFT-01, M3-SHFT-02_

- [x] 8.2 Model: `WeeklySchedule`
  - Fillable: `department_id`, `week_start`, `status`, `published_at`, `published_by`
  - Relationships: `belongsTo Department`, `hasMany ShiftAssignment`, `belongsTo User (publisher)`
  - Scope: `scopeForWeek($query, $date)`, `scopePublished()`, `scopeDraft()`
  - _Requirements: M2-PUB-01 to M2-PUB-05_

- [x] 8.3 Model: `ShiftAssignment`
  - Fillable: `weekly_schedule_id`, `user_id`, `assignment_date`, `shift_id`, `assignment_type`, `is_cover`, `cover_for_user_id`, `cover_shift_id`
  - Relationships: `belongsTo WeeklySchedule`, `belongsTo User`, `belongsTo Shift`, `belongsTo User (coverFor)`, `hasMany ShiftAssignmentHistory`
  - _Requirements: M2-ASGN-01 to M2-ASGN-10_

- [x] 8.4 Model: `ShiftAssignmentHistory`
  - Fillable: `shift_assignment_id`, `changed_by`, `previous_type`, `previous_shift_id`, `new_type`, `new_shift_id`, `comment`, `changed_at`
  - `public $timestamps = false;` (append-only, no `updated_at`)
  - Relationships: `belongsTo ShiftAssignment`, `belongsTo User (changer)`
  - _Requirements: M2-ASGN-08, M2-ASGN-09_

---

### 9. Controllers & Routes — M2

- [x] 9.1 Shift read route + `ShiftController@index`
  - `GET /api/shifts` → return all active shifts (for picker in assignment form)
  - Middleware: `auth:sanctum` + manager role check
  - Validation: none (read only)
  - Returns: `[{id, name, start_time, end_time, is_overnight, duration_hours}]`
  - _Requirements: M2-ASGN-03_

- [x] 9.2 Schedule read routes + `ScheduleController@index` + `@publishStatus`
  - `GET /api/schedules?week_start=YYYY-MM-DD` → `index()` — load full weekly grid
    - Scoped to `auth()->user()->department_id`
    - Eager-loads: all `ShiftAssignment` with `shift`, `user` (with level+tier), `coverFor`, `histories` count
    - Returns: week metadata + array of employees, each with 7 day cells
  - `GET /api/schedules/publish-status?week_start=YYYY-MM-DD` → `publishStatus()` — return current draft/published state + missing cell count
  - _Requirements: M2-GRID-01 to M2-GRID-10, M2-PUB-01_

- [x] 9.3 Day detail route + `ScheduleController@day`
  - `GET /api/schedules/day?week_start=YYYY-MM-DD&date=YYYY-MM-DD` → `day()`
    - Returns all employees for that day with assignment, shift info, cover details, summary counts
  - _Requirements: M2-FILT-02 to M2-FILT-05_

- [x] 9.4 Export route + `ScheduleController@export`
  - `GET /api/schedules/export?week_start=YYYY-MM-DD` → `export()`
    - Stream CSV file: `employee_name, mon, tue, wed, thu, fri, sat, sun` (shift names per day)
    - Response: `Content-Type: text/csv`, `Content-Disposition: attachment; filename="schedule_YYYY-MM-DD.csv"`
  - _Requirements: M2-EXP-01_

- [x] 9.5 Assignment create route + `AssignmentController@store`
  - `POST /api/schedules/assignments/create`
  - Validation: `week_start` (required, date, Monday), `user_id` (exists, in manager's dept), `assignment_date` (required, date, within week), `assignment_type` (required, in:shift,day_off,sick_day,leave_request), `shift_id` (required_if type=shift, FK), `is_cover` (boolean), `cover_for_user_id` (required_if is_cover, FK exists), `cover_shift_id` (required_if is_cover, FK exists), `comment` (nullable string max:1000)
  - Logic: find/create `WeeklySchedule` for dept+week → upsert `ShiftAssignment` → append `ShiftAssignmentHistory` row
  - Returns: full assignment with shift + history
  - _Requirements: M2-ASGN-01 to M2-ASGN-09_

- [x] 9.6 Assignment update route + `AssignmentController@update`
  - `PUT /api/schedules/assignments/{assignment}/update`
  - Same validation as create (all `sometimes|required`)
  - Logic: verify assignment belongs to manager's dept → store previous values → update → append history row
  - Returns: full updated assignment with new history entry
  - _Requirements: M2-ASGN-02, M2-ASGN-08, M2-ASGN-09, M2-ASGN-10, M2-PUB-04_

- [x] 9.7 Assignment delete route + `AssignmentController@destroy`
  - `DELETE /api/schedules/assignments/{assignment}/delete`
  - Logic: verify ownership → append history row (type: cleared) → delete assignment record
  - Returns: `{message: 'Assignment cleared.', assignment_id: N}`
  - _Requirements: M2-ASGN-05_

- [x] 9.8 Assignment history route + `AssignmentController@history`
  - `GET /api/schedules/assignments/{assignment}/history`
  - Returns: full ordered history array for that cell `[{id, changed_by_name, previous_type, new_type, comment, changed_at}]`
  - _Requirements: M2-ASGN-08, M2-ASGN-09_

- [x] 9.9 Bulk assignment route + `AssignmentController@bulk`
  - `POST /api/schedules/assignments/bulk-create`
  - Validation: `week_start`, `mode` (in:`by_employees`,`by_days`), `user_ids` (array, required_if mode=by_employees), `dates` (array, required_if mode=by_days), `assignment_type`, `shift_id`, `comment`
  - Logic: iterate targets → upsert each `ShiftAssignment` → append history per cell → wrap in `DB::transaction()`
  - Returns: `{assigned: N, cells: [...]}`
  - _Requirements: M2-BULK-01 to M2-BULK-04_

- [x] 9.10 Copy-last-week route + `ScheduleController@copyLastWeek`
  - `POST /api/schedules/copy-last-week`
  - Validation: `week_start` (required, Monday date)
  - Logic: find previous week schedule for dept → duplicate all `ShiftAssignment` rows for new week → append history (`comment: 'Copied from previous week'`) → wrap in `DB::transaction()`
  - Returns: `{message, assignments_copied: N, week_start: 'YYYY-MM-DD'}`
  - _Requirements: M2-COPY-01 to M2-COPY-03_

- [x] 9.11 Publish route + `ScheduleController@publish`
  - `POST /api/schedules/publish`
  - Validation: `week_start` (required, Monday date)
  - Logic: load schedule → count total required cells (dept employees × 7) → count filled cells → if any missing return 422 with count + list → update `status=published`, `published_at=now()`, `published_by=auth()->id()`
  - Returns: `{message, week_start, published_at, published_by_name}` or `422 {message, missing_count, missing_cells: [{user_id, name, missing_dates}]}`
  - _Requirements: M2-PUB-01 to M2-PUB-05_

---

### 10. M2 Middleware

- [x] 10.1 Add manager role check middleware or gate
  - Verify `auth()->user()` has a Spatie role in `['L2','L2PM','L3','L4','L5','L6']`
  - Verify all target users/assignments belong to `auth()->user()->department_id`
  - Return 403 if either check fails
  - _Requirements: M2-GRID-09, NFR-SEC-02, NFR-SEC-10_

---

### 11. Background Jobs — M2

- [x] 11.1 Job: `SendMondayScheduleReminder`
  - Scheduled: every Monday at 12:00 PM via `php artisan schedule:run`
  - Logic: get all Manager-role users → send reminder email to each
  - _Requirements: M2-EMAIL-01_

- [x] 11.2 Job: `SendTuesdayReminderAndAutoCopy`
  - Scheduled: every Tuesday at 12:00 PM
  - Logic: for each manager's department — check if `WeeklySchedule` exists for current week → if not: send reminder email AND dispatch `CopyLastWeekJob` for that department
  - Idempotent: skip if schedule already exists
  - _Requirements: M2-EMAIL-02, M2-EMAIL-03, M2-EMAIL-04_

- [x] 11.3 Email views
  - Monday reminder email template
  - Tuesday reminder email template
  - _Requirements: M2-EMAIL-01, M2-EMAIL-02_

---

### 12. Tests — M2

- [x] 12.1 `ScheduleGridTest.php`
  - Load grid for own department (200), load grid for other department (403), empty week returns empty cells
  - _Requirements: M2-GRID-01, M2-GRID-09, M2-GRID-10_

- [x] 12.2 `AssignmentTest.php`
  - Create assignment (shift, day_off, sick_day, leave_request), create cover, edit assignment, clear assignment, 422 on missing shift_id when type=shift, 403 on wrong dept
  - _Requirements: M2-ASGN-01 to M2-ASGN-09_

- [x] 12.3 `AssignmentHistoryTest.php`
  - History created on assign, history created on edit, history created on clear, history is read-only (no update/delete endpoint exists)
  - _Requirements: M2-ASGN-08, M2-ASGN-09_

- [x] 12.4 `BulkAssignmentTest.php`
  - Bulk by employees, bulk by days, rollback on any cell failure
  - _Requirements: M2-BULK-01 to M2-BULK-04_

- [x] 12.5 `CopyLastWeekTest.php`
  - Copy succeeds when previous week exists, returns 422 when no previous week, assignments duplicated correctly
  - _Requirements: M2-COPY-01 to M2-COPY-03_

- [x] 12.6 `PublishTest.php`
  - Publish succeeds when all cells filled, 422 with missing cells when incomplete, manager can edit after publish, non-manager cannot publish (403)
  - _Requirements: M2-PUB-01 to M2-PUB-05_

- [x] 12.7 `ScheduleExportTest.php`
  - Export returns CSV content-type, correct columns, correct filename
  - _Requirements: M2-EXP-01_

- [x] 12.8 `DayDetailTest.php`
  - Day view returns all employees for date, summary counts correct, search and filter params respected
  - _Requirements: M2-FILT-02 to M2-FILT-05_

> **M2 Total: 42 tests, 137 assertions — all passing ✅**

---

---

## M3 — Audit & Compliance System ⬜

---

### 13. Database — M3 Migrations

- [ ] 13.1 Migration: `create_fingerprint_imports_table`
  - Columns: `id`, `imported_by` (FK → users), `week_start` (DATE), `filename` (VARCHAR), `rows_imported` (INT), `rows_failed` (INT), `imported_at` (TIMESTAMP), timestamps
  - Index: `week_start`
  - _Requirements: M3-IMP-01, Section 8 schema_

- [ ] 13.2 Migration: `create_fingerprint_records_table`
  - Columns: `id`, `import_id` (FK), `user_id` (FK → users), `record_date` (DATE), `clock_in` (TIME NULL), `clock_out` (TIME NULL), timestamps
  - Unique: `(user_id, record_date)` — upsert replaces on re-import
  - Indexes: `user_id`, `record_date`, `import_id`
  - _Requirements: M3-IMP-02, Section 8 schema_

- [ ] 13.3 Migration: `create_attendance_statuses_table`
  - Columns: `id`, `user_id` (FK), `shift_assignment_id` (FK), `record_date` (DATE), `status` (ENUM: `on_time`, `late`, `left_early`, `early_exit`, `late_and_left_early`, `absent`, `off`), `minutes_late` (INT NULL), `minutes_left_early` (INT NULL), `shift_completion_pct` (DECIMAL NULL), `computed_at` (TIMESTAMP), timestamps
  - Unique: `(user_id, record_date)`
  - Indexes: `user_id`, `record_date`, `status`
  - _Requirements: M3-STAT-01 to M3-STAT-09, Section 8 schema_

---

### 14. Models — M3

- [ ] 14.1 Model: `FingerprintImport`
  - Fillable: `imported_by`, `week_start`, `filename`, `rows_imported`, `rows_failed`, `imported_at`
  - Relationships: `hasMany FingerprintRecord`, `belongsTo User`
  - _Requirements: M3-IMP-01 to M3-IMP-05_

- [ ] 14.2 Model: `FingerprintRecord`
  - Fillable: `import_id`, `user_id`, `record_date`, `clock_in`, `clock_out`
  - Relationships: `belongsTo FingerprintImport`, `belongsTo User`
  - _Requirements: M3-IMP-02_

- [ ] 14.3 Model: `AttendanceStatus`
  - Fillable: `user_id`, `shift_assignment_id`, `record_date`, `status`, `minutes_late`, `minutes_left_early`, `shift_completion_pct`, `computed_at`
  - Relationships: `belongsTo User`, `belongsTo ShiftAssignment`
  - _Requirements: M3-STAT-01 to M3-STAT-09_

---

### 15. Attendance Status Computation Service — M3

- [ ] 15.1 Service: `AttendanceComputeService`
  - Method: `computeForWeek(string $weekStart): void`
    - Loads published `WeeklySchedule` for the week
    - For each `ShiftAssignment` of type `shift`: loads `FingerprintRecord` for same `user_id` + `assignment_date`
    - Runs `computeStatus(ShiftAssignment, ?FingerprintRecord): string` to determine status
    - Upserts `AttendanceStatus` row for each cell
  - Method: `computeStatus(ShiftAssignment $a, ?FingerprintRecord $fp): array`
    - If `$fp` is null → `absent`
    - If `$a->assignment_type !== 'shift'` → `off`
    - Minutes late = `clock_in - shift_start` in minutes (negative = early, treat as 0)
    - Minutes left early = `shift_end - clock_out` in minutes
    - Shift duration = total minutes from `shift_start` to `shift_end` (handles overnight)
    - Completion % = `(clock_out - shift_start) / shift_duration × 100`
    - On Time: `minutes_late <= 5` AND `clock_out >= shift_end`
    - Late: `minutes_late > 5`
    - Left Early (standard): `completion_pct >= 60` AND `clock_out < shift_end`
    - Early Exit: `completion_pct < 60` AND `clock_out < shift_end`
    - Combined: `minutes_late > 5` AND (`left_early` OR `early_exit`)
    - Returns: `{status, minutes_late, minutes_left_early, shift_completion_pct}`
  - _Requirements: M3-STAT-01 to M3-STAT-09_

---

### 16. Controllers & Routes — M3

- [ ] 16.1 Audit grid route + `AuditController@index`
  - `GET /api/audit?week_start=YYYY-MM-DD&department_id=N&status=&search=`
  - Middleware: `auth:sanctum` + `role:compliance`
  - Logic: load published schedule for week → load all `AttendanceStatus` for week → merge into grid (employee rows × 7 days) → apply dept/status/search filters
  - Returns: `{week_start, department, summary: {on_time,late,absent,left_early,early_exit}, employees: [{user_id, name, level, days: [{date, status, minutes_late, minutes_left_early, shift_name, clock_in, clock_out, is_overnight, has_cover, is_updated}]}]}`
  - _Requirements: M3-GRID-01 to M3-GRID-06, M3-FILT-01 to M3-FILT-03, M3-OVER-01 to M3-OVER-03_

- [ ] 16.2 Cell detail route + `AuditController@cell`
  - `GET /api/audit/cell?user_id=N&date=YYYY-MM-DD`
  - Middleware: `auth:sanctum` + `role:compliance`
  - Logic: load `ShiftAssignment`, `AttendanceStatus`, `FingerprintRecord`, full `ShiftAssignmentHistory` for target cell
  - Returns: `{user, planned_shift, clock_in, clock_out, total_hours_worked, status, minutes_late, minutes_left_early, cover_details, change_history: [{changed_by, previous, new, comment, changed_at}]}`
  - _Requirements: M3-MODAL-01, M3-MODAL-02, M3-MODAL-03_

- [ ] 16.3 Fingerprint import route + `FingerprintController@import`
  - `POST /api/audit/import`
  - Middleware: `auth:sanctum` + `role:compliance`
  - Validation: `file` (required, mimes:csv,xlsx, max:10240), `week_start` (required, date)
  - Logic: validate MIME + extension → parse file rows → validate required columns (`employee_id`/`name`, `date`, `clock_in`, `clock_out`) → upsert `FingerprintRecord` rows → create `FingerprintImport` log → dispatch `RecomputeAttendanceJob` for the week
  - Returns: `{message, rows_imported, rows_failed, failed_rows: [{row, reason}]}`
  - _Requirements: M3-IMP-01 to M3-IMP-05_

- [ ] 16.4 Shift list route + `AuditShiftController@index`
  - `GET /api/audit/shifts`
  - Middleware: `auth:sanctum` + `role:compliance`
  - Returns: all shifts with `{id, name, start_time, end_time, duration_hours, is_overnight}`
  - _Requirements: M3-SHFT-01, M3-SHFT-02_

- [ ] 16.5 Shift create route + `AuditShiftController@store`
  - `POST /api/audit/shifts/create`
  - Validation: `name` (required, string, unique:shifts,name), `start_time` (required, HH:MM), `end_time` (required, HH:MM)
  - Logic: auto-compute `is_overnight = end_time < start_time` → create `Shift`
  - Returns: full shift object with `is_overnight` flag
  - _Requirements: M3-SHFT-03_

- [ ] 16.6 Shift update route + `AuditShiftController@update`
  - `PUT /api/audit/shifts/{shift}/update`
  - Validation: same as create, `sometimes|required`
  - Logic: update shift, re-compute `is_overnight`
  - Returns: updated shift object
  - _Requirements: M3-SHFT-04_

- [ ] 16.7 Shift delete route + `AuditShiftController@destroy`
  - `DELETE /api/audit/shifts/{shift}/delete`
  - Logic: check if shift is referenced in future `ShiftAssignment` → if yes: 422 with warning → if no: delete with confirmation
  - Returns: `{message}` or `422 {message, assigned_count}`
  - _Requirements: M3-SHFT-05_

---

### 17. Background Job — M3

- [ ] 17.1 Job: `RecomputeAttendanceJob`
  - Dispatched after fingerprint import
  - Logic: calls `AttendanceComputeService::computeForWeek($weekStart)`
  - Idempotent: re-running same week overwrites existing `AttendanceStatus` rows (upsert)
  - _Requirements: M3-IMP-04, M3-STAT-01_

---

### 18. Tests — M3

- [ ] 18.1 `AuditGridTest.php`
  - Grid returns correct structure, today highlight field present, summary counts correct, non-compliance role returns 403
  - _Requirements: M3-GRID-01 to M3-GRID-06, M3-MGR-01_

- [ ] 18.2 `AttendanceStatusComputeTest.php`
  - On Time: clock-in within 5 min, clock-out at/after end
  - Late: clock-in > 5 min after start
  - Left Early (standard): ≥60% completion, left before end
  - Early Exit: <60% completion
  - Combined Late+Left Early: both conditions true
  - Absent: no fingerprint record
  - Off day: assignment_type != shift → status = off
  - Overnight shift: duration computed correctly across midnight
  - _Requirements: M3-STAT-01 to M3-STAT-09, M3-OVER-03_

- [ ] 18.3 `FingerprintImportTest.php`
  - Valid CSV imports successfully, valid XLSX imports successfully, invalid column structure returns 422, re-import upserts (does not duplicate), import triggers recompute, non-compliance role returns 403
  - _Requirements: M3-IMP-01 to M3-IMP-05_

- [ ] 18.4 `AuditCellDetailTest.php`
  - Returns all fields (planned, actual, status, history), history is read-only (no write endpoint exists)
  - _Requirements: M3-MODAL-01 to M3-MODAL-03_

- [ ] 18.5 `AuditShiftTest.php`
  - CRUD on shifts, overnight auto-detected, delete blocked if in use, delete with confirmation dialog data
  - _Requirements: M3-SHFT-01 to M3-SHFT-06_

- [ ] 18.6 `AuditFilterTest.php`
  - Filter by department, filter by status, search by name, combined filters
  - _Requirements: M3-FILT-01 to M3-FILT-03_

---

---

## Final Checkpoints

- [ ] **CP-1** — After M2 implementation: run full test suite (M1 + M2), confirm 0 regressions
- [ ] **CP-2** — After M3 implementation: run full test suite (M1 + M2 + M3), confirm 0 regressions
- [ ] **CP-3** — Database review: confirm all 7 new migrations run cleanly on fresh database (`php artisan migrate:fresh --seed`)
- [ ] **CP-4** — API documentation: update `API_TESTING_GUIDE.md` with all M2 and M3 endpoint examples
- [ ] **CP-5** — Final SRS review: confirm all 96 functional requirements in Section 3 have a corresponding task in this document

---

*— End of Section 9 — SRS Complete —*

---

**CONFIDENTIAL — NVT Internal Use Only**
