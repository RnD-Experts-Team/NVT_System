# NVT System — API Postman Testing Guide

**Base URL:** `http://localhost/projacet/NVTsystem/public/api`  
**Auth:** All requests except Login require the header:
```
Authorization: Bearer {{token}}
```
Store the token you receive from Login in a Postman **environment variable** called `token`.

---

## Quick Start Checklist

1. Run Login → copy the `token` value → save it as `{{token}}` in your Postman environment
2. All admin routes below require `is_admin = true` on the logged-in user
3. The seeded Super Admin credentials are:
   - **Email:** `admin@nvtsystem.com`
   - **Password:** `Admin@1234`

---

## 1. Auth

### 1.1 Login
```
POST /auth/login
```
**Headers:**
```
Content-Type: application/json
```
**Body (JSON):**
```json
{
    "email": "admin@nvtsystem.com",
    "password": "Admin@1234"
}
```
**Success Response `200`:**
```json
{
    "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "user": {
        "id": 1,
        "name": "Super Admin",
        "email": "admin@nvtsystem.com",
        "is_admin": true,
        "department": { "id": 1, "name": "Head Office" },
        "level": { "id": 7, "code": "L6", "name": "Executive" },
        "tier": null,
        "roles": ["L6"]
    }
}
```
**Error Response `422` (wrong credentials):**
```json
{
    "message": "The provided credentials are incorrect.",
    "errors": {
        "email": ["The provided credentials are incorrect."]
    }
}
```

---

### 1.2 Logout
```
POST /auth/logout
```
**Headers:**
```
Authorization: Bearer {{token}}
```
**Success Response `200`:**
```json
{
    "message": "Logged out successfully."
}
```

---

### 1.3 Get Current User (Me)
```
GET /auth/me
```
**Headers:**
```
Authorization: Bearer {{token}}
```
**Success Response `200`:**
```json
{
    "data": {
        "id": 1,
        "name": "Super Admin",
        "nickname": null,
        "email": "admin@nvtsystem.com",
        "is_admin": true,
        "department": { "id": 1, "name": "Head Office" },
        "level": { "id": 7, "code": "L6", "name": "Executive" },
        "tier": null,
        "roles": ["L6"]
    }
}
```

---

## 2. Departments

> All department routes require: `Authorization: Bearer {{token}}` (Admin only)

### 2.1 List All Departments
```
GET /departments
```
**Success Response `200`:**
```json
{
    "data": [
        {
            "id": 1,
            "name": "Head Office",
            "description": "Root department",
            "parent_id": null,
            "path": "/1/",
            "is_active": true,
            "children": []
        }
    ]
}
```

---

### 2.2 Get Department Tree (Nested)
```
GET /departments/tree
```
**Success Response `200`:**
```json
{
    "data": [
        {
            "id": 1,
            "name": "Head Office",
            "parent_id": null,
            "path": "/1/",
            "is_active": true,
            "children": [
                {
                    "id": 2,
                    "name": "Engineering",
                    "parent_id": 1,
                    "path": "/1/2/",
                    "children": []
                }
            ]
        }
    ]
}
```

---

### 2.3 Create Department (Root)
```
POST /departments/create
```
**Body (JSON):**
```json
{
    "name": "Engineering",
    "description": "Technology & Innovation",
    "is_active": true
}
```
**Success Response `201`:**
```json
{
    "data": {
        "id": 2,
        "name": "Engineering",
        "description": "Technology & Innovation",
        "parent_id": null,
        "path": "/2/",
        "is_active": true
    }
}
```

---

### 2.4 Create Department (Child / Sub-department)
```
POST /departments/create
```
**Body (JSON):**
```json
{
    "name": "R&D",
    "description": "Research and Development",
    "parent_id": 2,
    "is_active": true
}
```
> The `path` is automatically built: `/2/3/`

---

### 2.5 Show Department
```
GET /departments/{id}
```
**Example:** `GET /departments/2`

**Success Response `200`:**
```json
{
    "data": {
        "id": 2,
        "name": "Engineering",
        "children": [...]
    }
}
```
**Not Found `404`:** when `id` does not exist.

---

### 2.6 Update Department
```
PUT /departments/{id}/update
```
**Body (JSON) — send only fields to change:**
```json
{
    "name": "Engineering & R&D",
    "is_active": false
}
```
**Success Response `200`**

