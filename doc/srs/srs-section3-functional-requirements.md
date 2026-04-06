---
**SOFTWARE REQUIREMENTS SPECIFICATION**
**Employee Schedule Management System**

| | |
|---|---|
| **Organization** | NVT — New Vision Team |
| **Document type** | Software Requirements Specification (SRS) |
| **Current section** | Section 3 — Functional Requirements |
| **Document status** | Draft — In progress (9 sections total) |
| **Version** | 1.0 |
| **Prepared by** | NVT Development Team |
| **Date** | 31 March 2026 |

---

**SRS Progress — Section 3 of 9**

| | |
|---|---|
| ✅ Section 1: Introduction | ✅ Section 2: Overall Description |
| ✅ Section 3: Functional Requirements *(this document)* | ⬜ Section 4: Non-Functional Requirements |
| ⬜ Section 5: System Users & Roles | ⬜ Section 6: Use Cases |
| ⬜ Section 7: Technical Requirements | ⬜ Section 8: Constraints & Assumptions |
| ⬜ Section 9: Final Assembly & Appendix | |

---

## Overview

This section defines all functional requirements for the Employee Schedule Management System, organized into three modules. Each requirement is identified by a unique requirement ID and includes a description, actor, and source reference.

**Requirement ID format:** `[Module]-[Category]-[Number]`
- `M1` = Core Admin & Hierarchy
- `M2` = Manager Schedule System
- `M3` = Audit & Compliance System

**Priority notation:**
- `SHALL` — Mandatory. The system must implement this requirement.
- `SHOULD` — Recommended. The system should implement this unless technically constrained.
- `MAY` — Optional. The system may implement this as an enhancement.

---

## Module 1 — Core Admin & Hierarchy (M1)

> **Implementation status:** ✅ Fully implemented.
> All M1 requirements are satisfied by the existing Laravel 13 REST API (36 routes, 101 passing tests).

### 3.1.1  Authentication

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M1-AUTH-01 | The system SHALL allow a registered user to log in using their email address and password, and SHALL return a bearer token upon successful authentication. | Any User | SHALL |
| M1-AUTH-02 | The system SHALL allow an authenticated user to log out, which SHALL invalidate their current bearer token. | Any User | SHALL |
| M1-AUTH-03 | The system SHALL provide an endpoint that returns the profile of the currently authenticated user, including their name, email, level, tier, department, and roles. | Any User | SHALL |
| M1-AUTH-04 | The system SHALL reject all protected endpoint requests that do not include a valid bearer token with HTTP 401 Unauthorized. | System | SHALL |

---

### 3.1.2  Department Management

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M1-DEPT-01 | The system SHALL allow the Admin to create a new department with a name and an optional parent department, enabling unlimited nesting depth. | Admin | SHALL |
| M1-DEPT-02 | The system SHALL allow the Admin to retrieve a flat list of all departments. | Admin | SHALL |
| M1-DEPT-03 | The system SHALL allow the Admin to retrieve the full department hierarchy as a nested tree structure. | Admin | SHALL |
| M1-DEPT-04 | The system SHALL allow the Admin to retrieve a single department by its ID, including its parent reference. | Admin | SHALL |
| M1-DEPT-05 | The system SHALL allow the Admin to update the name and parent of an existing department. | Admin | SHALL |
| M1-DEPT-06 | The system SHALL allow the Admin to delete a department. | Admin | SHALL |
| M1-DEPT-07 | The system SHALL allow the Admin to retrieve all users assigned to a specific department. | Admin | SHALL |

---

### 3.1.3  User Level Management

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M1-LVL-01 | The system SHALL allow the Admin to create a new user level with a name and a level code (e.g., L1, L2, L3, L4, L5, L6, L2PM). | Admin | SHALL |
| M1-LVL-02 | The system SHALL allow the Admin to retrieve a list of all user levels. | Admin | SHALL |
| M1-LVL-03 | The system SHALL allow the Admin to retrieve a single user level by its ID. | Admin | SHALL |
| M1-LVL-04 | The system SHALL allow the Admin to update the name and code of an existing user level. | Admin | SHALL |
| M1-LVL-05 | The system SHALL allow the Admin to delete a user level. | Admin | SHALL |

---

### 3.1.4  User Level Tier Management

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M1-TIER-01 | The system SHALL allow the Admin to create a sub-tier within a user level (e.g., Tier 1, Tier 2 under L2). | Admin | SHALL |
| M1-TIER-02 | The system SHALL allow the Admin to retrieve all tiers belonging to a specific user level. | Admin | SHALL |
| M1-TIER-03 | The system SHALL allow the Admin to retrieve a single tier by its ID within a user level. | Admin | SHALL |
| M1-TIER-04 | The system SHALL allow the Admin to update the name of an existing tier. | Admin | SHALL |
| M1-TIER-05 | The system SHALL allow the Admin to delete a tier. | Admin | SHALL |

