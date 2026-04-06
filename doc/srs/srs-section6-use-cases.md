---
**SOFTWARE REQUIREMENTS SPECIFICATION**
**Employee Schedule Management System**

| | |
|---|---|
| **Organization** | NVT — New Vision Team |
| **Document type** | Software Requirements Specification (SRS) |
| **Current section** | Section 6 — Use Cases |
| **Document status** | Draft — In progress (9 sections total) |
| **Version** | 1.0 |
| **Prepared by** | NVT Development Team |
| **Date** | 31 March 2026 |

---

**SRS Progress — Section 6 of 9**

| | |
|---|---|
| ✅ Section 1: Introduction | ✅ Section 2: Overall Description |
| ✅ Section 3: Functional Requirements | ✅ Section 4: Non-Functional Requirements |
| ✅ Section 5: System Users & Roles | ✅ Section 6: Use Cases *(this document)* |
| ⬜ Section 7: Technical Requirements | ⬜ Section 8: Constraints & Assumptions |
| ⬜ Section 9: Final Assembly & Appendix | |

---

## 6.1  Use Case Format

Each use case in this section follows a consistent structure:
- **ID**: Unique reference (UC-XX)
- **Primary Actor**: Main user role initiating the flow
- **Goal**: Business objective of the use case
- **Preconditions**: Conditions that must be true before the flow starts
- **Trigger**: Event that starts the use case
- **Main Flow**: Normal success steps
- **Alternate / Exception Flows**: Error or branching paths
- **Postconditions**: State after completion

---

## 6.2  Core Use Cases (Admin)

### UC-01 — Admin Creates a Department

| Field | Description |
|---|---|
| **Primary Actor** | Admin |
| **Goal** | Create a new department, optionally under a parent department |
| **Preconditions** | Admin is authenticated with valid bearer token and `is_admin = true` |
| **Trigger** | Admin clicks `Create Department` |

**Main Flow**
1. Admin opens Department Management.
2. Admin enters department name.
3. Admin optionally selects a parent department.
4. Admin submits the form.
5. System validates input.
6. System creates the department.
7. System refreshes the department list/tree and shows success message.

**Alternate / Exception Flows**
- A1: Department name is empty -> system rejects request and shows validation error.
- A2: Parent department is invalid -> system rejects request and shows validation error.
- A3: User is not admin -> system returns HTTP 403.

**Postconditions**
- New department exists in the hierarchy and is available for user assignment.

---

### UC-02 — Admin Creates a User and Assigns Role Context

| Field | Description |
|---|---|
| **Primary Actor** | Admin |
| **Goal** | Create a user with level/department and optional admin/compliance access |
| **Preconditions** | Admin authenticated; at least one department and one level exist |
| **Trigger** | Admin clicks `Create User` |

**Main Flow**
1. Admin opens User Management.
2. Admin enters name, email, and password.
3. Admin selects department and user level.
4. Admin optionally selects tier.
5. Admin optionally enables `is_admin`.
6. Admin optionally assigns `Compliance` role.
7. Admin saves user.
8. System creates user and auto-syncs the level-code role (`L1`..`L6`, `L2PM`).
9. System shows created user in listing.

**Alternate / Exception Flows**
- A1: Duplicate email -> system rejects and shows error.
- A2: Missing required field -> system rejects and shows validation messages.

**Postconditions**
- User account exists with one department, one level role, and optional extra access flags/roles.

---

### UC-03 — Admin Maintains Roles and Permissions

| Field | Description |
|---|---|
| **Primary Actor** | Admin |
| **Goal** | Create/update/delete roles and permissions and sync permissions to roles |
| **Preconditions** | Admin authenticated |
| **Trigger** | Admin opens Roles or Permissions screens |

**Main Flow**
1. Admin creates or edits a role.
2. Admin creates or edits permissions.
3. Admin syncs selected permissions to a role.
4. System stores role-permission mappings.
5. System confirms updates.

**Alternate / Exception Flows**
- A1: Role name already exists -> system rejects create/update.
- A2: Permission name already exists -> system rejects create/update.

**Postconditions**
- Role-permission model is updated and enforceable by middleware.

---

## 6.3  Core Use Cases (Manager)

### UC-04 — Manager Builds Weekly Schedule (Draft)