---

### 2.7 Delete Department
```
DELETE /departments/{id}/delete
```
**Success Response `200`:**
```json
{ "message": "Department deleted successfully." }
```
**Error `422` — has child departments:**
```json
{ "message": "Cannot delete department with child departments." }
```
**Error `422` — has assigned users:**
```json
{ "message": "Cannot delete department that has users assigned." }
```

---

### 2.8 Get All Users in Department Tree
```
GET /departments/{id}/users
```
Returns all users in this department **and all its sub-departments** (uses materialized path).

**Example:** `GET /departments/2/users`

**Success Response `200`:**
```json
{
    "data": [
        {
            "id": 2,
            "name": "Jane Doe",
            "email": "jane@company.com",
            "level": { "code": "L2", "name": "Team Lead" },
            "tier": null
        }
    ]
}
```

---

## 3. User Levels

> All level routes require: Admin token

### 3.1 List All Levels
```
GET /levels
```
Returns levels ordered by `hierarchy_rank`.

**Success Response `200`:**
```json
{
    "data": [
        { "id": 1, "code": "L1", "name": "Employee",        "hierarchy_rank": 1, "tiers": [] },
        { "id": 2, "code": "L2", "name": "Team Lead",       "hierarchy_rank": 2, "tiers": [] },
        { "id": 3, "code": "L2PM","name": "Project Manager","hierarchy_rank": 2, "tiers": [] },
        { "id": 4, "code": "L3", "name": "Senior Manager",  "hierarchy_rank": 3, "tiers": [] },
        { "id": 5, "code": "L4", "name": "Department Head", "hierarchy_rank": 4, "tiers": [] },
        { "id": 6, "code": "L5", "name": "Director",        "hierarchy_rank": 5, "tiers": [] },
        { "id": 7, "code": "L6", "name": "Executive",       "hierarchy_rank": 6, "tiers": [] }
    ]
}
```

---

### 3.2 Create Level
```
POST /levels/create
```
**Body (JSON):**
```json
{
    "code": "L7",
    "name": "VP",
    "hierarchy_rank": 7,
    "description": "Vice President"
}
```
**Validation errors `422`:**
- `code` is required, unique, max 50 chars
- `name` is required, max 100 chars
- `hierarchy_rank` is required integer >= 1

---

### 3.3 Show Level (with Tiers)
```
GET /levels/{id}
```
**Example:** `GET /levels/2`

```json
{
    "data": {
        "id": 2,
        "code": "L2",
        "name": "Team Lead",
        "hierarchy_rank": 2,
        "tiers": [
            { "id": 1, "tier_name": "Tier 1", "tier_order": 1 },
            { "id": 2, "tier_name": "Tier 2", "tier_order": 2 }
        ]
    }
}
```

---

### 3.4 Update Level
```
PUT /levels/{id}/update
```
**Body (JSON):**
```json
{
    "name": "Senior Team Lead",
    "hierarchy_rank": 3
}
```

---

### 3.5 Delete Level
```
DELETE /levels/{id}/delete
```
**Error `422` — level has users:**
```json
{ "message": "Cannot delete level that has users assigned." }
```

---

## 4. User Level Tiers

> All tier routes are nested under levels: `/levels/{levelId}/tiers`

### 4.1 List Tiers for a Level
```
GET /levels/{levelId}/tiers
```
**Example:** `GET /levels/2/tiers`

```json
[
    { "id": 1, "user_level_id": 2, "tier_name": "Tier 1", "tier_order": 1, "description": null },
    { "id": 2, "user_level_id": 2, "tier_name": "Tier 2", "tier_order": 2, "description": null }
]
```

---

### 4.2 Create Tier
```
POST /levels/{levelId}/tiers/create
```
**Example:** `POST /levels/2/tiers/create`

**Body (JSON):**
```json
{
    "tier_name": "Tier 1",
    "tier_order": 1,
    "description": "Entry-level tier"
}
```
**Success Response `201`:**
```json
{
    "id": 1,
    "user_level_id": 2,
    "tier_name": "Tier 1",
    "tier_order": 1,
    "description": "Entry-level tier"
}
```

---

### 4.3 Show Tier
```
GET /levels/{levelId}/tiers/{tierId}
```
**Note:** Returns `404` if the tier does not belong to the given level.