---

### 3.1.5  User Management

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M1-USR-01 | The system SHALL allow the Admin to create a new user account with name, email, password, department, user level, and optionally a tier. | Admin | SHALL |
| M1-USR-02 | The system SHALL allow the Admin to retrieve a list of all users. | Admin | SHALL |
| M1-USR-03 | The system SHALL allow the Admin to retrieve a single user by their ID, including their department, level, tier, and roles. | Admin | SHALL |
| M1-USR-04 | The system SHALL allow the Admin to update any field of an existing user. | Admin | SHALL |
| M1-USR-05 | The system SHALL allow the Admin to delete a user account. | Admin | SHALL |
| M1-USR-06 | When a user's level is assigned or changed, the system SHALL automatically synchronize that user's Spatie role to the level code (e.g., assigning level L2 sets the user's role to "L2"). | System | SHALL |
| M1-USR-07 | The system SHALL allow the Admin to mark any user account as an admin (`is_admin = true`), granting them full administrative access. | Admin | SHALL |

---

### 3.1.6  Role & Permission Management

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M1-ROLE-01 | The system SHALL allow the Admin to create a new role with a name. | Admin | SHALL |
| M1-ROLE-02 | The system SHALL allow the Admin to retrieve a list of all roles. | Admin | SHALL |
| M1-ROLE-03 | The system SHALL allow the Admin to retrieve a single role by its ID. | Admin | SHALL |
| M1-ROLE-04 | The system SHALL allow the Admin to update the name of a role. | Admin | SHALL |
| M1-ROLE-05 | The system SHALL allow the Admin to delete a role. | Admin | SHALL |
| M1-ROLE-06 | The system SHALL allow the Admin to assign or synchronize permissions to a role in a single operation (sync replaces the existing permission set). | Admin | SHALL |
| M1-PERM-01 | The system SHALL allow the Admin to create a new permission with a name. | Admin | SHALL |
| M1-PERM-02 | The system SHALL allow the Admin to retrieve a list of all permissions. | Admin | SHALL |
| M1-PERM-03 | The system SHALL allow the Admin to retrieve a single permission by its ID. | Admin | SHALL |
| M1-PERM-04 | The system SHALL allow the Admin to update the name of a permission. | Admin | SHALL |
| M1-PERM-05 | The system SHALL allow the Admin to delete a permission. | Admin | SHALL |

---

## Module 2 — Manager Schedule System (M2)

> **Implementation status:** ⬜ Pending development.
> Source: Requirements Confirmation — Module 1, v2.0, 25 March 2026.

### 3.2.1  Schedule Grid

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M2-GRID-01 | The system SHALL display a weekly schedule grid with employees of the manager's own department as rows and Monday through Sunday as columns. | Manager | SHALL |
| M2-GRID-02 | The system SHALL highlight today's column in a distinct color so the manager can immediately identify the current day. | System | SHALL |
| M2-GRID-03 | Empty cells (no shift assigned) SHALL be displayed as a dashed placeholder with a visible affordance (e.g., a "+" sign) to indicate the cell is ready to be assigned. | System | SHALL |
| M2-GRID-04 | Filled cells SHALL display the shift name, working hours, and a color corresponding to the shift type (working shift = green; Day Off = orange; Sick Day = red; Leave Request = purple). | System | SHALL |
| M2-GRID-05 | A cover shift cell SHALL display the name of the employee being covered directly on the cell face (e.g., "COVERING Omar — Shift B"). | System | SHALL |
| M2-GRID-06 | A summary bar at the top of the grid SHALL show total counts for the week: number of working assignments, Day Off, Sick Day, Leave Request, and modified shifts. | System | SHALL |
| M2-GRID-07 | A color legend at the bottom of the grid SHALL explain every color code used on the grid. | System | SHALL |
| M2-GRID-08 | The manager SHALL be able to navigate forward and backward between weeks using arrow buttons. The current week's date range SHALL be shown clearly at the top. | Manager | SHALL |
| M2-GRID-09 | A manager SHALL only be able to view and modify the schedule for their own department. Employees from other departments SHALL NOT be visible or accessible. | System | SHALL |
| M2-GRID-10 | The system SHALL allow multiple managers assigned to the same department to each view and edit the schedule for that department. | System | SHALL |

---

### 3.2.2  Shift Assignment

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M2-ASGN-01 | When a manager clicks an empty cell, the system SHALL open the shift assignment form pre-filled with the employee name, their level and tier, and the selected day. | Manager | SHALL |
| M2-ASGN-02 | When a manager clicks a filled cell, the system SHALL open the edit form showing the current shift and the full change history for that cell below the form. | Manager | SHALL |
| M2-ASGN-03 | The assignment form SHALL present all available company-wide shifts as selectable cards, each showing the shift name and working hours. | System | SHALL |
| M2-ASGN-04 | The assignment form SHALL present three off type options alongside the shift cards: Day Off (orange), Sick Day (red), and Leave Request (purple). Off type options SHALL NOT display working hours. | System | SHALL |
| M2-ASGN-05 | A manager SHALL be able to clear an assigned shift, returning the cell to an empty/unassigned state. An empty cell is valid only as a working state; the system requires all seven days to have an assignment before publishing. | Manager | SHALL |
| M2-ASGN-06 | The assignment form SHALL include an optional "Cover Shift" checkbox. When checked, two dropdowns SHALL appear: "This will cover..." (which shift slot is being covered) and "From whom" (which registered employee in the system is being covered). The covered employee reference SHALL be a link to an actual registered user account, not a free-text name. | Manager | SHALL |
| M2-ASGN-07 | The assignment form SHALL include an optional free-text comment field. Comments SHALL be saved permanently with the change record and SHALL be visible in the cell's change history. | Manager | SHALL |
| M2-ASGN-08 | Every change to a shift assignment SHALL be logged with: the acting user's name, a timestamp, the previous value, the new value, and any comment provided. | System | SHALL |
| M2-ASGN-09 | The change history log SHALL NOT be editable or deletable by any user. | System | SHALL |
| M2-ASGN-10 | Cells that have been modified after their initial assignment SHALL display a visible "Updated" indicator (e.g., a small tag in the corner of the cell). | System | SHALL |

---

### 3.2.3  Schedule Publishing

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M2-PUB-01 | A schedule SHALL exist in one of two states: **Draft** or **Published**. | System | SHALL |
| M2-PUB-02 | The manager SHALL be able to click a "Publish Schedule" button to transition the current week's schedule from Draft to Published. | Manager | SHALL |
| M2-PUB-03 | Only a Published schedule SHALL be visible to the Compliance team in Module 3 (M3). Draft schedules SHALL NOT appear in the Audit module. | System | SHALL |
| M2-PUB-04 | After publishing, the manager SHALL still be able to edit individual shift cells. Each edit SHALL be recorded in the change history. | Manager | SHALL |
| M2-PUB-05 | The system SHALL NOT allow publishing a schedule until all seven days of the week have a non-empty assignment for every employee in the department. | System | SHALL |

---

### 3.2.4  Bulk Assignment

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M2-BULK-01 | The system SHALL provide a bulk assignment mode that allows the manager to assign one shift to multiple employees (rows) for a single selected day in one action. | Manager | SHALL |
| M2-BULK-02 | The system SHALL provide a bulk assignment mode that allows the manager to assign one shift to a single employee for multiple selected days (columns) in one action. | Manager | SHALL |
| M2-BULK-03 | In bulk mode, checkboxes SHALL appear on each employee row or each day column header for multi-selection. | System | SHALL |
| M2-BULK-04 | When bulk mode is active, an informational hint panel SHALL be visible on screen explaining the current mode and guiding the manager through the steps. | System | SHALL |

---

### 3.2.5  Copy Previous Week

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M2-COPY-01 | The system SHALL provide a "Copy Last Week" button that duplicates all shift assignments from the previous week into the current week. | Manager | SHALL |
| M2-COPY-02 | Before copying, the system SHALL display a confirmation dialog to prevent accidental overwrites. | System | SHALL |
| M2-COPY-03 | After copying, the manager SHALL be able to edit any individual cell in the copied schedule before publishing. | Manager | SHALL |

---

### 3.2.6  Filters and Views

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M2-FILT-01 | The manager SHALL be able to filter the schedule grid to show only a specific assignment type: Working, Day Off, Sick Day, Leave Request, or Modified. | Manager | SHALL |
| M2-FILT-02 | Clicking a day column header SHALL open a Day Detail View showing a full list of all employees for that day, with each employee's shift name, hours, status badge, and cover details if applicable. | Manager | SHALL |
| M2-FILT-03 | The Day Detail View SHALL include a search box to filter employees by name. | Manager | SHALL |
| M2-FILT-04 | The Day Detail View SHALL include a summary bar showing counts of Working, Day Off, Sick Day, and Leave Request for that day. | System | SHALL |
| M2-FILT-05 | Each row in the Day Detail View SHALL include an Edit button that opens the shift assignment form for that employee directly. | Manager | SHALL |

---

### 3.2.7  Automated Email Reminders

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M2-EMAIL-01 | Every Monday at 12:00 PM, the system SHALL automatically send a reminder email to every manager in the system prompting them to create the current week's schedule. | System | SHALL |
| M2-EMAIL-02 | Every Tuesday at 12:00 PM, if a manager's department schedule has not yet been created for the current week, the system SHALL send a second reminder email to that manager. | System | SHALL |
| M2-EMAIL-03 | On the same Tuesday trigger (12:00 PM), if the schedule still does not exist, the system SHALL automatically copy the previous week's schedule into the current week for that department. | System | SHALL |
| M2-EMAIL-04 | No further automated reminders or auto-copies SHALL be triggered after Tuesday for the same week. | System | SHALL |

---

### 3.2.8  Export

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M2-EXP-01 | The system SHALL allow the manager to export the current week's schedule as a CSV file. The file SHALL include: employee name, and the assigned shift name and hours for each day (Monday through Sunday). | Manager | SHALL |

---

## Module 3 — Audit & Compliance System (M3)

> **Implementation status:** ⬜ Pending development.
> Source: Requirements Confirmation — Module 2 (Audit & Compliance), v2.0, 26 March 2026.

### 3.3.1  Audit Grid

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M3-GRID-01 | The system SHALL display a weekly audit grid with employees as rows and Monday through Sunday as columns. Each cell SHALL show the employee's computed attendance status for that day. | Compliance | SHALL |
| M3-GRID-02 | Cells SHALL be color-coded according to attendance status: Green = On Time, Orange = Late, Red = Absent, Purple = Left Early, dual color (Orange + Purple) = Late AND Left Early. | System | SHALL |
| M3-GRID-03 | Today's column SHALL be highlighted in a distinct color (purple) across the entire grid. | System | SHALL |
| M3-GRID-04 | Live summary count cards SHALL be displayed above the grid showing totals for: On Time, Late, Absent, and Left Early. These counts SHALL update instantly when filters are applied. | System | SHALL |
| M3-GRID-05 | The Compliance team member SHALL be able to navigate between weeks using left and right arrow buttons. | Compliance | SHALL |
| M3-GRID-06 | Cells on which the scheduled shift has been modified after the initial assignment SHALL display a small "Updated" badge in the corner. | System | SHALL |

---

### 3.3.2  Attendance Status Logic

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M3-STAT-01 | The system SHALL compute attendance status for each employee per day by comparing the published scheduled shift against the imported fingerprint clock-in/clock-out data. | System | SHALL |
| M3-STAT-02 | **On Time:** An employee SHALL be marked On Time if their clock-in time is within 5 minutes after the scheduled shift start time AND their clock-out time is at or after the scheduled shift end time. | System | SHALL |
| M3-STAT-03 | **Late:** An employee SHALL be marked Late if their clock-in time is more than 5 minutes after the scheduled shift start time. | System | SHALL |
| M3-STAT-04 | **Left Early (standard):** An employee SHALL be marked Left Early if their clock-out time occurs after they have completed 60% or more of their scheduled shift duration but before the scheduled end time. This case SHALL be displayed in purple. | System | SHALL |
| M3-STAT-05 | **Left Early (early exit):** An employee SHALL be marked as a distinct "early exit" status if their clock-out time occurs before they have completed 60% of their scheduled shift duration. This case SHALL be displayed in a different color from standard Left Early to visually distinguish the severity. | System | SHALL |
| M3-STAT-06 | **Combined — Late + Left Early:** If an employee both arrived late (more than 5 minutes) AND left early (either threshold), the cell SHALL display BOTH statuses simultaneously (dual color: orange + purple). The cell detail SHALL show the exact number of minutes late and exact number of minutes left early. | System | SHALL |
| M3-STAT-07 | **Absent:** An employee SHALL be marked Absent if they have a scheduled working shift for that day but NO fingerprint record (no clock-in or clock-out) exists for that employee on that day. | System | SHALL |
| M3-STAT-08 | If an employee is assigned a Day Off, Sick Day, or Leave Request for a given day, that day SHALL NOT be evaluated for attendance status. It SHALL display the off type label instead of a status color. | System | SHALL |
| M3-STAT-09 | If no schedule has been published for the week, the audit grid SHALL display a notice indicating there is no published schedule to audit. | System | SHALL |

---

### 3.3.3  Fingerprint Data Import

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M3-IMP-01 | The system SHALL allow a Compliance team member to upload fingerprint attendance data via a CSV or Excel (.xlsx) file. | Compliance | SHALL |
| M3-IMP-02 | Upon upload, the system SHALL parse the file and store the clock-in and clock-out records linked to the corresponding employee and date. | System | SHALL |
| M3-IMP-03 | The system SHALL validate the uploaded file format and SHALL reject uploads that do not match the expected column structure, displaying a clear error message. | System | SHALL |
| M3-IMP-04 | After a successful import, the audit grid SHALL automatically recompute all attendance statuses for the affected week. | System | SHALL |
| M3-IMP-05 | Fingerprint data import is one-directional: into the system only. The system SHALL NOT push any data back to external attendance devices or systems. | System | SHALL |

---

### 3.3.4  Cell Detail Modal

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M3-MODAL-01 | Clicking any cell in the audit grid SHALL open a detail modal for that employee on that day. | Compliance | SHALL |
| M3-MODAL-02 | The modal SHALL display: employee name and level, planned shift name and times, actual fingerprint clock-in and clock-out times, total hours worked, computed attendance status, cover assignment details (if applicable), and the full shift change history. | System | SHALL |
| M3-MODAL-03 | The modal's change history section SHALL be read-only. No user can edit or delete history entries through the modal. | System | SHALL |

---

### 3.3.5  Overnight Shift Support

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M3-OVER-01 | The system SHALL detect shifts that cross midnight (i.e., end time is earlier than start time on the 24-hour clock). | System | SHALL |
| M3-OVER-02 | Overnight shifts SHALL be displayed as a visual Gantt-style bar spanning two day columns in the audit grid, with a 🌙 overnight label. | System | SHALL |
| M3-OVER-03 | Overnight shift attendance status SHALL be computed correctly using the total shift duration across both calendar days. | System | SHALL |

---

### 3.3.6  Filters and Search

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M3-FILT-01 | The Compliance team member SHALL be able to filter the audit grid by department. | Compliance | SHALL |
| M3-FILT-02 | The Compliance team member SHALL be able to filter the audit grid by attendance status: All / On Time / Late / Absent / Left Early. When a filter is active, non-matching rows SHALL appear visually faded (not hidden). | Compliance | SHALL |
| M3-FILT-03 | The Compliance team member SHALL be able to search for an employee by name, with the grid updating instantly as text is typed. | Compliance | SHALL |

---

### 3.3.7  Shift Management (Compliance Admin)

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M3-SHFT-01 | The system SHALL provide a Shifts Management tab listing all defined company shifts with: shift name, start time, end time, and auto-calculated duration. | Compliance | SHALL |
| M3-SHFT-02 | Shifts that cross midnight SHALL be automatically flagged with a 🌙 overnight label in the shifts table. | System | SHALL |
| M3-SHFT-03 | A Compliance team member SHALL be able to add a new shift by providing: shift name, start time, and end time. | Compliance | SHALL |
| M3-SHFT-04 | A Compliance team member SHALL be able to edit the name, start time, and end time of an existing shift. | Compliance | SHALL |
| M3-SHFT-05 | A Compliance team member SHALL be able to delete a shift. A confirmation dialog SHALL be shown before deletion is executed. | Compliance | SHALL |
| M3-SHFT-06 | Shifts are company-wide. The same shift definitions apply to all departments and are managed exclusively by the Compliance team. | System | SHALL |

---

### 3.3.8  Manager Access Restriction

| ID | Requirement | Actor | Priority |
|---|---|---|---|
| M3-MGR-01 | Managers SHALL NOT have access to the Audit & Compliance module. The audit grid, shift management, fingerprint import, and all M3 screens are restricted to the Compliance team only. | System | SHALL |

---

## Summary Table

| Module | Category | Total Requirements |
|---|---|---|
| M1 — Core Admin & Hierarchy | AUTH, DEPT, LVL, TIER, USR, ROLE, PERM | 32 |
| M2 — Manager Schedule | GRID, ASGN, PUB, BULK, COPY, FILT, EMAIL, EXP | 31 |
| M3 — Audit & Compliance | GRID, STAT, IMP, MODAL, OVER, FILT, SHFT, MGR | 33 |
| **Total** | | **96** |

---

*— End of Section 3 — Continue to Section 4: Non-Functional Requirements —*

---

**CONFIDENTIAL — NVT Internal Use Only**
