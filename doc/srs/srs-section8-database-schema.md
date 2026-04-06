---
**SOFTWARE REQUIREMENTS SPECIFICATION**
**Employee Schedule Management System**

| | |
|---|---|
| **Organization** | NVT — New Vision Team |
| **Document type** | Software Requirements Specification (SRS) |
| **Current section** | Section 8 — Database Schema |
| **Document status** | Draft — In progress (9 sections total) |
| **Version** | 1.0 |
| **Prepared by** | NVT Development Team |
| **Date** | 31 March 2026 |

---

**SRS Progress — Section 8 of 9**

| | |
|---|---|
| ✅ Section 1: Introduction | ✅ Section 2: Overall Description |
| ✅ Section 3: Functional Requirements | ✅ Section 4: Non-Functional Requirements |
| ✅ Section 5: System Users & Roles | ✅ Section 6: Use Cases |
| ✅ Section 7: Technical Flow (Backend) | ✅ Section 8: Database Schema *(this document)* |
| ⬜ Section 9: Final Assembly & Appendix | |

---

## About This Document

This document defines the complete relational database schema for the Employee Schedule Management System. It covers all three modules:

- **M1 — Core Admin & Hierarchy** — tables that already exist (✅ implemented)
- **M2 — Manager Schedule System** — tables to be created when M2 is built (⬜ pending)
- **M3 — Audit & Compliance System** — tables to be created when M3 is built (⬜ pending)

Each table entry includes: column names, types, constraints, indexes, and foreign key relationships. A full Entity-Relationship summary is provided at the end.

**Notation:**
- `PK` = Primary Key
- `FK` = Foreign Key
- `UQ` = Unique constraint
- `IDX` = Index (non-unique)
- `NN` = NOT NULL
- `→` = references (FK target)

---

## M1 — Core Admin & Hierarchy (✅ Implemented)

---

### Table: `users`
> Base user accounts for all system users.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `name` | VARCHAR(255) | NN | Full display name |
| `nickname` | VARCHAR(100) | NULL | Optional short name |
| `email` | VARCHAR(255) | NN, UQ | Login credential |
| `email_verified_at` | TIMESTAMP | NULL | |
| `password` | VARCHAR(255) | NN | bcrypt hash |
| `department_id` | BIGINT UNSIGNED | NN, FK → `departments.id` | One department per user |
| `user_level_id` | BIGINT UNSIGNED | NN, FK → `user_levels.id` | L1–L6 or L2PM |
| `user_level_tier_id` | BIGINT UNSIGNED | NULL, FK → `user_level_tiers.id` | Optional sub-tier |
| `is_admin` | BOOLEAN | NN, DEFAULT false | Grants M1 access |
| `remember_token` | VARCHAR(100) | NULL | Laravel auth |
| `created_at` | TIMESTAMP | NULL | |
| `updated_at` | TIMESTAMP | NULL | |

**Indexes:** `email` (UQ), `department_id` (IDX), `user_level_id` (IDX)

---

### Table: `departments`
> Organizational units in a self-referencing hierarchy.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `name` | VARCHAR(255) | NN | Department name |
| `description` | TEXT | NULL | |
| `parent_id` | BIGINT UNSIGNED | NULL, FK → `departments.id` (NULL ON DELETE) | Self-referencing parent |
| `path` | VARCHAR(255) | NULL | Materialized path e.g. `/1/4/7/` |
| `is_active` | BOOLEAN | NN, DEFAULT true | |
| `created_at` | TIMESTAMP | NULL | |
| `updated_at` | TIMESTAMP | NULL | |

**Indexes:** `parent_id` (IDX), `path` (IDX)

---

### Table: `user_levels`
> Defines L1–L6 and L2PM level hierarchy.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `code` | VARCHAR(50) | NN, UQ | e.g. `L1`, `L2`, `L2PM`, `L6` |
| `name` | VARCHAR(100) | NN | Human-readable name |
| `hierarchy_rank` | INT | NN | Numeric rank for ordering |
| `description` | TEXT | NULL | |
| `created_at` | TIMESTAMP | NULL | |
| `updated_at` | TIMESTAMP | NULL | |

**Indexes:** `code` (UQ)

---

### Table: `user_level_tiers`
> Sub-tiers within a user level (e.g. L2 Tier 1, L2 Tier 2).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `user_level_id` | BIGINT UNSIGNED | NN, FK → `user_levels.id` CASCADE DELETE | Parent level |
| `tier_name` | VARCHAR(100) | NN | e.g. "Tier 1", "Senior" |
| `tier_order` | INT | NN | Sort order within level |
| `description` | TEXT | NULL | |
| `created_at` | TIMESTAMP | NULL | |
| `updated_at` | TIMESTAMP | NULL | |