---

### 4.4 Update Tier
```
PUT /levels/{levelId}/tiers/{tierId}/update
```
**Body (JSON):**
```json
{
    "tier_name": "Senior Tier 1",
    "tier_order": 1
}
```

---

### 4.5 Delete Tier
```
DELETE /levels/{levelId}/tiers/{tierId}/delete
```
**Error `422` — tier has users:**
```json
{ "message": "Cannot delete tier that has users assigned." }
```

---

## 5. Users

> All user routes require: Admin token

### 5.1 List All Users
```
GET /users
```
**Success Response `200`:**
```json
{
    "data": [
        {
            "id": 1,
            "name": "Super Admin",
            "nickname": null,
            "email": "admin@nvtsystem.com",
            "is_admin": true,
            "department": { "id": 1, "name": "Head Office" },
            "level": { "id": 7, "code": "L6", "name": "Executive" },
            "tier": null,
            "roles": ["L6"]
        }
    ]
}
```

---

### 5.2 Create User
```
POST /users/create
```
**Body (JSON):**
```json
{
    "name": "Jane Doe",
    "nickname": "Jane",
    "email": "jane@company.com",
    "password": "Secret@1234",
    "department_id": 2,
    "user_level_id": 2,
    "user_level_tier_id": null,
    "is_admin": false
}
```
**Important:** On creation, the user is automatically assigned the Spatie role matching their level code (e.g., level `L2` → role `L2`).

**Validation errors `422`:**
- `name` required
- `email` required, must be unique
- `password` required, min 8 characters
- `department_id` required, must exist
- `user_level_id` required, must exist
- `user_level_tier_id` optional, must exist if provided

---

### 5.3 Show User
```
GET /users/{id}
```
Returns the user with `department`, `level`, `tier`, and `roles` loaded.

---

### 5.4 Update User
```
PUT /users/{id}/update
```
**Body (JSON) — send only fields to change:**
```json
{
    "name": "Jane Smith",
    "nickname": "JS",
    "department_id": 3,
    "user_level_id": 3
}
```
**Important:** If `user_level_id` changes, the user's Spatie role is **automatically synced** to match the new level code.

---

### 5.5 Delete User
```
DELETE /users/{id}/delete
```
**Success Response `200`:**
```json
{ "message": "User deleted successfully." }
```

---

## 6. Roles

> All role routes require: Admin token

### 6.1 List All Roles
```
GET /roles
```
**Success Response `200`:**
```json
[
    { "id": 1, "name": "L1",      "permissions": [] },
    { "id": 2, "name": "L2",      "permissions": ["manage_own_department_schedule"] },
    { "id": 3, "name": "L2PM",    "permissions": [] },
    { "id": 4, "name": "L3",      "permissions": ["manage_own_department_schedule"] },
    { "id": 5, "name": "L4",      "permissions": ["manage_own_department_schedule"] },
    { "id": 6, "name": "L5",      "permissions": ["manage_own_department_schedule"] },
    { "id": 7, "name": "L6",      "permissions": ["manage_own_department_schedule"] },
    { "id": 8, "name": "auditor", "permissions": ["view_all_schedules"] }
]
```

---

### 6.2 Create Role
```
POST /roles/create
```
**Body (JSON):**
```json
{
    "name": "reports_viewer"
}
```
**Validation:** `name` required, unique across all roles.

---

### 6.3 Show Role
```
GET /roles/{id}
```
```json
{
    "id": 2,
    "name": "L2",
    "permissions": ["manage_own_department_schedule"]
}
```

---

### 6.4 Update Role
```
PUT /roles/{id}/update
```
**Body (JSON):**
```json
{
    "name": "senior_manager"
}
```

---

### 6.5 Delete Role
```
DELETE /roles/{id}/delete
```

---

### 6.6 Assign Permissions to Role
```
POST /roles/{id}/permissions
```
**Body (JSON):**
```json
{
    "permissions": ["manage_own_department_schedule", "view_all_schedules"]
}
```
This **replaces** all existing permissions on the role (sync, not append).

**Success Response `200`:**
```json
{
    "message": "Permissions assigned successfully.",
    "role": "L2",
    "permissions": ["manage_own_department_schedule", "view_all_schedules"]
}
```
**Error `422` — permission name does not exist:**
```json
{
    "errors": {
        "permissions.0": ["The selected permissions.0 is invalid."]
    }
}
```