| Field | Description |
|---|---|
| **Primary Actor** | Manager (L2 or above) |
| **Goal** | Fill weekly schedule for all employees in own department |
| **Preconditions** | Manager authenticated; manager assigned to one department; target week exists |
| **Trigger** | Manager opens M2 weekly grid |

**Main Flow**
1. Manager opens weekly schedule grid (Monday-Sunday).
2. System loads all employees in manager's department.
3. Manager clicks a cell.
4. System opens assign/edit dialog.
5. Manager selects shift or off type.
6. Manager optionally adds comment and/or cover details.
7. Manager saves.
8. System stores assignment and logs history.
9. Manager repeats until week is complete.

**Alternate / Exception Flows**
- A1: Manager tries to access another department -> system blocks access.
- A2: Invalid assignment payload -> system rejects and shows validation error.

**Postconditions**
- Schedule remains in Draft state with saved assignments and full history.

---

### UC-05 — Manager Uses Bulk Assignment

| Field | Description |
|---|---|
| **Primary Actor** | Manager |
| **Goal** | Assign one shift to many cells quickly |
| **Preconditions** | Manager authenticated in M2 grid |
| **Trigger** | Manager enables bulk mode |

**Main Flow**
1. Manager selects bulk mode by employees or by days.
2. System shows selection checkboxes.
3. Manager selects rows or day columns.
4. Manager picks one shift/off type.
5. Manager confirms apply action.
6. System applies assignment to all selected targets.
7. System logs change history per affected cell.

**Alternate / Exception Flows**
- A1: No target selected -> system blocks action and shows guidance.
- A2: Partial failure on some cells -> system applies successful cells and reports failed ones.

**Postconditions**
- Multiple schedule cells are updated in a single action.

---

### UC-06 — Manager Copies Last Week

| Field | Description |
|---|---|
| **Primary Actor** | Manager |
| **Goal** | Pre-fill current week from previous week |
| **Preconditions** | Previous week schedule exists |
| **Trigger** | Manager clicks `Copy Last Week` |

**Main Flow**
1. Manager clicks copy action.
2. System shows confirmation dialog.
3. Manager confirms.
4. System duplicates prior week assignments into current week.
5. System displays updated grid.
6. Manager edits cells as needed.

**Alternate / Exception Flows**
- A1: Previous week not found -> system shows message and does not copy.
- A2: Manager cancels confirmation -> no changes made.

**Postconditions**
- Current week draft is populated and ready for review/edit.

---

### UC-07 — Manager Publishes Schedule

| Field | Description |
|---|---|
| **Primary Actor** | Manager (L2+) |
| **Goal** | Publish validated weekly schedule for compliance auditing |
| **Preconditions** | Schedule is Draft; all employees have non-empty assignments for all 7 days |
| **Trigger** | Manager clicks `Publish Schedule` |

**Main Flow**
1. Manager clicks publish.
2. System validates completeness of all week cells.
3. System transitions state from Draft to Published.
4. System records publisher and timestamp.
5. System exposes schedule to M3 audit computations.

**Alternate / Exception Flows**
- A1: At least one required cell is empty -> system blocks publish and highlights missing assignments.
- A2: Unauthorized user attempts publish -> system returns HTTP 403.

**Postconditions**
- Published schedule is available to Compliance in M3.

---

### UC-08 — Automated Reminder and Auto-Copy

| Field | Description |
|---|---|
| **Primary Actor** | System Scheduler (background job) |
| **Goal** | Ensure weekly schedule exists by Tuesday noon |
| **Preconditions** | Scheduler service and email service are configured |
| **Trigger** | Monday 12:00 PM and Tuesday 12:00 PM cron events |

**Main Flow**
1. On Monday 12:00 PM, system checks schedule presence and sends reminder email.
2. On Tuesday 12:00 PM, system checks again.
3. If schedule still missing, system sends second reminder.
4. System auto-copies previous week into current week.
5. System logs actions in job logs.

**Alternate / Exception Flows**
- A1: Email service fails -> system logs failure and retries per queue policy.
- A2: No previous week data exists -> system logs warning and skips auto-copy.

**Postconditions**
- Department has either manager-created or auto-copied schedule by Tuesday.

---

## 6.4  Core Use Cases (Compliance)

### UC-09 — Compliance Uploads Fingerprint Data

