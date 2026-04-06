---
**SOFTWARE REQUIREMENTS SPECIFICATION**
**Employee Schedule Management System**

| | |
|---|---|
| **Organization** | NVT — New Vision Team |
| **Document type** | Software Requirements Specification (SRS) |
| **Current section** | Section 1 — Introduction |
| **Document status** | Draft — In progress (9 sections total) |
| **Version** | 1.0 |
| **Prepared by** | NVT Development Team |
| **Date** | 31 March 2026 |
| **Language** | English |
| **Backend** | Laravel 13 / PHP 8.3 / MySQL |
| **Frontend** | React |

---

**SRS Progress — Section 1 of 9**

| | |
|---|---|
| ✅ Section 1: Introduction *(this document)* | ⬜ Section 2: Overall Description |
| ⬜ Section 3: Functional Requirements | ⬜ Section 4: Non-Functional Requirements |
| ⬜ Section 5: System Users & Roles | ⬜ Section 6: Use Cases |
| ⬜ Section 7: Technical Requirements | ⬜ Section 8: Constraints & Assumptions |
| ⬜ Section 9: Final Assembly & Appendix | |

---

## 1.1  Purpose

This document is the Software Requirements Specification (SRS) for the **Employee Schedule Management System**, developed by NVT — New Vision Team. It provides a complete and formal description of the system's intended functionality, behavior, constraints, and technical requirements.

The purpose of this SRS is to serve as the single authoritative reference for the entire project. It establishes a shared understanding between the development team and all stakeholders about exactly what the system will do and how it will do it. It is the baseline against which all development work, testing, and delivery will be measured.

This document follows the structure recommended by IEEE Std 830-1998. It is completed in 9 sections. This file contains **Section 1: Introduction**.

---

## 1.2  Document Scope

The Employee Schedule Management System is an internal workforce management platform built exclusively for NVT — New Vision Team. It is designed to replace fragmented manual processes — such as spreadsheets, paper-based schedules, and informal attendance tracking — with a single centralized digital platform accessible through a web browser.


The system consists of three modules:

| Module | Name | Summary | Status |
|--------|------|---------|--------|
| **M1** | Core Admin & Hierarchy | Manage the full organizational structure: departments, user levels, tiers, users, roles, and permissions. Provides the foundational data layer that all other modules depend on. | ✅ Implemented |
| **M2** | Manager Schedule System | Allow managers (Level 2 and above) to build, edit, and publish weekly work schedules for employees in their department. Includes shift assignment, bulk operations, copy-last-week, change history, automated reminders, and CSV export. | ⬜ Pending Development |
| **M3** | Audit & Compliance System | Provide the Compliance team with full visibility into schedule adherence across all departments. Compares planned schedules against actual fingerprint attendance data. Includes an audit grid, status logic, shift management, filters, and summary cards. | ⬜ Pending Development |

### 1.2.1  What Is Outside the Scope of This System

The following items are explicitly outside the scope of the Employee Schedule Management System and will not be built as part of this project:

- Payroll processing or salary calculation of any kind
- Leave request submission by employees themselves
- Direct integration with biometric or RFID attendance devices (fingerprint data is expected as an external import)
- Integration with any external HR, ERP, or payroll systems
- Public-facing portals or features accessible by anyone outside NVT
- Mobile application (not included in the current scope)
- Legal or court-ready documentation
- Automated disciplinary workflows beyond what is described in the module scope documents

---

## 1.3  Intended Audience and Reading Guide

This document is intended for the following audiences. Each group uses it differently:

| Intended Audience | How They Use This Document |
|---|---|
| **Project Manager** | Approve scope, track requirements, manage delivery milestones. |
| **Development Team** | Understand exactly what to build. Use as the primary technical reference throughout all implementation stages. |
| **QA / Testing Team** | Derive test cases from functional requirements. Verify that the built system matches what is specified. |
| **Client (NVT Management)** | Review, confirm, and sign off that the system described matches expectations. |
| **New Team Members** | Onboard quickly by reading this document to understand the full system context before touching any code. |

### 1.3.1  How to Read This Document

The SRS is divided into 9 sections. Each section builds on the previous one. The recommended reading order depends on your role:

- **Project managers:** Start with Sections 1, 2, 5, 8.
- **Developers:** Read all sections. Focus on Sections 3, 4, 7.
- **Testers:** Focus on Sections 3, 4, 6.
- **Client and stakeholders:** Read Sections 1, 2, 3, 5.

---

## 1.4  Product Overview

The Employee Schedule Management System is a web-based workforce management platform built exclusively for internal use by NVT — New Vision Team. It is not a commercial product and will not be sold or licensed to third parties. It is purpose-built for NVT's organizational structure, department hierarchy, management workflows, and compliance requirements.

The system addresses four core operational problems that NVT currently faces:

**1. Hierarchy and access management.**
With employees distributed across multiple departments and levels, there is currently no centralized system to manage the organizational structure. The Core Admin module solves this with a full hierarchy management layer covering departments, user levels, tiers, roles, and permissions.

**2. Schedule visibility and accountability.**
Managers currently have no efficient tool for building, editing, and communicating weekly work schedules. Changes are undocumented and there is no audit trail of who changed what and why. The Manager Schedule module solves this with a structured weekly grid, change history, and a formal publish workflow.

**3. Attendance compliance.**
There is currently no systematic way to compare what was planned in the schedule against what actually happened in attendance. The Audit & Compliance module solves this by importing fingerprint data and comparing it against the published schedule, with color-coded status logic and summary reporting.