**Indexes:** `user_level_id` (IDX)

---

### Table: `roles` *(Spatie)*
> Spatie permission roles — level codes + Compliance.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `name` | VARCHAR(255) | NN, UQ per guard | e.g. `L1`, `L2`, `Compliance` |
| `guard_name` | VARCHAR(255) | NN, DEFAULT `web` | |
| `created_at` | TIMESTAMP | NULL | |
| `updated_at` | TIMESTAMP | NULL | |

---

### Table: `permissions` *(Spatie)*
> Named permissions assignable to roles.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `name` | VARCHAR(255) | NN, UQ per guard | e.g. `manage_schedule` |
| `guard_name` | VARCHAR(255) | NN, DEFAULT `web` | |
| `created_at` | TIMESTAMP | NULL | |
| `updated_at` | TIMESTAMP | NULL | |

---

### Table: `model_has_roles` *(Spatie pivot)*
> Assigns roles to users.

| Column | Type | Constraints |
|---|---|---|
| `role_id` | BIGINT UNSIGNED | FK → `roles.id` |
| `model_type` | VARCHAR(255) | Polymorphic type |
| `model_id` | BIGINT UNSIGNED | FK → `users.id` |

**Composite PK:** `(role_id, model_id, model_type)`

---

### Table: `role_has_permissions` *(Spatie pivot)*
> Assigns permissions to roles.

| Column | Type | Constraints |
|---|---|---|
| `permission_id` | BIGINT UNSIGNED | FK → `permissions.id` |
| `role_id` | BIGINT UNSIGNED | FK → `roles.id` |

**Composite PK:** `(permission_id, role_id)`

---

### Table: `personal_access_tokens` *(Sanctum)*
> Bearer tokens issued on login.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `tokenable_type` | VARCHAR(255) | NN | Polymorphic type |
| `tokenable_id` | BIGINT UNSIGNED | NN | FK → `users.id` |
| `name` | VARCHAR(255) | NN | Token name (e.g. `api-token`) |
| `token` | VARCHAR(64) | NN, UQ | Hashed token |
| `abilities` | TEXT | NULL | |
| `last_used_at` | TIMESTAMP | NULL | |
| `expires_at` | TIMESTAMP | NULL | 24h expiry |
| `created_at` | TIMESTAMP | NULL | |
| `updated_at` | TIMESTAMP | NULL | |

---

## M2 — Manager Schedule System (⬜ Pending Implementation)

---

### Table: `shifts`
> Company-wide shift definitions. Managed by Compliance (M3) and read by M2.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `name` | VARCHAR(100) | NN, UQ | e.g. `Shift A`, `Shift D` |
| `start_time` | TIME | NN | e.g. `06:00:00` |
| `end_time` | TIME | NN | e.g. `14:00:00` |
| `duration_minutes` | INT | NN | Auto-calculated on save |
| `is_overnight` | BOOLEAN | NN, DEFAULT false | `true` when end_time < start_time |
| `created_at` | TIMESTAMP | NULL | |
| `updated_at` | TIMESTAMP | NULL | |

**Indexes:** `name` (UQ)

---

### Table: `weekly_schedules`
> One row per department per week. Tracks draft/published state.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `department_id` | BIGINT UNSIGNED | NN, FK → `departments.id` | Scoped to one department |
| `week_start` | DATE | NN | Always a Monday |
| `week_end` | DATE | NN | Always the following Sunday |
| `status` | ENUM(`draft`, `published`) | NN, DEFAULT `draft` | |
| `published_at` | TIMESTAMP | NULL | Set when status → published |
| `published_by` | BIGINT UNSIGNED | NULL, FK → `users.id` | Manager who published |
| `created_at` | TIMESTAMP | NULL | |
| `updated_at` | TIMESTAMP | NULL | |

**Indexes:** `(department_id, week_start)` (UQ) — one schedule per department per week

---