---

## 7. Permissions

> All permission routes require: Admin token

### 7.1 List All Permissions
```
GET /permissions
```
**Success Response `200`:**
```json
[
    { "id": 1, "name": "manage_hierarchy" },
    { "id": 2, "name": "manage_own_department_schedule" },
    { "id": 3, "name": "view_all_schedules" }
]
```

---

### 7.2 Create Permission
```
POST /permissions/create
```
**Body (JSON):**
```json
{
    "name": "export_reports"
}
```

---

### 7.3 Show Permission
```
GET /permissions/{id}
```

---

### 7.4 Update Permission
```
PUT /permissions/{id}/update
```
**Body (JSON):**
```json
{
    "name": "export_all_reports"
}
```

---

### 7.5 Delete Permission
```
DELETE /permissions/{id}/delete
```

---

## Common HTTP Status Codes

| Code | Meaning |
|------|---------|
| `200` | Success |
| `201` | Created successfully |
| `401` | Unauthenticated — missing or invalid token |
| `403` | Forbidden — authenticated but not admin |
| `404` | Resource not found |
| `422` | Validation error — check the `errors` field in the response |
| `500` | Server error — check Laravel logs in `storage/logs/` |

---

## Postman Environment Setup

1. Open Postman → **Environments** → **New Environment** → name it `NVTsystem Local`
2. Add variable:

| Variable | Initial Value | Current Value |
|---|---|---|
| `base_url` | `http://localhost/projacet/NVTsystem/public/api` | _(same)_ |
| `token` | _(empty)_ | _(paste after login)_ |

3. In every request, set the URL as `{{base_url}}/auth/login`, etc.
4. After login, copy the `token` value from the response and paste it into the `token` environment variable.
5. For all protected requests, add the header: `Authorization: Bearer {{token}}`

---

## Full Test Flow (Recommended Order)

```
1.  POST   /auth/login                              → get token
2.  GET    /auth/me                                 → verify who you are
3.  GET    /levels                                  → see seeded levels
4.  POST   /departments/create                      → create "Engineering"
5.  POST   /departments/create                      → create "R&D" with parent_id from step 4
6.  GET    /departments/tree                        → verify nested structure
7.  POST   /levels/{id}/tiers/create                → add Tier 1 to L2
8.  POST   /users/create                            → create a new user in Engineering at L2
9.  GET    /users/{id}                              → verify role was auto-assigned as L2
10. GET    /departments/{engId}/users               → verify user appears via subtree query
11. PUT    /users/{id}/update                       → change level to L3 → verify role changes
12. GET    /roles                                   → see all roles
13. GET    /permissions                             → see all permissions
14. POST   /roles/{roleId}/permissions              → assign permissions to a role
15. GET    /roles/{roleId}                          → verify permissions are attached
16. DELETE /users/{id}/delete                       → delete the test user
17. DELETE /departments/{rdId}/delete               → delete R&D (no users, no children)
18. POST   /auth/logout                             → invalidate token
19. GET    /auth/me                                 → should return 401
```

---

## 8. Shifts

> `GET /shifts` is available to any authenticated user. `POST /create`, `PUT /{id}/update`, `DELETE /{id}/delete` require the **Compliance** role.

### 8.1 List All Shifts
```
GET /shifts
```
**Success Response `200`:**
```json
{
    "data": [
        {
            "id": 1,
            "name": "Morning",
            "start_time": "08:00",
            "end_time": "17:00",
            "is_overnight": false,
            "is_active": true
        }
    ]
}
```

---

### 8.2 Create Shift *(Compliance only)*
```
POST /shifts/create
```
**Body (JSON):**
```json
{
    "name": "Morning",
    "start_time": "08:00",
    "end_time": "17:00"
}
```
> `is_overnight` is **auto-detected**: if `end_time` is earlier than `start_time` the flag is set to `true`.

**Success Response `201`:**
```json
{
    "data": {
        "id": 1,
        "name": "Morning",
        "start_time": "08:00",
        "end_time": "17:00",
        "is_overnight": false,
        "is_active": true
    }
}
```
**Validation errors `422`:**
- `name` required
- `start_time` required, format `HH:mm`
- `end_time` required, format `HH:mm`

