---
**SOFTWARE REQUIREMENTS SPECIFICATION**
**Employee Schedule Management System**

| | |
|---|---|
| **Organization** | NVT — New Vision Team |
| **Document type** | Software Requirements Specification (SRS) |
| **Current section** | Section 5 — System Users & Roles |
| **Document status** | Draft — In progress (9 sections total) |
| **Version** | 1.0 |
| **Prepared by** | NVT Development Team |
| **Date** | 31 March 2026 |

---

**SRS Progress — Section 5 of 9**

| | |
|---|---|
| ✅ Section 1: Introduction | ✅ Section 2: Overall Description |
| ✅ Section 3: Functional Requirements | ✅ Section 4: Non-Functional Requirements |
| ✅ Section 5: System Users & Roles *(this document)* | ⬜ Section 6: Use Cases |
| ⬜ Section 7: Technical Requirements | ⬜ Section 8: Constraints & Assumptions |
| ⬜ Section 9: Final Assembly & Appendix | |

---

## Overview

This section defines every user type in the system, their access boundaries, and the technical mechanism used to enforce those boundaries. It covers three operational roles plus the employee level role codes that are assigned automatically by the system.

---

## 5.1  Role Architecture

The system uses two separate but combined access control mechanisms:

| Mechanism | Purpose | Managed by |
|---|---|---|
| `is_admin` flag (boolean on `users` table) | Grants full administrative access to M1 — Core Admin & Hierarchy | Admin sets this flag manually per user |
| Spatie Laravel Permission roles | Controls access to M2 (Manager Schedule) and M3 (Audit & Compliance) | Assigned automatically when a user's level is set; Compliance role assigned manually by Admin |

These two mechanisms are independent. A user can have `is_admin = true` AND a Spatie role simultaneously.

---

## 5.2  Operational Roles

The system has three operational roles that define what a user can do at runtime:

### 5.2.1  Admin

| Property | Value |
|---|---|
| **Access granted by** | `is_admin = true` flag on the user record |
| **Who assigns** | Another Admin (or the seeded super-admin) |
| **Module access** | M1 — full read/write access to all hierarchy, user, role, and permission management |
| **M2 access** | Yes — if the Admin is also assigned a Manager-level role (L2 or above), they can additionally use M2 for their department |
| **M3 access** | No — unless the Admin is separately assigned the Compliance role |
| **Can create users** | Yes — Admin creates all system users, including other Admins, Managers, and Compliance members |

**Access enforcement:** Every M1 endpoint is protected by the `EnsureIsAdmin` middleware, which checks `auth()->user()->is_admin === true`.

---

### 5.2.2  Manager

| Property | Value |
|---|---|
| **Access granted by** | Spatie role code matching a manager-level: `L2`, `L2PM`, `L3`, `L4`, `L5`, or `L6` |
| **Who assigns** | The system assigns this role automatically when the Admin sets the user's level to L2 or above |
| **Module access** | M2 — full schedule management for their own department only |
| **M1 access** | No — unless the user also has `is_admin = true` |
| **M3 access** | No |
| **Department scope** | A manager belongs to exactly one department and can only view and modify the schedule for that department |
| **Multiple managers** | Multiple managers may be assigned to the same department; each has full M2 access to that department's schedule |
| **Publishing** | Any manager of the department (L2 or above) may publish the schedule; no seniority restriction applies |

**Access enforcement:** M2 endpoints check for a valid Spatie role in the manager-level set (`L2`, `L2PM`, `L3`, `L4`, `L5`, `L6`) and then verify the requested department matches the authenticated user's assigned department.

---

### 5.2.3  Compliance Team

| Property | Value |
|---|---|
| **Access granted by** | Spatie role: `Compliance` |
| **Who assigns** | Admin manually assigns the Compliance role when creating the user account |
| **Module access** | M3 — full access to audit grid, fingerprint import, and shift management |
| **M1 access** | No |
| **M2 access** | No |
| **Department scope** | None — Compliance team can view the audit grid across all departments |

**Access enforcement:** M3 endpoints check for the `Compliance` Spatie role. Users without this role receive HTTP 403 Forbidden.

---

## 5.3  Employee Level Role Codes

In addition to the three operational roles above, the system automatically assigns a Spatie role matching the employee's level code when their level is set or changed. These codes are used for level identification and future permission expansion.