### Table: `shift_assignments`
> One row per employee per day within a weekly schedule. Every assignment is one cell in the grid.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `weekly_schedule_id` | BIGINT UNSIGNED | NN, FK → `weekly_schedules.id` CASCADE DELETE | Parent schedule |
| `user_id` | BIGINT UNSIGNED | NN, FK → `users.id` | The employee |
| `assignment_date` | DATE | NN | Specific day (Mon–Sun) |
| `assignment_type` | ENUM(`shift`, `day_off`, `sick_day`, `leave_request`) | NN | Working or off type |
| `shift_id` | BIGINT UNSIGNED | NULL, FK → `shifts.id` | Required when type = `shift` |
| `is_cover` | BOOLEAN | NN, DEFAULT false | Cover shift flag |
| `covers_user_id` | BIGINT UNSIGNED | NULL, FK → `users.id` | Employee being covered |
| `covers_shift_id` | BIGINT UNSIGNED | NULL, FK → `shifts.id` | Shift slot being covered |
| `comment` | TEXT | NULL | Optional manager note |
| `created_by` | BIGINT UNSIGNED | NN, FK → `users.id` | Manager who created it |
| `created_at` | TIMESTAMP | NULL | |
| `updated_at` | TIMESTAMP | NULL | |

**Indexes:**
- `(weekly_schedule_id, user_id, assignment_date)` (UQ) — one assignment per employee per day per schedule
- `user_id` (IDX)
- `assignment_date` (IDX)
- `shift_id` (IDX)

---

### Table: `shift_assignment_history`
> Immutable audit log. One row per change to any shift_assignment cell.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `shift_assignment_id` | BIGINT UNSIGNED | NN, FK → `shift_assignments.id` CASCADE DELETE | Parent assignment |
| `changed_by` | BIGINT UNSIGNED | NN, FK → `users.id` | Manager who made the change |
| `previous_type` | ENUM(`shift`, `day_off`, `sick_day`, `leave_request`) | NULL | Value before change |
| `previous_shift_id` | BIGINT UNSIGNED | NULL, FK → `shifts.id` | Shift before change |
| `new_type` | ENUM(`shift`, `day_off`, `sick_day`, `leave_request`) | NN | Value after change |
| `new_shift_id` | BIGINT UNSIGNED | NULL, FK → `shifts.id` | Shift after change |
| `comment` | TEXT | NULL | Comment at time of change |
| `changed_at` | TIMESTAMP | NN, DEFAULT CURRENT_TIMESTAMP | |

**Indexes:** `shift_assignment_id` (IDX), `changed_by` (IDX), `changed_at` (IDX)

> ⚠️ This table is **append-only**. No UPDATE or DELETE is permitted via any application-level code.

---

## M3 — Audit & Compliance System (⬜ Pending Implementation)

---

### Table: `fingerprint_imports`
> Tracks each CSV/Excel file uploaded by the Compliance team.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `uploaded_by` | BIGINT UNSIGNED | NN, FK → `users.id` | Compliance user who uploaded |
| `file_name` | VARCHAR(255) | NN | Original filename |
| `week_start` | DATE | NN | Week this data covers |
| `row_count` | INT | NN | Total rows parsed |
| `valid_count` | INT | NN | Rows successfully matched |
| `error_count` | INT | NN | Rows that failed matching |
| `import_status` | ENUM(`success`, `partial`, `failed`) | NN | |
| `created_at` | TIMESTAMP | NULL | |
| `updated_at` | TIMESTAMP | NULL | |

**Indexes:** `week_start` (IDX), `uploaded_by` (IDX)

---

### Table: `fingerprint_records`
> One row per employee per attendance event parsed from an import file.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `fingerprint_import_id` | BIGINT UNSIGNED | NN, FK → `fingerprint_imports.id` CASCADE DELETE | Source import file |
| `user_id` | BIGINT UNSIGNED | NN, FK → `users.id` | Matched employee |
| `attendance_date` | DATE | NN | The calendar date |
| `clock_in` | TIME | NULL | First clock-in event of the day |
| `clock_out` | TIME | NULL | Last clock-out event of the day |
| `created_at` | TIMESTAMP | NULL | |
| `updated_at` | TIMESTAMP | NULL | |

**Indexes:**
- `(user_id, attendance_date)` (UQ per import — use upsert on re-import)
- `user_id` (IDX)
- `attendance_date` (IDX)
- `fingerprint_import_id` (IDX)

---

### Table: `attendance_statuses`
> Computed attendance status per employee per day. Recalculated after each import.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `user_id` | BIGINT UNSIGNED | NN, FK → `users.id` | Employee |
| `shift_assignment_id` | BIGINT UNSIGNED | NN, FK → `shift_assignments.id` | The published assignment being audited |
| `fingerprint_record_id` | BIGINT UNSIGNED | NULL, FK → `fingerprint_records.id` | Matched fingerprint row; NULL = Absent |
| `attendance_date` | DATE | NN | The calendar date |
| `status` | ENUM(`on_time`, `late`, `left_early`, `early_exit`, `late_and_left_early`, `absent`) | NN | Computed status |
| `minutes_late` | SMALLINT UNSIGNED | NULL | Minutes after shift start (if late) |
| `minutes_left_early` | SMALLINT UNSIGNED | NULL | Minutes before shift end (if left early) |
| `shift_completion_pct` | DECIMAL(5,2) | NULL | % of shift worked before leaving |
| `computed_at` | TIMESTAMP | NN, DEFAULT CURRENT_TIMESTAMP | When this status was last computed |
| `created_at` | TIMESTAMP | NULL | |
| `updated_at` | TIMESTAMP | NULL | |