---

### 8.3 Update Shift *(Compliance only)*
```
PUT /shifts/{id}/update
```
**Body (JSON) — send only fields to change:**
```json
{
    "name": "Morning Extended",
    "end_time": "18:00"
}
```
**Success Response `200`** — returns updated shift object.

---

### 8.4 Delete Shift *(Compliance only)*
```
DELETE /shifts/{id}/delete
```
**Success Response `200`:**
```json
{ "message": "Shift deleted successfully." }
```
**Error `422` — shift is used in assignments:**
```json
{ "message": "Cannot delete shift that is used in assignments." }
```

---

## 9. Schedule Grid

> All schedule routes require the **Manager** role (levels L2–L6, L2PM).  
> The manager only sees data for their own `department_id`.

### 9.1 Get Weekly Schedule Grid
```
GET /schedules?week_start=2026-04-06
```
> `week_start` must be a **Monday** date (`YYYY-MM-DD`).

**Success Response `200`:**
```json
{
    "data": {
        "week_start": "2026-04-06",
        "week_end": "2026-04-12",
        "department_id": 2,
        "schedule_id": null,
        "status": "none",
        "published_at": null,
        "employees": [
            {
                "user_id": 5,
                "name": "Jane Doe",
                "nickname": "Jane",
                "level": "L2",
                "tier": null,
                "days": [
                    {
                        "date": "2026-04-06",
                        "assignment_id": null,
                        "assignment_type": null,
                        "shift": null,
                        "is_cover": false,
                        "cover_for_user": null,
                        "comment": null,
                        "history_count": 0
                    }
                ]
            }
        ]
    }
}
```
> When a schedule exists, `schedule_id` and `status` (`draft` or `published`) will be filled in. Each employee has 7 day entries (Mon–Sun).

---

### 9.2 Get Day Detail
```
GET /schedules/day?week_start=2026-04-06&date=2026-04-07
```
**Success Response `200`:**
```json
{
    "data": {
        "date": "2026-04-07",
        "week_start": "2026-04-06",
        "department_id": 2,
        "summary": {
            "total": 5,
            "shift": 3,
            "day_off": 1,
            "sick_day": 0,
            "leave_request": 1,
            "unassigned": 0
        },
        "assignments": [
            {
                "user_id": 5,
                "name": "Jane Doe",
                "assignment_type": "shift",
                "shift": { "id": 1, "name": "Morning", "start_time": "08:00", "end_time": "17:00" }
            }
        ]
    }
}
```

---

## 10. Shift Assignments

> All assignment routes require the **Manager** role.

### 10.1 Create Assignment
```
POST /schedules/assignments/create
```
**Body (JSON):**
```json
{
    "week_start": "2026-04-06",
    "user_id": 5,
    "assignment_date": "2026-04-06",
    "assignment_type": "shift",
    "shift_id": 1,
    "is_cover": false,
    "comment": null
}
```
> `assignment_type` can be: `shift`, `day_off`, `sick_day`, `leave_request`  
> `shift_id` is **required** when `assignment_type` is `shift`  
> `cover_for_user_id` is **required** when `is_cover` is `true`

**Success Response `201`** — returns the assignment object plus history entry.

---

### 10.2 Update Assignment
```
PUT /schedules/assignments/{id}/update
```
**Body (JSON):**
```json
{
    "assignment_type": "day_off",
    "shift_id": null,
    "comment": "Approved leave"
}
```
**Success Response `200`** — every update appends a `ShiftAssignmentHistory` record automatically.

---

### 10.3 Delete Assignment (Soft Delete)
```
DELETE /schedules/assignments/{id}/delete
```
**Success Response `200`:**
```json
{ "message": "Assignment deleted." }
```
> This is a **soft delete** — the history records are preserved. The cell will appear as unassigned again.

---

### 10.4 Get Assignment History
```
GET /schedules/assignments/{id}/history
```
**Success Response `200`:**
```json
{
    "data": [
        {
            "id": 3,
            "changed_by": { "id": 1, "name": "Super Admin" },
            "previous_type": "shift",
            "previous_shift": { "id": 1, "name": "Morning" },
            "new_type": "day_off",
            "new_shift": null,
            "comment": "Approved leave",
            "changed_at": "2026-04-06T10:00:00.000000Z"
        }
    ]
}
```