| Level Code | Role Name | Management Authority | Operational Role |
|---|---|---|---|
| L1 | `L1` | No management authority | Employee only — no module access beyond login |
| L2 | `L2` | Manages L1 | Manager |
| L2PM | `L2PM` | Manages L1 (Project Manager track) | Manager |
| L3 | `L3` | Manages L2 and L1 | Manager |
| L4 | `L4` | Manages L3, L2, and L1 | Manager |
| L5 | `L5` | Manages L4 and below | Manager |
| L6 | `L6` | Manages L5 and below | Manager |

> **Note:** L1 employees can log in and view their own profile via the `/auth/me` endpoint. They have no access to M1, M2, or M3 in the current version.

---

## 5.4  Combined Access Matrix

The table below shows exactly which modules and actions each role combination can perform.

| Action | Admin (`is_admin`) | Manager (L2+) | Compliance | L1 Employee |
|---|---|---|---|---|
| Log in | ✅ | ✅ | ✅ | ✅ |
| View own profile (`/auth/me`) | ✅ | ✅ | ✅ | ✅ |
| **M1 — Manage departments** | ✅ | ❌ | ❌ | ❌ |
| **M1 — Manage user levels & tiers** | ✅ | ❌ | ❌ | ❌ |
| **M1 — Create / edit / delete users** | ✅ | ❌ | ❌ | ❌ |
| **M1 — Manage roles & permissions** | ✅ | ❌ | ❌ | ❌ |
| **M2 — View own department schedule** | ✅ (if also Manager) | ✅ | ❌ | ❌ |
| **M2 — Assign / edit shifts** | ✅ (if also Manager) | ✅ | ❌ | ❌ |
| **M2 — Publish schedule** | ✅ (if also Manager) | ✅ | ❌ | ❌ |
| **M2 — Export CSV** | ✅ (if also Manager) | ✅ | ❌ | ❌ |
| **M3 — View audit grid** | ❌ (unless also Compliance) | ❌ | ✅ | ❌ |
| **M3 — Upload fingerprint data** | ❌ (unless also Compliance) | ❌ | ✅ | ❌ |
| **M3 — Manage shifts** | ❌ (unless also Compliance) | ❌ | ✅ | ❌ |

---

## 5.5  User Lifecycle

### 5.5.1  Creating a User

All user accounts are created by the Admin through M1. The Admin provides:
- Name, email, password
- Department assignment (one department per user)
- User level (L1 through L6, or L2PM)
- Optionally: tier within that level
- Optionally: `is_admin = true` flag
- Optionally: manual Compliance role assignment

Upon creation, the system automatically synchronizes the user's Spatie role to their level code.

### 5.5.2  Changing a User's Level

When the Admin updates a user's level, the system automatically:
1. Removes the user's previous level-code role
2. Assigns the new level-code role

This synchronization is atomic — at no point does the user hold zero roles or two level-code roles simultaneously.

### 5.5.3  Assigning Admin Rights

An existing user can be elevated to Admin at any time by the Admin setting `is_admin = true` on their account. This does not alter their Spatie role or department assignment. The user will then have both their existing module access (Manager or Compliance) AND full M1 administrative access.

### 5.5.4  Deleting a User

When a user account is deleted:
- Their bearer tokens are invalidated
- Their Spatie role assignments are removed
- Their shift assignments in M2 remain in the schedule (historical record is preserved)
- Their fingerprint records in M3 remain (historical record is preserved)

---

## 5.6  Spatie Roles Reference

The following Spatie roles SHALL exist in the system:

| Role Name | Created by | Used for |
|---|---|---|
| `L1` | Seeder / Auto-assigned | Level identification for L1 employees |
| `L2` | Seeder / Auto-assigned | Manager access (L2 level) |
| `L2PM` | Seeder / Auto-assigned | Manager access (L2 Project Manager track) |
| `L3` | Seeder / Auto-assigned | Manager access (L3 level) |
| `L4` | Seeder / Auto-assigned | Manager access (L4 level) |
| `L5` | Seeder / Auto-assigned | Manager access (L5 level) |
| `L6` | Seeder / Auto-assigned | Manager access (L6 level) |
| `Compliance` | Admin assigns manually | Full M3 Audit & Compliance access |

---

*— End of Section 5 — Continue to Section 6: Use Cases —*

---

**CONFIDENTIAL — NVT Internal Use Only**