**Indexes:**
- `(user_id, attendance_date)` (UQ) — one status per employee per day
- `status` (IDX)
- `shift_assignment_id` (IDX)
- `attendance_date` (IDX)

> Status computation rules (from M3-STAT-01 to M3-STAT-09):
> - `on_time`: clock_in ≤ shift_start + 5min AND clock_out ≥ shift_end
> - `late`: clock_in > shift_start + 5min
> - `left_early`: clock_out < shift_end AND shift_completion_pct ≥ 60%
> - `early_exit`: clock_out < shift_end AND shift_completion_pct < 60%
> - `late_and_left_early`: both late AND left early conditions met simultaneously
> - `absent`: no fingerprint_record exists for this user on this date

---

## Schema Summary — All Tables

| # | Table | Module | Status | Rows represent |
|---|---|---|---|---|
| 1 | `users` | M1 | ✅ Exists | Every system user |
| 2 | `departments` | M1 | ✅ Exists | Org units in nested hierarchy |
| 3 | `user_levels` | M1 | ✅ Exists | L1–L6, L2PM level definitions |
| 4 | `user_level_tiers` | M1 | ✅ Exists | Sub-tiers within a level |
| 5 | `roles` | M1 | ✅ Exists (Spatie) | Named roles (level codes + Compliance) |
| 6 | `permissions` | M1 | ✅ Exists (Spatie) | Named permissions |
| 7 | `model_has_roles` | M1 | ✅ Exists (Spatie) | User ↔ Role assignments |
| 8 | `role_has_permissions` | M1 | ✅ Exists (Spatie) | Role ↔ Permission assignments |
| 9 | `personal_access_tokens` | M1 | ✅ Exists (Sanctum) | Bearer tokens per login |
| 10 | `shifts` | M2+M3 | ⬜ To create | Company-wide shift definitions |
| 11 | `weekly_schedules` | M2 | ⬜ To create | One schedule per dept per week |
| 12 | `shift_assignments` | M2 | ⬜ To create | One cell in the schedule grid |
| 13 | `shift_assignment_history` | M2 | ⬜ To create | Immutable change log per cell |
| 14 | `fingerprint_imports` | M3 | ⬜ To create | Each uploaded CSV/XLSX file |
| 15 | `fingerprint_records` | M3 | ⬜ To create | One attendance event per employee/day |
| 16 | `attendance_statuses` | M3 | ⬜ To create | Computed status per employee/day |

---

## Entity-Relationship Summary

```
users ──────────────────────────────────────────────────────────────────────────────────
  │ department_id → departments.id
  │ user_level_id → user_levels.id
  │ user_level_tier_id → user_level_tiers.id
  │ (via Spatie) → roles → permissions
  │
  ├── shift_assignments.user_id
  ├── shift_assignments.covers_user_id
  ├── shift_assignments.created_by
  ├── shift_assignment_history.changed_by
  ├── weekly_schedules.published_by
  ├── fingerprint_records.user_id
  ├── fingerprint_imports.uploaded_by
  └── attendance_statuses.user_id

departments ────────────────────────────────────────────────────────────────────────────
  │ parent_id → departments.id (self-referencing)
  └── weekly_schedules.department_id

user_levels ────────────────────────────────────────────────────────────────────────────
  └── user_level_tiers.user_level_id

shifts ─────────────────────────────────────────────────────────────────────────────────
  ├── shift_assignments.shift_id
  ├── shift_assignments.covers_shift_id
  └── shift_assignment_history.previous_shift_id / new_shift_id

weekly_schedules ───────────────────────────────────────────────────────────────────────
  └── shift_assignments.weekly_schedule_id

shift_assignments ──────────────────────────────────────────────────────────────────────
  ├── shift_assignment_history.shift_assignment_id
  └── attendance_statuses.shift_assignment_id

fingerprint_imports ────────────────────────────────────────────────────────────────────
  └── fingerprint_records.fingerprint_import_id

fingerprint_records ────────────────────────────────────────────────────────────────────
  └── attendance_statuses.fingerprint_record_id
```

---

*— End of Section 8 — Continue to Section 9: Final Assembly & Appendix —*

---

**CONFIDENTIAL — NVT Internal Use Only**