---

## 11. Schedule Actions

> All schedule action routes require the **Manager** role.

### 11.1 Bulk Create Assignments
```
POST /schedules/assignments/bulk-create
```
**Mode `by_employees` — assign same shift to multiple users for entire week:**
```json
{
    "week_start": "2026-04-06",
    "mode": "by_employees",
    "user_ids": [5, 6, 7],
    "assignment_type": "shift",
    "shift_id": 1
}
```
**Mode `by_days` — assign specific dates to all department employees:**
```json
{
    "week_start": "2026-04-06",
    "mode": "by_days",
    "dates": ["2026-04-06", "2026-04-07"],
    "assignment_type": "day_off"
}
```
**Success Response `200`:**
```json
{
    "message": "Bulk assignments created.",
    "created": 14,
    "skipped": 2
}
```
> Skipped count = cells that already had an assignment (will not overwrite existing).

---

### 11.2 Copy Last Week's Schedule
```
POST /schedules/copy-last-week
```
**Body (JSON):**
```json
{
    "week_start": "2026-04-13"
}
```
> Copies all assignments from the week of `2026-04-06` into the week of `2026-04-13`.

**Success Response `200`:**
```json
{
    "message": "Schedule copied from previous week.",
    "copied": 35
}
```
**Error `422` — no previous week schedule exists:**
```json
{ "message": "No schedule found for the previous week." }
```

---

### 11.3 Check Publish Status
```
GET /schedules/publish-status?week_start=2026-04-06
```
**Success Response `200`:**
```json
{
    "data": {
        "week_start": "2026-04-06",
        "status": "draft",
        "total_required": 35,
        "total_filled": 33,
        "missing_count": 2,
        "can_publish": false,
        "missing_cells": [
            { "user_id": 5, "name": "Jane Doe", "date": "2026-04-10" },
            { "user_id": 6, "name": "John Smith", "date": "2026-04-11" }
        ]
    }
}
```
> `can_publish` is `true` only when `missing_count` is `0`.

---

### 11.4 Publish Schedule
```
POST /schedules/publish
```
**Body (JSON):**
```json
{
    "week_start": "2026-04-06"
}
```
**Success Response `200`:**
```json
{
    "data": {
        "message": "Schedule published successfully.",
        "week_start": "2026-04-06",
        "published_at": "2026-04-01T10:00:00.000000Z",
        "published_by_name": "Jane Manager"
    }
}
```
**Error `422` — incomplete schedule:**
```json
{
    "message": "Cannot publish: schedule has unfilled cells.",
    "missing_count": 2,
    "missing_cells": [
        { "user_id": 5, "name": "Jane Doe", "date": "2026-04-10" }
    ]
}
```

---

### 11.5 Export Schedule as CSV
```
GET /schedules/export?week_start=2026-04-06
```
> Returns a downloadable **CSV file** (not JSON).  
> In Postman: click **Send**, then click **Save Response → Save to a file**.

**CSV columns:** `Employee Name`, `Mon 06/04`, `Tue 07/04`, `Wed 08/04`, `Thu 09/04`, `Fri 10/04`, `Sat 11/04`, `Sun 12/04`  
**Cell values:** shift name (e.g. `Morning`), `Day Off`, `Sick Day`, `Leave`, or blank if unassigned.

**Error `422` — missing week_start:**
```json
{ "message": "The week start field is required." }
```

---

## 12. Compliance — Fingerprint Import & Audit

> All M3 routes require the **Compliance** role.  
> Login as a user who has the `Compliance` Spatie role assigned.

### 12.1 List Fingerprint Imports
```
GET /fingerprint/imports
```
Returns a paginated list of all previous uploads.

**Success Response `200`:**
```json
{
    "data": [
        {
            "id": 1,
            "imported_by": { "id": 3, "name": "Compliance Officer" },
            "week_start": "2026-03-30",
            "filename": "attendance_week13.xlsx",
            "status": "done",
            "rows_imported": 210,
            "rows_failed": 3,
            "imported_at": "2026-04-01T08:30:00.000000Z"
        }
    ]
}
```

---

### 12.2 Upload Fingerprint File
```
POST /fingerprint/imports/upload
```
> This is a **multipart/form-data** request, NOT JSON.  
> In Postman: select **Body → form-data**.