| Field | Description |
|---|---|
| **Primary Actor** | Compliance Team Member |
| **Goal** | Import attendance records from CSV/Excel into M3 |
| **Preconditions** | User has Compliance role; file is prepared in expected format |
| **Trigger** | Compliance user clicks `Upload Fingerprint File` |

**Main Flow**
1. Compliance user selects CSV or Excel file.
2. System validates file type and structure.
3. System parses records.
4. System links records to employees and dates.
5. System persists import result.
6. System recomputes attendance status for affected week.
7. System shows import summary.

**Alternate / Exception Flows**
- A1: Invalid file format -> system rejects file and shows specific errors.
- A2: Unknown employee references -> system reports rows with unmatched identifiers.

**Postconditions**
- Valid attendance records are available for audit comparison.

---

### UC-10 — Compliance Reviews Audit Grid

| Field | Description |
|---|---|
| **Primary Actor** | Compliance Team Member |
| **Goal** | Monitor attendance compliance and detect violations |
| **Preconditions** | Published schedule exists; fingerprint data is available |
| **Trigger** | Compliance user opens M3 Audit Grid |

**Main Flow**
1. Compliance user opens weekly audit grid.
2. System displays status colors per employee/day.
3. User applies filters (department/status/search) as needed.
4. User clicks a cell for details.
5. System shows planned vs actual, minutes late/early, and change history.
6. User navigates weeks as needed.

**Alternate / Exception Flows**
- A1: No published schedule for selected week -> system shows no-audit-data notice.
- A2: No fingerprint records for selected week -> statuses reflect Absent/Not computable per rules.

**Postconditions**
- Compliance user obtains actionable attendance insights for decisions/reporting.

---

### UC-11 — Compliance Manages Shift Definitions

| Field | Description |
|---|---|
| **Primary Actor** | Compliance Team Member |
| **Goal** | Maintain company-wide shift catalog |
| **Preconditions** | User has Compliance role |
| **Trigger** | User opens Shifts Management tab |

**Main Flow**
1. User opens shifts list.
2. User adds, edits, or deletes a shift.
3. System validates times and calculates duration.
4. If shift crosses midnight, system marks it as overnight.
5. System saves updates and refreshes table.

**Alternate / Exception Flows**
- A1: Invalid time range -> system blocks save and shows validation message.
- A2: Delete requested on shift in active use -> system requires confirmation and warns impact.

**Postconditions**
- Shift catalog remains accurate for scheduling and audit logic.

---

## 6.5  Access Control Use Cases

### UC-12 — Unauthorized Access to Audit by Manager

| Field | Description |
|---|---|
| **Primary Actor** | Manager |
| **Goal** | (Negative case) Verify manager cannot access M3 |
| **Preconditions** | Manager authenticated without Compliance role |
| **Trigger** | Manager attempts to open M3 route or endpoint |

**Main Flow**
1. Manager requests M3 screen or API endpoint.
2. System checks role permissions.
3. System denies access (HTTP 403 / UI "Access Denied").
4. Attempt is logged for audit trail.

**Postconditions**
- No M3 data is exposed to non-Compliance users.

---

### UC-13 — Unauthorized Access to M1 by Compliance User

| Field | Description |
|---|---|
| **Primary Actor** | Compliance Team Member |
| **Goal** | (Negative case) Verify Compliance user cannot access M1 admin endpoints |
| **Preconditions** | Compliance user authenticated with no `is_admin` flag |
| **Trigger** | Compliance user attempts M1 admin action |

**Main Flow**
1. Compliance user requests M1 endpoint.
2. System executes `is_admin` middleware check.
3. System denies request with HTTP 403.

**Postconditions**
- Admin-only controls remain isolated to Admin users.

---

## 6.6  Use Case Traceability

| Use Case ID | Related Module | Related Functional Requirement Areas |
|---|---|---|
| UC-01, UC-02, UC-03 | M1 | AUTH, DEPT, LVL, TIER, USR, ROLE, PERM |
| UC-04, UC-05, UC-06, UC-07, UC-08 | M2 | GRID, ASGN, BULK, COPY, PUB, EMAIL |
| UC-09, UC-10, UC-11 | M3 | IMP, GRID, STAT, FILT, MODAL, SHFT, OVER |
| UC-12, UC-13 | Cross-module | Access restrictions (M1/M3 boundaries) |

---

*— End of Section 6 — Continue to Section 7: Technical Requirements —*

---

**CONFIDENTIAL — NVT Internal Use Only**
