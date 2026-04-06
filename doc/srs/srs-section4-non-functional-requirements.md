---
**SOFTWARE REQUIREMENTS SPECIFICATION**
**Employee Schedule Management System**

| | |
|---|---|
| **Organization** | NVT — New Vision Team |
| **Document type** | Software Requirements Specification (SRS) |
| **Current section** | Section 4 — Non-Functional Requirements |
| **Document status** | Draft — In progress (9 sections total) |
| **Version** | 1.0 |
| **Prepared by** | NVT Development Team |
| **Date** | 31 March 2026 |

---

**SRS Progress — Section 4 of 9**

| | |
|---|---|
| ✅ Section 1: Introduction | ✅ Section 2: Overall Description |
| ✅ Section 3: Functional Requirements | ✅ Section 4: Non-Functional Requirements *(this document)* |
| ⬜ Section 5: System Users & Roles | ⬜ Section 6: Use Cases |
| ⬜ Section 7: Technical Requirements | ⬜ Section 8: Constraints & Assumptions |
| ⬜ Section 9: Final Assembly & Appendix | |

---

## Overview

This section defines the non-functional requirements (NFRs) for the Employee Schedule Management System. These requirements specify the quality attributes and operational constraints the system must satisfy. They are not tied to specific features but apply globally across all three modules.

**Requirement ID format:** `NFR-[Category]-[Number]`

---

## 4.1  Performance

| ID | Requirement | Priority |
|---|---|---|
| NFR-PERF-01 | The system SHALL support a minimum of **30 concurrent authenticated users** without performance degradation. | SHALL |
| NFR-PERF-02 | All API responses to standard read requests (e.g., load schedule grid, load audit grid) SHALL complete within **3 seconds** under normal load conditions (up to 30 concurrent users). | SHALL |
| NFR-PERF-03 | All API responses to write requests (e.g., assign shift, publish schedule, import fingerprint file) SHALL complete within **5 seconds** under normal load conditions. | SHALL |
| NFR-PERF-04 | The schedule grid for a department of up to 210 employees across a full 7-day week SHALL load and render completely within the 3-second response window. | SHALL |
| NFR-PERF-05 | Fingerprint file import processing (CSV or Excel, up to a full week of records for all employees) SHALL complete within **10 seconds** after upload. | SHALL |

---

## 4.2  Security

| ID | Requirement | Priority |
|---|---|---|
| NFR-SEC-01 | All API communication SHALL occur over HTTPS. Plain HTTP requests SHALL be rejected or redirected. | SHALL |
| NFR-SEC-02 | All protected API endpoints SHALL require a valid Laravel Sanctum bearer token. Requests without a token SHALL receive HTTP 401 Unauthorized. | SHALL |
| NFR-SEC-03 | Admin-only endpoints SHALL additionally verify the `is_admin` flag on the authenticated user. Non-admin users SHALL receive HTTP 403 Forbidden. | SHALL |
| NFR-SEC-04 | Module 2 (schedule management) and Module 3 (audit management) endpoints SHALL enforce Spatie role and permission checks. Unauthorized role access SHALL return HTTP 403 Forbidden. | SHALL |
| NFR-SEC-05 | User passwords SHALL be hashed using Laravel's default bcrypt algorithm before storage. Plaintext passwords SHALL NOT be stored or logged anywhere. | SHALL |
| NFR-SEC-06 | The system SHALL apply a **minimum password length of 8 characters**. No additional complexity rules are required in the current version. | SHALL |
| NFR-SEC-07 | All user-submitted text inputs (names, comments, search queries) SHALL be sanitized to prevent XSS and SQL injection attacks. Laravel's query builder parameterization and Blade/React output escaping SHALL be used throughout. | SHALL |
| NFR-SEC-08 | File uploads (fingerprint CSV/Excel) SHALL be validated for file type and structure. Files that fail validation SHALL be rejected before any data is processed. | SHALL |
| NFR-SEC-09 | The system SHALL NOT expose internal error stack traces to the client in production. Errors SHALL return a generic message with an HTTP status code only. | SHALL |
| NFR-SEC-10 | A manager SHALL only be able to access data belonging to their own department. The system SHALL enforce department-level data scoping on all M2 queries. | SHALL |

---

## 4.3  Authentication & Session Management