**4. Role-based access control.**
Different user types (Admin, Manager, Compliance Team) need different levels of access. The system enforces strict role-based access so each user sees and can act on only what their role permits.

---

## 1.5  Definitions, Acronyms, and Abbreviations

The following terms are used throughout this document. All readers should be familiar with these definitions before reading further sections.

| Term / Abbreviation | Definition |
|---|---|
| **SRS** | Software Requirements Specification. This document. |
| **NVT** | New Vision Team. The company that owns and will use this system. |
| **Laravel** | A PHP-based backend web framework used to build the server-side API. |
| **React** | A JavaScript library used to build the web-based frontend interface. |
| **REST API** | Representational State Transfer Application Programming Interface. The communication layer between the frontend (React) and the backend (Laravel). |
| **MySQL** | The relational database management system used to store all system data. |
| **Sanctum** | Laravel Sanctum. The authentication package used for stateless API token management. |
| **Spatie** | Spatie Laravel Permission. The package used to manage roles and permissions per user. |
| **Module** | A self-contained feature set within the system. The system has three modules: M1, M2, M3. |
| **Admin** | A user with full system access. Can manage all departments, users, levels, roles, and permissions. |
| **Manager** | A user at Level 2 or above who can manage the weekly schedule for employees within their department. |
| **Compliance Team** | A dedicated role with full access to the audit grid, shift management, and compliance reporting. |
| **HR** | Human Resources team. Has read-only access to audit summaries. |
| **Department** | An organizational unit within NVT. Departments can be nested using a parent-child hierarchy. |
| **User Level** | A hierarchy rank assigned to a user (e.g. L1, L2, L3 up to L6). Determines the user's position in the organization. |
| **Tier** | A sub-classification within a user level (e.g. Tier 1, Tier 2 within L2). Optional, used to further segment employees. |
| **Role** | A named set of permissions assigned to a user. Roles are tied to user levels and automatically synced when a user's level changes. |
| **Permission** | A named rule that grants access to a specific action in the system (e.g. `manage_own_department_schedule`). |
| **Shift** | A defined block of working hours with a name, start time, and end time (e.g. Shift A: 06:00–14:00). |
| **Schedule** | A weekly assignment of shifts to employees, built by a manager for their department. |
| **Published Schedule** | A schedule that has been formally submitted by the manager and made visible to the Audit & Compliance team. |
| **Cover Shift** | A shift assignment where one employee covers another employee's planned shift slot. |
| **Change History** | A permanent, immutable log of every change made to a shift assignment, including who changed it, when, and any comment provided. |
| **Fingerprint Data** | Actual clock-in and clock-out times recorded by the company's attendance system and imported into M3 for comparison. |
| **Audit Grid** | The visual weekly table in Module 3 that shows each employee's attendance status compared to their planned schedule. |
| **Attendance Status** | The computed result of comparing planned shift times to actual fingerprint times. One of: On Time, Late, Absent, Left Early, or Late + Left Early. |
| **Overnight Shift** | A shift whose end time crosses midnight (e.g. 22:00 → 06:00). Requires special rendering in the audit grid. |
| **Bulk Assignment** | A feature in M2 that lets a manager assign one shift to multiple employees or multiple days in a single action. |
| **CRUD** | Create, Read, Update, Delete. The four basic operations performed on database records. |
| **JSON** | JavaScript Object Notation. The data format used for all API communication between frontend and backend. |
| **API** | Application Programming Interface. Specifically the REST API built in Laravel that the React frontend communicates with. |
| **UI** | User Interface. The screens and controls the user interacts with. |
| **Audit Trail** | A permanent log of every change made in the system: who did it, when, and what changed. |
| **TBD** | To Be Determined. Used for items not yet decided at the time of writing. |
| **SHALL** | A mandatory requirement. The system must do this without exception. |
| **SHOULD** | A recommended requirement. The system is expected to do this unless there is a strong reason not to. |
| **MAY** | An optional requirement. The system can do this if feasible. |

---

## 1.6  References

The following internal documents were used as input when writing this SRS:

| Ref ID | Document Title | Version | Date |
|--------|---------------|---------|------|
| [REF-1] | Requirements Confirmation — Module 1: Manager Schedule System | v2.0 | 25 March 2026 |
| [REF-2] | Requirements Confirmation — Module 2: Audit & Compliance System | v2.0 | 26 March 2026 |
| [REF-3] | IEEE Std 830-1998 — Recommended Practice for Software Requirements Specifications | — | — |

---

## 1.7  Document Conventions

The following conventions are used consistently throughout this SRS:

| Convention | Meaning |
|---|---|
| **SHALL** | A mandatory requirement. The system must do this without exception. |
| **SHOULD** | A recommended requirement. The system is expected to do this unless there is a strong reason not to. |
| **MAY** | An optional requirement. The system can do this if feasible. |
| **TBD** | To Be Determined. This item is acknowledged but not yet finalized. |
| **[REF-N]** | A reference to a document listed in Section 1.6. |
| **M1, M2, M3** | Shorthand for Module 1, Module 2, Module 3 respectively. |
| ✅ | Feature or section that is already implemented and tested. |
| ⬜ | Feature or section that is approved and pending development. |

---

## 1.8  Document Version History

All changes to this document are recorded below. Future sessions will add new rows as sections are updated.

| Version | Date | Author | Description |
|---------|------|--------|-------------|
| 1.0 | 31 March 2026 | NVT Development Team | Initial draft — Section 1: Introduction |

---

*— End of Section 1 — Continue to Section 2: Overall Description —*

---

**CONFIDENTIAL — NVT Internal Use Only**