**Form fields:**

| Key | Type | Value |
|-----|------|-------|
| `file` | File | Select your `.csv` or `.xlsx` file |
| `week_start` | Text | `2026-03-30` |

**Expected file format (columns must be in this order):**

| Column | Name | Example |
|--------|------|---------|
| 0 | `AC_No` | `1042` |
| 1 | `Name` | `Jane Doe` |
| 2 | `Time` | `3/30/2026 8:05 AM` |
| 3 | `State` | `C-In` or `C-Out` |

> The parser groups rows by `AC_No + date`. The **first** `C-In` of the day = `clock_in`. The **last** `C-Out` of the day = `clock_out`. Rows are matched to users via `users.ac_no`.

**Success Response `200`:**
```json
{
    "message": "Import complete.",
    "rows_imported": 210,
    "rows_failed": 3,
    "errors": [
        "Row 15: AC_No 9999 not found in system.",
        "Row 42: Could not parse date '13/0/2026'.",
        "Row 78: AC_No 1050 not found in system."
    ]
}
```
**Validation Error `422` — wrong file type or missing week_start:**
```json
{
    "errors": {
        "file": ["The file must be a file of type: csv, xlsx."],
        "week_start": ["The week start field is required."]
    }
}
```

---

### 12.3 Get Audit Grid (Weekly)
```
GET /audit?week_start=2026-03-30&department_id=2
```

**Optional query parameters:**

| Parameter | Type | Example | Description |
|-----------|------|---------|-------------|
| `week_start` | date | `2026-03-30` | Required. Must be a Monday. |
| `department_id` | integer | `2` | Required. Department to audit. |
| `status` | string | `late` | Filter employees by status. One of: `on_time`, `late`, `left_early_std`, `left_early_earl`, `combined`, `absent`, `off` |
| `search` | string | `jane` | Filter employees by name. |

**Success Response `200`:**
```json
{
    "data": {
        "week_start": "2026-03-30",
        "week_end": "2026-04-05",
        "summary": {
            "on_time": 12,
            "late": 5,
            "left_early_std": 2,
            "left_early_earl": 1,
            "combined": 0,
            "absent": 3,
            "off": 7
        },
        "employees": [
            {
                "user_id": 5,
                "name": "Jane Doe",
                "level": "L2",
                "tier": null,
                "days": [
                    {
                        "date": "2026-03-30",
                        "status": "late",
                        "clock_in": "08:12",
                        "clock_out": "17:03",
                        "late_minutes": 7,
                        "early_minutes": 0
                    },
                    {
                        "date": "2026-03-31",
                        "status": "on_time",
                        "clock_in": "07:58",
                        "clock_out": "17:05",
                        "late_minutes": 0,
                        "early_minutes": 0
                    }
                ]
            }
        ]
    }
}
```

**Status values explained:**

| Status | Meaning |
|--------|---------|
| `on_time` | Clocked in within 5-min grace, clocked out at or after shift end |
| `late` | Clocked in more than 5 minutes after shift start |
| `left_early_std` | Left before shift end but completed ≥ 60% of shift duration |
| `left_early_earl` | Left before shift end and completed < 60% of shift duration |
| `combined` | Both late AND left early |
| `absent` | No fingerprint record on a working day |
| `off` | Assignment type is `day_off`, `sick_day`, or `leave_request` |

---

### 12.4 Get Audit Cell Detail
```
GET /audit/cell?user_id=5&date=2026-03-30
```
Returns full detail for one employee on one day.

**Success Response `200`:**
```json
{
    "data": {
        "user_id": 5,
        "name": "Jane Doe",
        "date": "2026-03-30",
        "status": "late",
        "clock_in": "08:12",
        "clock_out": "17:03",
        "late_minutes": 7,
        "early_minutes": 0,
        "shift_completion_pct": 100,
        "shift": {
            "id": 1,
            "name": "Morning",
            "start_time": "08:00",
            "end_time": "17:00"
        },
        "assignment_type": "shift",
        "fingerprint_import": {
            "id": 1,
            "filename": "attendance_week13.xlsx",
            "imported_at": "2026-04-01T08:30:00.000000Z"
        }
    }
}
```
**Error `404` — no assignment or no record for that date:**
```json
{ "message": "No data found for this employee on this date." }
```