| ID | Requirement | Priority |
|---|---|---|
| NFR-AUTH-01 | Bearer tokens issued by Laravel Sanctum SHALL expire after **24 hours** of inactivity. | SHALL |
| NFR-AUTH-02 | After token expiry, the user SHALL be required to log in again to obtain a new token. | SHALL |
| NFR-AUTH-03 | A user who logs out explicitly SHALL have their token immediately invalidated server-side, regardless of the remaining expiry time. | SHALL |
| NFR-AUTH-04 | The system SHALL NOT allow concurrent login from the same credentials to produce multiple active tokens for the same session. Each login SHALL issue one active token per user. | SHOULD |

---

## 4.4  Usability

| ID | Requirement | Priority |
|---|---|---|
| NFR-USE-01 | The system SHALL be fully functional on all modern web browsers, including Google Chrome, Mozilla Firefox, Microsoft Edge, and Safari (latest stable releases). | SHALL |
| NFR-USE-02 | The user interface SHALL provide clear visual feedback for all user actions: loading states, success confirmations, and error messages. | SHALL |
| NFR-USE-03 | All destructive actions (delete shift, delete user, delete department, copy last week, publish schedule) SHALL require a confirmation dialog before executing. | SHALL |
| NFR-USE-04 | Error messages displayed to the user SHALL be written in plain language and indicate the cause and suggested action, not raw technical codes. | SHALL |
| NFR-USE-05 | The system interface language SHALL be English. | SHALL |

---

## 4.5  Reliability & Availability

| ID | Requirement | Priority |
|---|---|---|
| NFR-REL-01 | The system SHALL be available **24 hours a day, 7 days a week**, including weekends and holidays, to support managers working shift schedules across all hours. | SHALL |
| NFR-REL-02 | The automated Monday and Tuesday email reminder jobs (M2-EMAIL-01, M2-EMAIL-02, M2-EMAIL-03) SHALL execute reliably at their scheduled times without requiring manual intervention. | SHALL |
| NFR-REL-03 | A failed automated job (email or auto-copy) SHALL be logged with enough detail to allow a developer to diagnose the failure. Failed jobs SHALL NOT cause the system to crash or affect other functionality. | SHALL |
| NFR-REL-04 | The system SHALL handle file upload failures (network interruption, malformed file) gracefully and leave the database in a consistent state with no partial imports committed. | SHALL |

---

## 4.6  Maintainability

| ID | Requirement | Priority |
|---|---|---|
| NFR-MAINT-01 | The backend codebase SHALL follow Laravel conventions (MVC structure, Eloquent ORM, resource controllers, form request validation). | SHALL |
| NFR-MAINT-02 | All database schema changes SHALL be implemented as Laravel migrations. No manual schema changes to the production database are permitted. | SHALL |
| NFR-MAINT-03 | The system SHALL include a comprehensive automated test suite. All M1 API endpoints SHALL maintain passing test coverage. M2 and M3 test coverage SHALL be added as each module is implemented. | SHALL |
| NFR-MAINT-04 | Application errors and exceptions in production SHALL be written to the Laravel log (storage/logs/laravel.log) with timestamps, user context, and stack traces for developer diagnosis. | SHALL |

---

## 4.7  Data Retention

| ID | Requirement | Priority |
|---|---|---|
| NFR-DATA-01 | There is currently **no defined retention limit** for schedule records, audit records, fingerprint data, or change history logs. All data SHALL be retained indefinitely until a formal retention policy is decided and approved by NVT. | SHALL |
| NFR-DATA-02 | When a retention policy is adopted in a future version, it SHALL be implemented as a configurable system setting without requiring code changes to enforce it. | SHOULD |
| NFR-DATA-03 | The change history log for shift assignments SHALL NOT be deletable by any user action regardless of any future data retention policy. Deletion of history records, if ever required, SHALL only be performed by a system administrator directly on the database with written authorization. | SHALL |

---

## 4.8  Scalability

| ID | Requirement | Priority |
|---|---|---|
| NFR-SCALE-01 | The system architecture SHALL not impose a hard limit on the number of departments, users, or nesting levels. The department hierarchy SHALL support growth beyond the current 210 employees and 10+ departments without schema changes. | SHALL |
| NFR-SCALE-02 | The schedule and audit grid queries SHALL be optimized with appropriate database indexes to ensure performance remains within the 3-second threshold as the number of employees and historical records grows over time. | SHOULD |

---

*— End of Section 4 — Continue to Section 5: System Users & Roles —*

---

**CONFIDENTIAL — NVT Internal Use Only**
