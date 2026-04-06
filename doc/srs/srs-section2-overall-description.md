---
**SOFTWARE REQUIREMENTS SPECIFICATION**
**Employee Schedule Management System**

| | |
|---|---|
| **Organization** | NVT — New Vision Team |
| **Document type** | Software Requirements Specification (SRS) |
| **Current section** | Section 2 — Overall Description |
| **Document status** | Draft — In progress (9 sections total) |
| **Version** | 1.0 |
| **Prepared by** | NVT Development Team |
| **Date** | 31 March 2026 |

---

**SRS Progress — Section 2 of 9**

| | |
|---|---|
| ✅ Section 1: Introduction | ✅ Section 2: Overall Description *(this document)* |
| ⬜ Section 3: Functional Requirements | ⬜ Section 4: Non-Functional Requirements |
| ⬜ Section 5: System Users & Roles | ⬜ Section 6: Use Cases |
| ⬜ Section 7: Technical Requirements | ⬜ Section 8: Constraints & Assumptions |
| ⬜ Section 9: Final Assembly & Appendix | |

---

## 2.1  Product Perspective

The Employee Schedule Management System is a new, standalone internal platform. It does not replace or extend any existing commercial software. It is built from scratch to solve organizational pain points specific to NVT — New Vision Team.

The system operates as a client-server web application:

- The **backend** is a REST API built with Laravel 13 on PHP 8.3, backed by a MySQL database.
- The **frontend** is a React web application that communicates exclusively with the backend API.
- All data exchange between frontend and backend uses JSON over HTTPS.
- Authentication is handled via Laravel Sanctum using stateless bearer tokens.
- Role and permission enforcement is handled via Spatie Laravel Permission.

The system has no dependency on any external HR, payroll, or ERP system. The only external data input is fingerprint attendance data, which is manually uploaded by the Compliance team in CSV or Excel format through the Audit & Compliance module.

---

## 2.2  Product Functions — High-Level Summary

The system provides three distinct functional areas, each packaged as a module:

### M1 — Core Admin & Hierarchy
The foundation of the entire system. The Admin manages the full organizational structure before any other module can operate. This module is fully implemented as of the date of this document.

Key capabilities:
- Manage departments with unlimited nesting depth (parent-child hierarchy)
- Manage user levels (L1 through L6) and sub-tiers within each level
- Create and manage user accounts with automatic role assignment based on level
- Define roles and permissions and assign them to users
- Full admin-only access control protecting all management operations

### M2 — Manager Schedule System
Allows managers (L2 and above) to build and manage the weekly work schedule for all employees within their own department.

Key capabilities:
- Weekly schedule grid (Monday to Sunday) showing every employee as a row
- Assign one of the defined company shifts or an off type (Day Off, Sick Day, Leave Request) to each cell
- Mark shifts as cover shifts with a reference to the employee being covered
- Add comments and view full change history per shift cell
- Bulk assign the same shift to multiple employees or multiple days at once
- Copy the previous week's schedule as a starting point
- Publish the completed schedule to make it visible to the Compliance team
- Automated email reminders on Monday and Tuesday if the schedule has not been created
- Export the weekly schedule as a CSV file

### M3 — Audit & Compliance System
Provides the Compliance team with complete visibility into schedule adherence. Compares what was planned in M2 against actual attendance records imported from the fingerprint system.

Key capabilities:
- Weekly audit grid showing every employee's attendance status per day
- Attendance status computed by comparing planned shift times against actual clock-in/clock-out data
- Four status outcomes: On Time, Late, Absent, Left Early — with a combined Late + Left Early case
- Fingerprint data imported via CSV or Excel file upload
- Full shift management (add, edit, delete company shift definitions)
- Overnight shift support — shifts crossing midnight rendered across two day columns
- Filters by department, status, and search by employee name
- Summary count cards: On Time / Late / Absent / Left Early
- Managers have read-only access to the audit grid
- Only Compliance team members can manage shifts and upload attendance data

---

## 2.3  Organizational Context

NVT employs **210+ employees** across more than **10 departments**, with departments organized in a nested hierarchy of multiple levels. The system must accommodate this scale and the full nesting depth without restriction.

### 2.3.1  Department Hierarchy

Departments are organized as a tree. A department can have one parent and multiple children. There is no fixed limit on depth. The hierarchy is managed exclusively by the Admin through M1.

Example structure (illustrative only — not the actual NVT org chart):

```
Head Office
├── Operations
│   ├── Dispatch Team A
│   └── Dispatch Team B
├── Customer Support
│   ├── CS Morning Team
│   └── CS Evening Team
└── Administration
    ├── HR Department
    └── Finance
```

Each department can have users assigned to it. A user belongs to exactly one department at a time.

### 2.3.2  User Level Hierarchy

NVT uses a six-level employee hierarchy (L1 through L6). The hierarchy defines managerial authority:

| Level | Code | Authority |
|-------|------|-----------|
| L1 | L1 | Individual contributor — no management authority |
| L2 | L2 | Manages L1 employees in their department |
| L3 | L3 | Manages L2 and L1 employees in their department |
| L4 | L4 | Manages L3, L2, and L1 employees in their department |
| L5 | L5 | Manages L4 and below in their department |
| L6 | L6 | Manages L5 and below in their department |

A manager's schedule-building access covers **all employees in their own department**, regardless of those employees' levels. A manager does not have access to any other department.

Multiple managers can share the same department. Each manager in a shared department can build and edit schedules for all employees in that department.

Additionally, L2PM is a parallel level at the same hierarchy rank as L2, representing Project Managers. They follow the same management rules as L2.

---

## 2.4  User Classes and Characteristics

The system serves three distinct user classes. Each class has a different interface, a different set of permitted actions, and a different relationship with the data.

| User Class | Level | Interface | Primary Activity |
|---|---|---|---|
| **Admin** | Any user with `is_admin = true` | Full system — all modules | Manage hierarchy, users, roles, permissions |
| **Manager** | L2 and above | M2 Schedule Grid | Build and publish weekly schedules for their department |
| **Compliance Team** | Designated role | M3 Audit Grid + Shift Management | Review attendance compliance, manage shifts, upload fingerprint data |

Detailed access rules for each user class are fully specified in **Section 5 — System Users & Roles**.

---

## 2.5  Operating Environment

The system operates in the following environment:

| Component | Technology |
|---|---|
| Backend framework | Laravel 13 |
| Backend language | PHP 8.3 |
| Database | MySQL |
| Frontend framework | React |
| Authentication | Laravel Sanctum (bearer token) |
| Authorization | Spatie Laravel Permission |
| API format | REST / JSON |
| Web server | Apache (XAMPP on development, production TBD) |
| Deployment environment | Internal server, web browser access |
| Mobile application | Not in current scope |

The system is accessed through a standard web browser on a desktop or laptop computer. No mobile application is included in the current scope.

---

## 2.6  Module Dependency and Data Flow

The three modules are not independent. They share a common data layer and have a strict operational dependency:

```
M1 (Core Admin & Hierarchy)
        │
        │  provides: users, departments, levels, roles
        ▼
M2 (Manager Schedule)
        │
        │  provides: published weekly schedules
        ▼
M3 (Audit & Compliance)
        │
        │  receives: fingerprint CSV/Excel upload
        │  computes: attendance status per employee per day
```

**M1 must be configured first.** Departments, users, and levels must exist before a manager can build a schedule.

**M2 must produce a published schedule** before M3 can perform meaningful audit comparison. Unpublished schedules are not visible to the Compliance team.

**M3 requires both** a published schedule from M2 and imported fingerprint data to compute attendance status.

---

## 2.7  Assumptions and Dependencies

The following assumptions are made and must remain valid for the system to function as specified. Any change to these assumptions may require a revision to this SRS.

| # | Assumption |
|---|---|
| A1 | The Admin will fully configure M1 (departments, users, levels) before managers begin using M2. |
| A2 | The work week runs from **Monday to Sunday**. Week boundaries are based on this definition throughout all three modules. |
| A3 | Fingerprint attendance data is available as a structured **CSV or Excel file** and is uploaded manually into M3 by the Compliance team. No direct device integration is required. |
| A4 | A manager can only build and view schedules for **their own department**. They cannot access schedules of other departments. |
| A5 | Multiple managers may be assigned to the same department and may each build or edit the schedule for that department. |
| A6 | A user belongs to exactly **one department** at a time. |
| A7 | A user has exactly **one user level** and optionally one tier. Their Spatie role is automatically synchronized to their level code when their level is set or changed. |
| A8 | Shifts are company-wide. The same set of shifts applies across all departments. Shift definitions are managed exclusively in M3 by the Compliance team. |
| A9 | A published schedule is immutable from the Audit perspective — the Compliance team sees exactly what was published. Edits after publishing are allowed but tracked in the change history. |
| A10 | Emails are sent using the Laravel mail system via a configured SMTP provider. Email configuration is handled at the environment level and is not part of the SRS scope. |

---

## 2.8  Constraints

| # | Constraint |
|---|---|
| C1 | The system is internal only. No public registration, no external access. |
| C2 | All authentication is token-based via Laravel Sanctum. Sessions are stateless. |
| C3 | All admin operations (hierarchy management) are protected by the `is_admin` flag. |
| C4 | All manager operations are protected by role-based permission checks (`manage_own_department_schedule`). |
| C5 | All compliance operations are protected by role-based permission checks (`view_all_schedules` and shift management permissions). |
| C6 | The change history log for shift assignments SHALL NOT be editable or deletable by any user class. |
| C7 | Fingerprint data import is one-directional: uploaded into the system only. The system does not push data back to any attendance device or external system. |

---

*— End of Section 2 — Continue to Section 3: Functional Requirements —*

---

**CONFIDENTIAL — NVT Internal Use Only**
