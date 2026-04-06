---
**SOFTWARE REQUIREMENTS SPECIFICATION**
**Employee Schedule Management System**

| | |
|---|---|
| **Organization** | NVT — New Vision Team |
| **Document type** | Software Requirements Specification (SRS) |
| **Current section** | Section 7 — Technical Flow: Backend Only |
| **Document status** | Draft — Working Document (Module-by-Module) |
| **Version** | 1.0 |
| **Prepared by** | NVT Development Team |
| **Date** | 31 March 2026 |

---

**SRS Progress — Section 7 of 9**

| | |
|---|---|
| ✅ Section 1: Introduction | ✅ Section 2: Overall Description |
| ✅ Section 3: Functional Requirements | ✅ Section 4: Non-Functional Requirements |
| ✅ Section 5: System Users & Roles | ✅ Section 6: Use Cases |
| ✅ Section 7: Technical Flow *(this document)* | ⬜ Section 8: Constraints & Assumptions |
| ⬜ Section 9: Final Assembly & Appendix | |

---

## About This Section

This section documents the complete **backend-only** technical flow for every API endpoint in the system. It is built **module-by-module** to allow verification of each module's completeness before moving to the next.

Each endpoint flow includes:
- **Route** definition and HTTP method
- **Middleware** stack (if any)
- **Controller** method and signature
- **Validation** rules (FormRequest or inline)
- **Service/Business Logic** (if any)
- **Model/Database** operations
- **JSON Response** (exact structure with HTTP status code)

---

## M1 — Core Admin & Hierarchy

**Status: ✅ IMPLEMENTED**  
**Total Endpoints: 36**  
**Tests: 101 passing**

### M1-AUTH — Authentication (3 endpoints)

#### 1. POST /api/auth/login

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `POST /api/auth/login` | Public route, no middleware |
| **Controller** | `AuthController.php` | `login(Request $request)` | Validates, calls Auth::attempt(), loads user with relationships |
| **Validation** | Inline | `validate(['email'=>'required\|email', 'password'=>'required\|string'])` | Returns 422 if invalid |
| **Business Logic** | `AuthController.php` | Auth::attempt() + createToken() | Attempts login, generates Sanctum token if successful |
| **Model** | `User.php` | `load(['department', 'level', 'tier', 'roles'])` | Eager-loads relationships for response |
| **Response** | `UserResource` | 200 OK | `{token, user: {id, name, email, is_admin, department, level, tier, roles}}` |
| **Errors** | `ValidationException` | 422 | `{message: 'The provided credentials are incorrect.'}` |

**Response JSON — 200 OK:**
```json
{
  "token": "1|abcdefg123xyz",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@nvt.com",
    "is_admin": true,
    "department": {"id": 1, "name": "Head Office"},
    "level": {"id": 1, "code": "L6", "name": "Level 6"},
    "tier": null,
    "roles": ["L6"]
  }
}
```

---

#### 2. POST /api/auth/logout

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `POST /api/auth/logout` | Protected: `middleware('auth:sanctum')` |
| **Controller** | `AuthController.php` | `logout(Request $request)` | Deletes current access token |
| **Business Logic** | `AuthController` | `$request->user()->currentAccessToken()->delete()` | Invalidates token immediately |
| **Response** | JSON | 200 OK | `{message: 'Logged out successfully.'}` |

---

#### 3. GET /api/auth/me

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/auth/me` | Protected: `middleware('auth:sanctum')` |
| **Controller** | `AuthController.php` | `me(Request $request)` | Returns current authenticated user |
| **Model** | `User.php` | `$request->user()->load(['department', 'level', 'tier', 'roles'])` | Loads all relationships |
| **Response** | `UserResource` | 200 OK | Same structure as login response |

---

### M1-DEPT — Department Management (7 endpoints)

#### 4. GET /api/departments

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/departments` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `DepartmentController.php` | `index()` | Returns flat list of all departments |
| **Model** | `Department.php` | `Department::with('children')->get()` | Loads child references |
| **Response** | `DepartmentResource::collection()` | 200 OK | Array of `{id, name, description, parent_id, path, is_active, children}` |

---

#### 5. POST /api/departments/create

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `POST /api/departments/create` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `DepartmentController.php` | `store(Request $request)` | Creates new department |
| **Validation** | Inline | `validate(['name'=>'required\|string\|max:255', 'parent_id'=>'nullable\|exists:departments,id'])` | Returns 422 if invalid |
| **Business Logic** | `DepartmentController` | Builds materialized path from parent | If parent exists, path = parent.path + id + '/' |
| **Model** | `Department.php` | `Department::create($validated)` then `$department->save()` | Creates record with path |
| **Response** | `DepartmentResource` | 201 Created | `{id, name, description, parent_id, path, is_active, children}` |

---

#### 6. GET /api/departments/tree

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/departments/tree` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `DepartmentController.php` | `tree()` | Returns nested hierarchy |
| **Model** | `Department.php` | `Department::with('children.children.children')->whereNull('parent_id')->get()` | Loads roots with 3 levels of nesting |
| **Response** | `DepartmentResource::collection()` | 200 OK | Nested array with recursive `children` |

---

#### 7. GET /api/departments/{department}

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/departments/{department}` | Protected: `middleware(['auth:sanctum', 'is_admin'])`. Department is implicit route model binding |
| **Controller** | `DepartmentController.php` | `show(Department $department)` | Returns single department with children |
| **Model** | **Implicit** | Route model binding resolves `{department}` to `Department::find()` | |
| **Response** | `DepartmentResource` | 200 OK | `{id, name, description, parent_id, path, is_active, children}` |

---

#### 8. PUT /api/departments/{department}/update

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `PUT /api/departments/{department}/update` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `DepartmentController.php` | `update(Request $request, Department $department)` | Updates department fields |
| **Validation** | Inline | `validate(['name'=>'sometimes\|required\|string\|max:255', 'parent_id'=>'nullable\|exists:departments,id'])` | Returns 422 if invalid |
| **Model** | `Department.php` | `$department->update($validated)` | Updates specified columns |
| **Response** | `DepartmentResource` | 200 OK | Updated department object |

---

#### 9. DELETE /api/departments/{department}/delete

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `DELETE /api/departments/{department}/delete` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `DepartmentController.php` | `destroy(Department $department)` | Deletes department with validation |
| **Business Logic** | `DepartmentController` | Checks `$department->children()->exists()` and `$department->users()->exists()` | Prevents delete if children or users exist |
| **Response** | JSON | 200 OK | `{message: 'Department deleted successfully.'}` |
| **Errors** | JSON | 422 | `{message: 'Cannot delete department with child departments.'}` or `{message: 'Cannot delete department that has users assigned.'}` |

---

#### 10. GET /api/departments/{department}/users

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/departments/{department}/users` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `DepartmentController.php` | `users(Department $department)` | Returns all users in this department and sub-departments |
| **Business Logic** | `DepartmentController` | Queries path: `where('path', 'like', $dept->path.'%')` | Uses materialized path for subtree query |
| **Model** | `User.php` | `User::with(['level', 'tier'])->whereIn('department_id', $deptIds)->get()` | Loads users with relationships |
| **Response** | `UserResource::collection()` | 200 OK | Array of user objects |

---

### M1-LVL — User Level Management (5 endpoints)

#### 11. GET /api/levels

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/levels` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `UserLevelController.php` | `index()` | Returns all levels ordered by hierarchy rank |
| **Model** | `UserLevel.php` | `UserLevel::with('tiers')->orderBy('hierarchy_rank')->get()` | Loads tiers for each level |
| **Response** | `UserLevelResource::collection()` | 200 OK | Array of `{id, code, name, hierarchy_rank, description, tiers}` |

---

#### 12. POST /api/levels/create

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `POST /api/levels/create` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `UserLevelController.php` | `store(Request $request)` | Creates new level |
| **Validation** | Inline | `validate(['code'=>'required\|string\|max:50\|unique:user_levels', 'name'=>'required\|string\|max:100', 'hierarchy_rank'=>'required\|integer\|min:1'])` | Returns 422 if invalid |
| **Model** | `UserLevel.php` | `UserLevel::create($validated)` | Creates level record |
| **Response** | `UserLevelResource` | 201 Created | `{id, code, name, hierarchy_rank, description, tiers}` |

---

#### 13. GET /api/levels/{userLevel}

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/levels/{userLevel}` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `UserLevelController.php` | `show(UserLevel $userLevel)` | Returns single level with tiers |
| **Model** | **Implicit** + `load('tiers')` | Route model binding + eager load | |
| **Response** | `UserLevelResource` | 200 OK | Level object with all tiers |

---

#### 14. PUT /api/levels/{userLevel}/update

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `PUT /api/levels/{userLevel}/update` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `UserLevelController.php` | `update(Request $request, UserLevel $userLevel)` | Updates level fields |
| **Validation** | Inline | `validate(['code'=>'sometimes\|required\|string\|unique:user_levels,code,' . $userLevel->id, ...])` | Excludes current record from unique check |
| **Model** | `UserLevel.php` | `$userLevel->update($validated)` | Updates record |
| **Response** | `UserLevelResource` | 200 OK | Updated level |

---

#### 15. DELETE /api/levels/{userLevel}/delete

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `DELETE /api/levels/{userLevel}/delete` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `UserLevelController.php` | `destroy(UserLevel $userLevel)` | Deletes level if no users assigned |
| **Business Logic** | `UserLevelController` | Checks `$userLevel->users()->exists()` | Prevents delete if users exist |
| **Response** | JSON | 200 OK | `{message: 'Level deleted successfully.'}` |
| **Errors** | JSON | 422 | `{message: 'Cannot delete level that has users assigned.'}` |

---

### M1-TIER — User Level Tier Management (5 endpoints)

#### 16. GET /api/levels/{userLevel}/tiers

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/levels/{userLevel}/tiers` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `UserLevelTierController.php` | `index(UserLevel $userLevel)` | Returns all tiers for this level |
| **Model** | `UserLevel.php` | `$userLevel->tiers` | Direct relationship access |
| **Response** | JSON | 200 OK | Array of `{id, tier_name, tier_order, description}` |

---

#### 17. POST /api/levels/{userLevel}/tiers/create

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `POST /api/levels/{userLevel}/tiers/create` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `UserLevelTierController.php` | `store(Request $request, UserLevel $userLevel)` | Creates new tier under level |
| **Validation** | Inline | `validate(['tier_name'=>'required\|string\|max:100', 'tier_order'=>'required\|integer\|min:1'])` | Returns 422 if invalid |
| **Model** | `UserLevel.php` | `$userLevel->tiers()->create($validated)` | Creates tier as child of level |
| **Response** | JSON | 201 Created | `{id, tier_name, tier_order, description}` |

---

#### 18. GET /api/levels/{userLevel}/tiers/{tier}

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/levels/{userLevel}/tiers/{tier}` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `UserLevelTierController.php` | `show(UserLevel $userLevel, UserLevelTier $tier)` | Returns single tier with validation |
| **Business Logic** | `UserLevelTierController` | `abort_if($tier->user_level_id !== $userLevel->id, 404)` | Ensures tier belongs to level |
| **Response** | JSON | 200 OK | `{id, tier_name, tier_order, description}` |

---

#### 19. PUT /api/levels/{userLevel}/tiers/{tier}/update

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `PUT /api/levels/{userLevel}/tiers/{tier}/update` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `UserLevelTierController.php` | `update(Request $request, UserLevel $userLevel, UserLevelTier $tier)` | Updates tier fields |
| **Business Logic** | `UserLevelTierController` | `abort_if($tier->user_level_id !== $userLevel->id, 404)` | Validates tier belongs to level |
| **Validation** | Inline | `validate(['tier_name'=>'sometimes\|required\|string', 'tier_order'=>'sometimes\|required\|integer\|min:1'])` | Returns 422 if invalid |
| **Model** | `UserLevelTier.php` | `$tier->update($validated)` | Updates tier |
| **Response** | JSON | 200 OK | Updated tier object |

---

#### 20. DELETE /api/levels/{userLevel}/tiers/{tier}/delete

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `DELETE /api/levels/{userLevel}/tiers/{tier}/delete` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `UserLevelTierController.php` | `destroy(UserLevel $userLevel, UserLevelTier $tier)` | Deletes tier if no users assigned |
| **Business Logic** | `UserLevelTierController` | Checks `$tier->users()->exists()` | Prevents delete if users exist |
| **Response** | JSON | 200 OK | `{message: 'Tier deleted successfully.'}` |
| **Errors** | JSON | 422 | `{message: 'Cannot delete tier that has users assigned.'}` |

---

### M1-USR — User Management (7 endpoints)

#### 21. GET /api/users

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/users` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `UserController.php` | `index()` | Returns all users with relationships |
| **Model** | `User.php` | `User::with(['department', 'level', 'tier', 'roles'])->get()` | Eager loads all relationships |
| **Response** | `UserResource::collection()` | 200 OK | Array of users with full context |

---

#### 22. POST /api/users/create

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `POST /api/users/create` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `UserController.php` | `store(Request $request)` | Creates new user with auto role sync |
| **Validation** | Inline | `validate(['name'=>'required\|string', 'email'=>'required\|email\|unique:users', 'password'=>'required\|string\|min:8', 'department_id'=>'required\|exists:departments,id', 'user_level_id'=>'required\|exists:user_levels,id', 'is_admin'=>'boolean'])` | Returns 422 if invalid |
| **Business Logic** | `UserController::syncLevelRole()` | Finds level by ID, creates Spatie role if not exists, calls `syncRoles([$level->code])` | Auto-assigns level code role |
| **Model** | `User.php` + `Role.php` | `User::create()` then `Role::firstOrCreate()` then `syncRoles()` | Creates user, role, and assigns role |
| **Response** | `UserResource` | 201 Created | User with all relationships loaded |

---

#### 23. GET /api/users/{user}

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/users/{user}` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `UserController.php` | `show(User $user)` | Returns single user |
| **Model** | **Implicit** + `load(['department', 'level', 'tier', 'roles'])` | Route model binding + eager load | |
| **Response** | `UserResource` | 200 OK | User object |

---

#### 24. PUT /api/users/{user}/update

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `PUT /api/users/{user}/update` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `UserController.php` | `update(Request $request, User $user)` | Updates user; re-syncs role if level changed |
| **Validation** | Inline | `validate(['email'=>'sometimes\|required\|email\|unique:users,email,' . $user->id, ...])` | Excludes current user from unique email check |
| **Business Logic** | `UserController` | Detects level change: `$levelChanged = isset($validated['user_level_id']) && $validated['user_level_id'] != $user->user_level_id`. If true, calls `syncLevelRole($user)` | Re-syncs role when level is updated |
| **Model** | `User.php` | `$user->update($validated)` then conditionally `syncRoles()` | Updates user, re-syncs role if needed |
| **Response** | `UserResource` | 200 OK | Updated user |

---

#### 25. DELETE /api/users/{user}/delete

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `DELETE /api/users/{user}/delete` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `UserController.php` | `destroy(User $user)` | Deletes user |
| **Model** | `User.php` | `$user->delete()` | Soft or hard delete depending on model |
| **Response** | JSON | 200 OK | `{message: 'User deleted successfully.'}` |

---

### M1-ROLE — Role Management (6 endpoints)

#### 26. GET /api/roles

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/roles` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `RoleController.php` | `index()` | Returns all roles with permissions |
| **Model** | `Role.php` (Spatie) | `Role::with('permissions')->get()->map(...)` | Loads roles and maps to response shape |
| **Response** | JSON | 200 OK | Array of `{id, name, permissions: [permission names]}` |

---

#### 27. POST /api/roles/create

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `POST /api/roles/create` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `RoleController.php` | `store(Request $request)` | Creates new role |
| **Validation** | Inline | `validate(['name'=>'required\|string\|unique:roles,name'])` | Returns 422 if invalid |
| **Model** | `Role.php` (Spatie) | `Role::create(['name'=>$validated['name'], 'guard_name'=>'web'])` | Creates role with web guard |
| **Response** | JSON | 201 Created | `{id, name, permissions: []}` |

---

#### 28. GET /api/roles/{role}

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/roles/{role}` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `RoleController.php` | `show(Role $role)` | Returns single role with permissions |
| **Model** | **Implicit** + `with('permissions')` | Route model binding + load permissions | |
| **Response** | JSON | 200 OK | `{id, name, permissions: [permission names]}` |

---

#### 29. PUT /api/roles/{role}/update

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `PUT /api/roles/{role}/update` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `RoleController.php` | `update(Request $request, Role $role)` | Updates role name |
| **Validation** | Inline | `validate(['name'=>'required\|string\|unique:roles,name,' . $role->id])` | Excludes current role from unique check |
| **Model** | `Role.php` | `$role->update($validated)` | Updates name |
| **Response** | JSON | 200 OK | Updated role object |

---

#### 30. DELETE /api/roles/{role}/delete

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `DELETE /api/roles/{role}/delete` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `RoleController.php` | `destroy(Role $role)` | Deletes role |
| **Model** | `Role.php` | `$role->delete()` | Deletes from database |
| **Response** | JSON | 200 OK | `{message: 'Role deleted successfully.'}` |

---

#### 31. POST /api/roles/{role}/permissions

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `POST /api/roles/{role}/permissions` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `RoleController.php` | `assignPermissions(Request $request, Role $role)` | Syncs permissions to role |
| **Validation** | Inline | `validate(['permissions'=>'required\|array', 'permissions.*'=>'string\|exists:permissions,name'])` | Each permission must exist in DB. Returns 422 if invalid |
| **Business Logic** | `RoleController` | `$role->syncPermissions($validated['permissions'])` | Replaces role's permissions with provided list |
| **Response** | JSON | 200 OK | `{message: 'Permissions assigned successfully.', role: name, permissions: [names]}` |

---

### M1-PERM — Permission Management (5 endpoints)

#### 32. GET /api/permissions

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/permissions` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `PermissionController.php` | `index()` | Returns all permissions |
| **Model** | `Permission.php` (Spatie) | `Permission::all(['id', 'name'])` | Returns id and name |
| **Response** | JSON | 200 OK | Array of `{id, name}` |

---

#### 33. POST /api/permissions/create

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `POST /api/permissions/create` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `PermissionController.php` | `store(Request $request)` | Creates new permission |
| **Validation** | Inline | `validate(['name'=>'required\|string\|unique:permissions,name'])` | Returns 422 if invalid |
| **Model** | `Permission.php` (Spatie) | `Permission::create(['name'=>$validated['name'], 'guard_name'=>'web'])` | Creates permission |
| **Response** | JSON | 201 Created | `{id, name}` |

---

#### 34. GET /api/permissions/{permission}

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/permissions/{permission}` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `PermissionController.php` | `show(Permission $permission)` | Returns single permission |
| **Model** | **Implicit** | Route model binding | |
| **Response** | JSON | 200 OK | `{id, name}` |

---

#### 35. PUT /api/permissions/{permission}/update

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `PUT /api/permissions/{permission}/update` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `PermissionController.php` | `update(Request $request, Permission $permission)` | Updates permission name |
| **Validation** | Inline | `validate(['name'=>'required\|string\|unique:permissions,name,' . $permission->id])` | Excludes current from unique check |
| **Model** | `Permission.php` | `$permission->update($validated)` | Updates name |
| **Response** | JSON | 200 OK | Updated permission object |

---

#### 36. DELETE /api/permissions/{permission}/delete

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `DELETE /api/permissions/{permission}/delete` | Protected: `middleware(['auth:sanctum', 'is_admin'])` |
| **Controller** | `PermissionController.php` | `destroy(Permission $permission)` | Deletes permission |
| **Model** | `Permission.php` | `$permission->delete()` | Deletes from database |
| **Response** | JSON | 200 OK | `{message: 'Permission deleted successfully.'}` |

---

## M1 Summary

| Category | Endpoints | Status |
|---|---|---|
| Auth | 3 | ✅ Implemented & tested |
| Departments | 7 | ✅ Implemented & tested |
| Levels | 5 | ✅ Implemented & tested |
| Tiers | 5 | ✅ Implemented & tested |
| Users | 5 | ✅ Implemented & tested |
| Roles | 6 | ✅ Implemented & tested |
| Permissions | 5 | ✅ Implemented & tested |
| **Total** | **36** | **101 tests (all passing)** |

---

---

## M2 — Manager Schedule System

**Status: ⬜ PENDING DEVELOPMENT**
**Total Endpoints: 12**
**Middleware: `auth:sanctum` + `role:manager` (Spatie — user must have L2/L2PM/L3/L4/L5/L6 role)**

> All M2 endpoints are department-scoped. The system automatically filters all queries to `WHERE department_id = auth()->user()->department_id`. A manager can never read or write data for another department.

---

### M2-SCHEDULE — Weekly Schedule Grid (4 endpoints)

#### 37. GET /api/schedules

**Purpose:** Load the full weekly schedule grid for the manager's department.

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/schedules` | Protected: `middleware(['auth:sanctum', 'role:manager'])` |
| **Controller** | `ScheduleController.php` | `index(Request $request)` | Receives query: `week_start` (YYYY-MM-DD, Monday). Defaults to current week if not provided |
| **Validation** | Inline | `validate(['week_start' => 'nullable\|date\|date_format:Y-m-d'])` | Returns 422 if invalid date |
| **Business Logic** | `ScheduleController` | Computes `week_end = week_start + 6 days`. Filters to manager's `department_id` | Ensures Monday-to-Sunday boundary |
| **Model** | `ScheduleAssignment.php` | `ScheduleAssignment::with(['employee', 'shift', 'coveredEmployee', 'changes'])->where('department_id', $deptId)->whereBetween('date', [$weekStart, $weekEnd])->get()` | Returns all assignments for the week |
| **Response** | `ScheduleResource` | 200 OK | Full grid: employees × days, each cell has assignment or null |
| **Errors** | — | 403 | If user does not have a manager-level role |

> **M2-FILT-01 note:** Grid type filtering (Working / Day Off / Sick Day / Leave Request / Modified) is handled **client-side**. Every assignment in the response includes `type` and `has_changes`, which is all the data the frontend needs to filter without a second request. No `filter_type` query param is required on this endpoint.

**Response JSON — 200 OK:**
```json
{
  "week_start": "2026-03-30",
  "week_end": "2026-04-05",
  "published": false,
  "summary": {
    "working": 18, "day_off": 3, "sick_day": 1, "leave_request": 0, "unassigned": 6
  },
  "employees": [
    {
      "id": 5, "name": "Ahmed Hassan", "level": "L2", "tier": "Tier 1",
      "assignments": {
        "2026-03-30": {"id": 101, "type": "shift", "shift_id": 2, "shift_name": "Shift B", "start": "09:00", "end": "17:00", "is_cover": false, "covered_employee": null, "has_changes": false},
        "2026-03-31": null,
        "2026-04-01": {"id": 102, "type": "day_off", "shift_id": null, "shift_name": null, "start": null, "end": null, "is_cover": false, "covered_employee": null, "has_changes": false}
      }
    }
  ]
}
```

---

#### 38. GET /api/schedules/day

**Purpose:** Load the Day Detail View — all employees for one specific day.

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/schedules/day` | Protected: `middleware(['auth:sanctum', 'role:manager'])` |
| **Controller** | `ScheduleController.php` | `day(Request $request)` | Receives query: `date` (YYYY-MM-DD) |
| **Validation** | Inline | `validate(['date' => 'required\|date'])` | Returns 422 if missing |
| **Model** | `ScheduleAssignment.php` | `ScheduleAssignment::with(['employee', 'shift', 'coveredEmployee'])->where('department_id', $deptId)->where('date', $date)->get()` | Single day, all employees |
| **Response** | JSON | 200 OK | Array of employees with their assignment for the day, plus summary counts |

**Response JSON — 200 OK:**
```json
{
  "date": "2026-03-30",
  "summary": {"working": 8, "day_off": 1, "sick_day": 0, "leave_request": 1, "unassigned": 2},
  "employees": [
    {
      "id": 5, "name": "Ahmed Hassan", "level": "L2",
      "assignment": {"id": 101, "type": "shift", "shift_name": "Shift B", "start": "09:00", "end": "17:00", "is_cover": false, "covered_employee": null}
    }
  ]
}
```

---

#### 39. GET /api/schedules/export

**Purpose:** Export the current week's schedule as a CSV file.

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/schedules/export` | Protected: `middleware(['auth:sanctum', 'role:manager'])` |
| **Controller** | `ScheduleController.php` | `export(Request $request)` | Receives query: `week_start` |
| **Validation** | Inline | `validate(['week_start' => 'required\|date'])` | Returns 422 if missing |
| **Business Logic** | `ScheduleController` | Builds CSV rows: employee name + shift name/hours per day for all 7 days | Uses `League\Csv` or `fputcsv` |
| **Response** | StreamedResponse | 200 OK | File download: `Content-Type: text/csv`, `Content-Disposition: attachment; filename="schedule-{week_start}.csv"` |

**CSV Structure:**
```
Employee,Level,Mon 30/03,Tue 31/03,Wed 01/04,Thu 02/04,Fri 03/04,Sat 04/04,Sun 05/04
Ahmed Hassan,L2,Shift B (09:00-17:00),Shift B (09:00-17:00),Day Off,,,,
```

---

#### 40. GET /api/schedules/publish-status

**Purpose:** Check whether the current week's schedule is Draft or Published.

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/schedules/publish-status` | Protected: `middleware(['auth:sanctum', 'role:manager'])` |
| **Controller** | `ScheduleController.php` | `publishStatus(Request $request)` | Receives query: `week_start` |
| **Model** | `WeeklySchedule.php` | `WeeklySchedule::where('department_id', $deptId)->where('week_start', $weekStart)->first()` | Reads `status` field: `draft` or `published` |
| **Response** | JSON | 200 OK | `{week_start, published: bool, published_at: timestamp or null, published_by: user name or null}` |

---

### M2-ASSIGN — Shift Assignment (4 endpoints)

#### 41. POST /api/schedules/assignments/create

**Purpose:** Assign a shift or off-type to a single employee for a single day.

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `POST /api/schedules/assignments/create` | Protected: `middleware(['auth:sanctum', 'role:manager'])` |
| **Controller** | `ScheduleAssignmentController.php` | `store(Request $request)` | Creates a new assignment |
| **Validation** | Inline | `validate(['employee_id'=>'required\|exists:users,id', 'date'=>'required\|date', 'type'=>'required\|in:shift,day_off,sick_day,leave_request', 'shift_id'=>'required_if:type,shift\|exists:shifts,id', 'is_cover'=>'boolean', 'covered_employee_id'=>'required_if:is_cover,true\|exists:users,id', 'comment'=>'nullable\|string\|max:1000'])` | Returns 422 if invalid |
| **Business Logic** | `ScheduleAssignmentController` | Verifies `employee_id` belongs to manager's department. If `is_cover=true`, verifies `covered_employee_id` also belongs to same department | Uses `abort_if` for 403 |
| **Model** | `ScheduleAssignment.php` | `ScheduleAssignment::create([...])` + `AssignmentChange::create([..., 'previous_value'=>null, 'new_value'=>$type, 'changed_by'=>auth()->id()])` | Two writes in one `DB::transaction()` |
| **Response** | JSON | 201 Created | Full assignment object with change history |
| **Errors** | — | 409 Conflict | If an assignment already exists for this employee + date (use update instead) |

> **M2-PUB-04 note:** This endpoint does NOT check `WeeklySchedule.status`. Creating (and editing) assignments is permitted regardless of whether the schedule is Draft or Published. Every write is still logged in `AssignmentChange`.

**Response JSON — 201 Created:**
```json
{
  "id": 201,
  "employee_id": 5, "employee_name": "Ahmed Hassan",
  "date": "2026-03-30",
  "type": "shift",
  "shift": {"id": 2, "name": "Shift B", "start": "09:00", "end": "17:00"},
  "is_cover": true,
  "covered_employee": {"id": 8, "name": "Khalid Mohammed"},
  "comment": "Covering Khalid who is on leave.",
  "has_changes": true,
  "changes": [
    {"id": 1, "previous": null, "new": "shift:Shift B", "changed_by": "Manager Sara", "changed_at": "2026-03-30T08:00:00Z", "comment": "Covering Khalid who is on leave."}
  ]
}
```

---

#### 42. PUT /api/schedules/assignments/{assignment}/update

**Purpose:** Edit an existing shift assignment (change shift, off-type, cover details, comment).

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `PUT /api/schedules/assignments/{assignment}/update` | Protected: `middleware(['auth:sanctum', 'role:manager'])` |
| **Controller** | `ScheduleAssignmentController.php` | `update(Request $request, ScheduleAssignment $assignment)` | Updates assignment; logs change |
| **Validation** | Inline | Same fields as create, all `sometimes\|required` | Returns 422 if invalid |
| **Business Logic** | `ScheduleAssignmentController` | Verifies `assignment->department_id === auth()->user()->department_id`. Stores `$prevValue` before update. Logs change record with before/after | Uses `DB::transaction()` |
| **Model** | `ScheduleAssignment.php` + `AssignmentChange.php` | `$assignment->update([...])` + `AssignmentChange::create([..., 'previous_value'=>$prev, 'new_value'=>$new])` | Atomic transaction — both succeed or both roll back |
| **Response** | JSON | 200 OK | Updated assignment with appended change history entry |

> **M2-PUB-04 note:** This endpoint does NOT check `WeeklySchedule.status`. Editing an assignment is permitted regardless of Draft or Published state — per requirement M2-PUB-04. All edits are still logged in `AssignmentChange`.

---

#### 43. DELETE /api/schedules/assignments/{assignment}/delete

**Purpose:** Clear an assignment (return cell to unassigned/empty state).

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `DELETE /api/schedules/assignments/{assignment}/delete` | Protected: `middleware(['auth:sanctum', 'role:manager'])` |
| **Controller** | `ScheduleAssignmentController.php` | `destroy(ScheduleAssignment $assignment)` | Deletes assignment record |
| **Business Logic** | `ScheduleAssignmentController` | Verifies `assignment->department_id === auth()->user()->department_id` | Returns 403 if wrong department |
| **Model** | `ScheduleAssignment.php` | `$assignment->delete()` + logs deletion in `AssignmentChange` | Change record preserved even after assignment is deleted |
| **Response** | JSON | 200 OK | `{message: 'Assignment cleared.', date: '...', employee_id: N}` |

---

#### 44. GET /api/schedules/assignments/{assignment}/history

**Purpose:** Return the full change history for a single assignment cell.

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/schedules/assignments/{assignment}/history` | Protected: `middleware(['auth:sanctum', 'role:manager'])` |
| **Controller** | `ScheduleAssignmentController.php` | `history(ScheduleAssignment $assignment)` | Returns all change records for the assignment |
| **Model** | `AssignmentChange.php` | `AssignmentChange::with('changedBy')->where('assignment_id', $assignment->id)->orderBy('changed_at', 'asc')->get()` | Ordered oldest to newest |
| **Response** | JSON | 200 OK | Array of change entries: `{id, previous_value, new_value, changed_by, changed_at, comment}` |

---

### M2-BULK — Bulk Assignment (1 endpoint)

#### 45. POST /api/schedules/assignments/bulk-create

**Purpose:** Assign one shift to many employees (by row) or one employee to many days (by column) in a single request.

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `POST /api/schedules/assignments/bulk-create` | Protected: `middleware(['auth:sanctum', 'role:manager'])` |
| **Controller** | `ScheduleAssignmentController.php` | `bulkCreate(Request $request)` | Handles both bulk-by-employees and bulk-by-days |
| **Validation** | Inline | `validate(['mode'=>'required\|in:by_employees,by_days', 'employee_ids'=>'required_if:mode,by_employees\|array', 'employee_ids.*'=>'exists:users,id', 'dates'=>'required_if:mode,by_days\|array', 'dates.*'=>'date', 'type'=>'required\|in:shift,day_off,sick_day,leave_request', 'shift_id'=>'required_if:type,shift\|exists:shifts,id'])` | Returns 422 with per-field errors |
| **Business Logic** | `ScheduleAssignmentController` | Verifies every `employee_id` belongs to manager's department. Iterates targets and creates/updates each assignment. Wraps in `DB::transaction()` — all succeed or none do | Logs one `AssignmentChange` per affected cell |
| **Model** | `ScheduleAssignment.php` | `ScheduleAssignment::updateOrCreate(['employee_id'=>$id,'date'=>$date], [...])` per target | Upsert per cell to handle overwrite of existing assignments |
| **Response** | JSON | 200 OK | `{processed: N, failed: M, assignments: [...]}` |

**Response JSON — 200 OK:**
```json
{
  "processed": 8,
  "failed": 0,
  "assignments": [
    {"employee_id": 5, "date": "2026-03-30", "type": "shift", "shift_name": "Shift A"},
    {"employee_id": 6, "date": "2026-03-30", "type": "shift", "shift_name": "Shift A"}
  ]
}
```

---

### M2-COPY — Copy Previous Week (1 endpoint)

#### 46. POST /api/schedules/copy-last-week

**Purpose:** Duplicate all assignments from the previous week into the current week.

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `POST /api/schedules/copy-last-week` | Protected: `middleware(['auth:sanctum', 'role:manager'])` |
| **Controller** | `ScheduleController.php` | `copyLastWeek(Request $request)` | Performs copy operation |
| **Validation** | Inline | `validate(['week_start' => 'required\|date'])` | Target week must be provided |
| **Business Logic** | `ScheduleController` | Computes `prev_week_start = week_start - 7 days`. Loads all assignments for previous week. Inserts copies for current week. Does NOT overwrite existing current-week assignments. Wraps in `DB::transaction()` | Idempotent — safe to call multiple times |
| **Model** | `ScheduleAssignment.php` | Read: `ScheduleAssignment::where('department_id', $deptId)->whereBetween('date', [$prevStart, $prevEnd])->get()` then for each: `ScheduleAssignment::firstOrCreate(['employee_id'=>$a->employee_id, 'date'=>$newDate], [...])` | `firstOrCreate` prevents duplicate writes |
| **Response** | JSON | 200 OK | `{copied: N, skipped: N (already existed), week_start: '...'}` |
| **Errors** | — | 404 | If previous week has no assignments to copy |

---

### M2-PUBLISH — Schedule Publishing (1 endpoint)

#### 47. POST /api/schedules/publish

**Purpose:** Publish the current week's schedule, making it visible to the Compliance module (M3).

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `POST /api/schedules/publish` | Protected: `middleware(['auth:sanctum', 'role:manager'])` |
| **Controller** | `ScheduleController.php` | `publish(Request $request)` | Validates completeness, then publishes |
| **Validation** | Inline | `validate(['week_start' => 'required\|date'])` | Returns 422 if missing |
| **Business Logic** | `ScheduleController` | Step 1: Load all employees in department. Step 2: For every employee × every day (7 days): verify a non-null assignment exists. Step 3: If any cell is empty, return 422 with list of missing assignments. Step 4: If complete, upsert `WeeklySchedule` with `status = published`, `published_at = now()`, `published_by = auth()->id()` | Returns 422 with detailed gap list if incomplete |
| **Model** | `WeeklySchedule.php` | `WeeklySchedule::updateOrCreate(['department_id'=>$deptId,'week_start'=>$weekStart], ['status'=>'published','published_at'=>now(),'published_by'=>auth()->id()])` | Creates or updates the week header record |
| **Response** | JSON | 200 OK | `{message: 'Schedule published.', week_start, published_at, published_by}` |
| **Errors** | — | 422 | `{message: 'Schedule is incomplete.', missing: [{employee_id, employee_name, dates: ['2026-03-31', ...]}]}` |

**Response JSON — 422 (incomplete):**
```json
{
  "message": "Schedule is incomplete. All employees must have an assignment for every day before publishing.",
  "missing": [
    {"employee_id": 8, "employee_name": "Khalid Mohammed", "dates": ["2026-03-31", "2026-04-02"]}
  ]
}
```

---

### M2-SHIFTS — Shift Definitions Read (1 endpoint)

> Shifts are defined and managed in M3 (Compliance). M2 only reads them for the assignment form picker.

#### 48. GET /api/shifts

**Purpose:** Return all company-wide shift definitions for the assignment form.

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/shifts` | Protected: `middleware(['auth:sanctum', 'role:manager'])` |
| **Controller** | `ShiftController.php` | `index()` | Returns all shifts |
| **Model** | `Shift.php` | `Shift::orderBy('start_time')->get()` | Ordered by start time |
| **Response** | JSON | 200 OK | Array of `{id, name, start_time, end_time, duration_hours, is_overnight}` |

**Response JSON — 200 OK:**
```json
[
  {"id": 1, "name": "Shift A", "start_time": "06:00", "end_time": "14:00", "duration_hours": 8, "is_overnight": false},
  {"id": 4, "name": "Shift D", "start_time": "22:00", "end_time": "06:00", "duration_hours": 8, "is_overnight": true}
]
```

---

### M2-EMAIL — Automated Reminders (background jobs, no HTTP endpoints)

> These are scheduled jobs, not HTTP endpoints callable by users. They are defined here for completeness.

| Job | File / Class | Trigger | What it does |
|---|---|---|---|
| **Monday Reminder** | `SendMondayScheduleReminder.php` | Laravel Scheduler: every Monday at 12:00 | Finds all managers (users with L2+ role). For each: checks if a `WeeklySchedule` record exists for current week. If not: sends `ScheduleReminderMail` to manager's email |
| **Tuesday Reminder + Auto-Copy** | `SendTuesdayReminderAndAutoCopy.php` | Laravel Scheduler: every Tuesday at 12:00 | Same check. If schedule still missing: sends second `ScheduleReminderMail`. Then calls `copyLastWeek()` logic to auto-populate current week from previous week. Logs action in job log |
| **Queue Worker** | Laravel Queue | Background daemon | Processes mail dispatch jobs asynchronously. Failed jobs stored in `failed_jobs` table |

---

## M2 Summary

| Category | Endpoints | Status |
|---|---|---|
| Schedule Grid | 4 | ⬜ Pending |
| Shift Assignment | 4 | ⬜ Pending |
| Bulk Assignment | 1 | ⬜ Pending |
| Copy Last Week | 1 | ⬜ Pending |
| Publish | 1 | ⬜ Pending |
| Shifts Read | 1 | ⬜ Pending |
| Background Jobs | 2 jobs | ⬜ Pending |
| **Total HTTP** | **12** | ⬜ Pending |

### New DB Tables Required for M2

| Table | Purpose |
|---|---|
| `weekly_schedules` | One row per department per week. Holds `status` (draft/published), `published_at`, `published_by` |
| `schedule_assignments` | One row per employee per day. Holds `type`, `shift_id`, `is_cover`, `covered_employee_id`, `department_id` |
| `assignment_changes` | Append-only log. One row per change to any assignment. Holds before/after values, who changed it, when, and comment |
| `shifts` | Company-wide shift definitions. Holds `name`, `start_time`, `end_time`, `is_overnight`. Managed by M3 |

---

---

## M3 — Audit & Compliance System

**Status: ⬜ Pending Development**
**Total HTTP Endpoints: 11**
**Background Jobs: 0 (import is synchronous)**
**Middleware: `auth:sanctum` + `role:compliance` on all endpoints**

> Covers requirements: M3-GRID, M3-STAT, M3-IMP, M3-MODAL, M3-OVER, M3-FILT, M3-SHFT, M3-MGR
> M3-MGR-01 enforcement: managers do NOT have the `compliance` role — they are blocked from every endpoint below at the middleware level.

---

### M3-GRID + M3-STAT + M3-FILT — Audit Grid (2 endpoints)

#### 49. GET /api/audit/grid

**Purpose:** Return the weekly audit grid — every employee × every day — with computed attendance status for each cell. Covers M3-GRID-01 to 06, M3-STAT-01 to 09, M3-FILT-01 to 03, M3-OVER-01 to 03.

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/audit/grid` | Protected: `middleware(['auth:sanctum', 'role:compliance'])` |
| **Controller** | `AuditController.php` | `grid(Request $request)` | Receives query params: `week_start` (required), `department_id` (optional), `status` (optional: `on_time/late/absent/left_early/early_exit`), `search` (optional: employee name substring) |
| **Validation** | Inline | `validate(['week_start'=>'required\|date', 'department_id'=>'nullable\|exists:departments,id', 'status'=>'nullable\|in:on_time,late,absent,left_early,early_exit', 'search'=>'nullable\|string\|max:100'])` | Returns 422 if invalid |
| **Business Logic — Step 1** | `AuditService.php` | `buildGrid(string $weekStart, array $filters): array` | Load all published `schedule_assignments` for the week. If no published `WeeklySchedule` record found for any department in scope → return `{no_schedule: true}` (covers M3-STAT-09) |
| **Business Logic — Step 2** | `AuditService.php` | `computeStatus(Assignment $a, ?Fingerprint $f): array` | For each employee × day cell: look up matching `fingerprint_records` row. Then apply status rules in order: (1) If assignment type is `day_off / sick_day / leave_request` → status = off type label, skip computation (M3-STAT-08). (2) If no fingerprint record → status = `absent` (M3-STAT-07). (3) Compute `late_minutes = max(0, clockIn - shiftStart - 5min grace)`. (4) Compute `shift_duration_minutes`. (5) Compute `worked_minutes = clockOut - clockIn`. (6) Compute `60pct_threshold = shift_duration_minutes * 0.60`. (7) If `worked_minutes < 60pct_threshold` → status = `early_exit` (M3-STAT-05 — distinct color). (8) Elif `clockOut < shiftEnd` → status = `left_early` (M3-STAT-04 — purple). (9) If `late_minutes > 0` AND any left_early/early_exit → status = `late_and_left_early`, store both minute counts (M3-STAT-06). (10) If `late_minutes > 0` only → status = `late` (M3-STAT-03). (11) Else → status = `on_time` (M3-STAT-02). |
| **Overnight Logic** | `AuditService.php` | `isOvernight(Shift $s): bool` | `$s->end_time < $s->start_time`. If true: `duration = (24h - start) + end`. Status computed on combined duration. Cell flagged `is_overnight: true` (M3-OVER-01, M3-OVER-03) |
| **Model** | `ScheduleAssignment.php` + `FingerprintRecord.php` | `ScheduleAssignment::with(['employee','shift','changes'])->where('week_start', $weekStart)->whereIn('department_id', $deptIds)->get()` then `FingerprintRecord::whereIn('user_id', $employeeIds)->whereBetween('date', [$weekStart, $weekEnd])->get()` | Two queries, joined in PHP for status computation |
| **Filters** | `AuditService.php` | Applied after status computation | `department_id`: filter to one dept. `status`: keep only rows with matching computed status (non-matching rows returned with `faded: true`). `search`: `Str::contains(strtolower($employee->name), strtolower($search))` |
| **Response** | JSON | 200 OK | See response shape below |
| **Errors** | — | 404 | Week not found or no schedule published |

**Response JSON — 200 OK:**
```json
{
  "week_start": "2026-03-30",
  "week_end": "2026-04-05",
  "no_schedule": false,
  "summary": {
    "on_time": 120,
    "late": 18,
    "absent": 7,
    "left_early": 5,
    "early_exit": 2
  },
  "employees": [
    {
      "employee_id": 5,
      "employee_name": "Ahmed Hassan",
      "level": "L2",
      "department_id": 3,
      "department_name": "Dispatch Team A",
      "faded": false,
      "days": {
        "2026-03-30": {
          "assignment_id": 201,
          "type": "shift",
          "shift_id": 1,
          "shift_name": "Shift A",
          "shift_start": "06:00",
          "shift_end": "14:00",
          "is_overnight": false,
          "is_cover": false,
          "covered_employee_id": null,
          "has_changes": false,
          "clock_in": "06:03",
          "clock_out": "14:05",
          "status": "on_time",
          "late_minutes": 0,
          "early_minutes": 0
        },
        "2026-03-31": {
          "assignment_id": 202,
          "type": "shift",
          "shift_id": 2,
          "shift_name": "Shift B",
          "shift_start": "09:00",
          "shift_end": "17:00",
          "is_overnight": false,
          "is_cover": false,
          "covered_employee_id": null,
          "has_changes": true,
          "clock_in": "09:18",
          "clock_out": "14:30",
          "status": "late_and_left_early",
          "late_minutes": 13,
          "early_minutes": 150
        }
      }
    }
  ]
}
```

---

#### 50. GET /api/audit/grid/cell

**Purpose:** Return full detail for a single employee on a single day — for the cell detail modal. Covers M3-MODAL-01, M3-MODAL-02, M3-MODAL-03.

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/audit/grid/cell` | Protected: `middleware(['auth:sanctum', 'role:compliance'])` |
| **Controller** | `AuditController.php` | `cellDetail(Request $request)` | Receives: `employee_id`, `date` |
| **Validation** | Inline | `validate(['employee_id'=>'required\|exists:users,id', 'date'=>'required\|date'])` | Returns 422 if invalid |
| **Business Logic** | `AuditService.php` | `getCellDetail(int $employeeId, string $date): array` | Load assignment for this employee + date. Load fingerprint record. Compute status using same logic as EP 49. Load full `assignment_changes` for this cell. |
| **Model** | `ScheduleAssignment.php` | `ScheduleAssignment::with(['shift','coveredEmployee','changes.changedBy'])->where('employee_id',$id)->where('date',$date)->firstOrFail()` | Loads all nested relationships |
| **Response** | JSON | 200 OK | See response below |

**Response JSON — 200 OK:**
```json
{
  "employee_id": 5,
  "employee_name": "Ahmed Hassan",
  "level": "L2",
  "date": "2026-03-31",
  "planned": {
    "shift_name": "Shift B",
    "start_time": "09:00",
    "end_time": "17:00",
    "duration_hours": 8,
    "is_overnight": false,
    "type": "shift",
    "is_cover": false,
    "covered_employee": null
  },
  "actual": {
    "clock_in": "09:18",
    "clock_out": "14:30",
    "hours_worked": 5.2
  },
  "status": "late_and_left_early",
  "late_minutes": 13,
  "early_minutes": 150,
  "change_history": [
    {
      "id": 12,
      "changed_by": "Sara Manager",
      "changed_at": "2026-03-29T10:15:00Z",
      "previous_type": "day_off",
      "new_type": "shift",
      "previous_shift": null,
      "new_shift": "Shift B",
      "comment": "Employee swap confirmed by HR."
    }
  ]
}
```

---

### M3-IMP — Fingerprint Data Import (2 endpoints)

#### 51. POST /api/audit/fingerprint/import

**Purpose:** Upload a CSV or Excel file containing fingerprint clock-in/clock-out records. Covers M3-IMP-01 to 05.

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `POST /api/audit/fingerprint/import` | Protected: `middleware(['auth:sanctum', 'role:compliance'])` |
| **Controller** | `FingerprintController.php` | `import(Request $request)` | Receives multipart form upload |
| **Validation** | Inline | `validate(['file'=>'required\|file\|mimes:csv,xlsx,xls\|max:10240', 'week_start'=>'required\|date'])` | Returns 422 if file missing, wrong type, or too large |
| **Business Logic — Step 1** | `FingerprintImportService.php` | `parseFile(UploadedFile $file): array` | Detect CSV vs XLSX. Parse file using `League\Csv` (CSV) or `PhpSpreadsheet` (XLSX). Extract columns: `employee_identifier`, `date`, `clock_in`, `clock_out`. Required columns: must exist or return 422 with column-level error |
| **Business Logic — Step 2** | `FingerprintImportService.php` | `matchEmployees(array $rows): array` | For each row: match `employee_identifier` against `users.email` or `users.id`. Collect unmatched identifiers into `$errors` array (does not abort — continues processing valid rows) |
| **Business Logic — Step 3** | `FingerprintImportService.php` | `persist(array $validRows, string $weekStart): int` | Wrap in `DB::transaction()`. For each valid row: `FingerprintRecord::updateOrCreate(['user_id'=>$id,'date'=>$row['date']], ['clock_in'=>$row['clock_in'],'clock_out'=>$row['clock_out']])`. Atomic — all or nothing (M3-IMP-02, M3-IMP-05) |
| **Business Logic — Step 4** | `FingerprintImportService.php` | `recomputeStatuses(string $weekStart): void` | After successful persist: invalidate or recompute cached audit statuses for the affected week. Subsequent calls to EP 49 will now reflect the new data (M3-IMP-04) |
| **Model** | `FingerprintRecord.php` | `updateOrCreate()` on `fingerprint_records` table | Idempotent: re-uploading same week replaces existing records |
| **Response** | JSON | 200 OK | See below |
| **Errors** | — | 422 | File format invalid or required columns missing |

**Response JSON — 200 OK:**
```json
{
  "message": "Import successful.",
  "week_start": "2026-03-30",
  "imported": 198,
  "skipped": 3,
  "errors": [
    {"row": 45, "identifier": "unknown_emp_99", "reason": "Employee not found in system"},
    {"row": 61, "identifier": "ahmed@nvt.com", "reason": "Missing clock_out value"}
  ]
}
```

**Response JSON — 422 (wrong column structure):**
```json
{
  "message": "File format invalid.",
  "errors": {
    "file": ["Required columns missing: clock_in, clock_out. Found: [name, date, entry_time]"]
  }
}
```

---

#### 52. GET /api/audit/fingerprint/records

**Purpose:** List all imported fingerprint records for a given week (for verification/debug by Compliance team).

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/audit/fingerprint/records` | Protected: `middleware(['auth:sanctum', 'role:compliance'])` |
| **Controller** | `FingerprintController.php` | `index(Request $request)` | Receives: `week_start`, optional `employee_id`, optional `department_id` |
| **Validation** | Inline | `validate(['week_start'=>'required\|date', 'employee_id'=>'nullable\|exists:users,id', 'department_id'=>'nullable\|exists:departments,id'])` | Returns 422 if invalid |
| **Model** | `FingerprintRecord.php` | `FingerprintRecord::with('employee')->whereBetween('date', [$weekStart, $weekEnd])->when($employeeId, fn($q) => $q->where('user_id', $employeeId))->get()` | Filtered query |
| **Response** | JSON | 200 OK | `[{user_id, employee_name, date, clock_in, clock_out}]` |

---

### M3-SHFT — Shift Management (4 endpoints)

#### 53. GET /api/shifts/manage

**Purpose:** List all company-wide shift definitions for the Shifts Management tab. Covers M3-SHFT-01, M3-SHFT-02, M3-SHFT-06.

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `GET /api/shifts/manage` | Protected: `middleware(['auth:sanctum', 'role:compliance'])` |
| **Controller** | `ShiftController.php` | `index()` | No params required |
| **Model** | `Shift.php` | `Shift::orderBy('start_time')->get()` | All shifts ordered by start time |
| **Business Logic** | `ShiftController` | For each shift: `is_overnight = ($shift->end_time < $shift->start_time)`. If true, `duration = (1440 - startMinutes + endMinutes)` minutes. Else `duration = endMinutes - startMinutes` | Auto-calculates duration and overnight flag |
| **Response** | JSON | 200 OK | `[{id, name, start_time, end_time, duration_hours, is_overnight}]` |

**Response JSON — 200 OK:**
```json
[
  {"id": 1, "name": "Shift A", "start_time": "06:00", "end_time": "14:00", "duration_hours": 8, "is_overnight": false},
  {"id": 2, "name": "Shift B", "start_time": "09:00", "end_time": "17:00", "duration_hours": 8, "is_overnight": false},
  {"id": 3, "name": "Shift C", "start_time": "14:00", "end_time": "22:00", "duration_hours": 8, "is_overnight": false},
  {"id": 4, "name": "Shift D", "start_time": "22:00", "end_time": "06:00", "duration_hours": 8, "is_overnight": true},
  {"id": 5, "name": "Shift E", "start_time": "20:30", "end_time": "08:30", "duration_hours": 12, "is_overnight": true}
]
```

---

#### 54. POST /api/shifts/create

**Purpose:** Create a new company-wide shift. Covers M3-SHFT-03, M3-SHFT-06.

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `POST /api/shifts/create` | Protected: `middleware(['auth:sanctum', 'role:compliance'])` |
| **Controller** | `ShiftController.php` | `store(Request $request)` | Creates new shift |
| **Validation** | Inline | `validate(['name'=>'required\|string\|max:100\|unique:shifts,name', 'start_time'=>'required\|date_format:H:i', 'end_time'=>'required\|date_format:H:i'])` | Returns 422 if invalid |
| **Business Logic** | `ShiftController` | Compute `is_overnight` and `duration_hours` same as EP 53. Store computed values. | Auto-derives overnight flag and duration |
| **Model** | `Shift.php` | `Shift::create(['name'=>..., 'start_time'=>..., 'end_time'=>..., 'duration_hours'=>..., 'is_overnight'=>...])` | Single INSERT |
| **Response** | JSON | 201 Created | Full shift object: `{id, name, start_time, end_time, duration_hours, is_overnight}` |

---

#### 55. PUT /api/shifts/{shift}/update

**Purpose:** Edit an existing shift definition. Covers M3-SHFT-04.

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `PUT /api/shifts/{shift}/update` | Protected: `middleware(['auth:sanctum', 'role:compliance'])` |
| **Controller** | `ShiftController.php` | `update(Request $request, Shift $shift)` | Updates existing shift |
| **Validation** | Inline | `validate(['name'=>'sometimes\|required\|string\|max:100\|unique:shifts,name,'.$shift->id, 'start_time'=>'sometimes\|required\|date_format:H:i', 'end_time'=>'sometimes\|required\|date_format:H:i'])` | Returns 422 if invalid |
| **Business Logic** | `ShiftController` | Recompute `is_overnight` and `duration_hours` if times changed | Updates derived fields automatically |
| **Model** | `Shift.php` | `$shift->update($validated)` | Single UPDATE |
| **Response** | JSON | 200 OK | Updated shift object |

---

#### 56. DELETE /api/shifts/{shift}/delete

**Purpose:** Delete a shift definition. Covers M3-SHFT-05. Confirm UI is frontend responsibility (M3-SHFT-05 confirmation dialog).

| Layer | File / Class | Method / Code | Details |
|---|---|---|---|
| **Route** | `routes/api.php` | `DELETE /api/shifts/{shift}/delete` | Protected: `middleware(['auth:sanctum', 'role:compliance'])` |
| **Controller** | `ShiftController.php` | `destroy(Shift $shift)` | Deletes shift |
| **Business Logic** | `ShiftController` | Check if shift is referenced by any active `schedule_assignments` for future weeks. If yes: return 422 with warning. Past assignments are unaffected (historical reference preserved) | Prevents breaking future schedules |
| **Model** | `Shift.php` | `$shift->delete()` | Soft-delete preferred (`SoftDeletes` trait) so historical assignment records keep their `shift_id` reference |
| **Response** | JSON | 200 OK | `{message: 'Shift deleted successfully.'}` |
| **Errors** | — | 422 | `{message: 'Shift is in use by future schedule assignments. Remove those assignments first.'}` |

---

### M3-MGR — Access Restriction (enforced at middleware, no endpoint)

> M3-MGR-01 is enforced by the `role:compliance` middleware on every M3 endpoint above.
> Any user without the `Compliance` Spatie role receives **HTTP 403 Forbidden** before the controller is reached.
> No separate endpoint is required. Managers (L2–L6) do not hold the `compliance` role and are therefore blocked from all 11 M3 endpoints.

---

## M3 Summary

| Category | Endpoints | Requirements covered | Status |
|---|---|---|---|
| Audit Grid + Status Logic | 2 (EP 49, 50) | M3-GRID-01 to 06, M3-STAT-01 to 09, M3-FILT-01 to 03, M3-OVER-01 to 03, M3-MODAL-01 to 03 | ⬜ Pending |
| Fingerprint Import | 2 (EP 51, 52) | M3-IMP-01 to 05 | ⬜ Pending |
| Shift Management | 4 (EP 53–56) | M3-SHFT-01 to 06 | ⬜ Pending |
| Manager Restriction | Middleware only | M3-MGR-01 | ⬜ Pending |
| Shift Read (M2 uses) | 1 (EP 48, in M2 section) | M3-SHFT-01 (read-only from M2) | ⬜ Pending |
| **Total HTTP** | **11** | **33** | ⬜ Pending |

### New DB Tables Required for M3

| Table | Purpose |
|---|---|
| `shifts` | Company-wide shift definitions. `name`, `start_time`, `end_time`, `duration_hours`, `is_overnight`. Managed by Compliance, read by M2 |
| `fingerprint_records` | One row per employee per day. `user_id`, `date`, `clock_in`, `clock_out`. Updated via import |

> `schedule_assignments` and `assignment_changes` defined in M2 are also read by M3 for grid rendering and cell detail.

---

## Full System Endpoint Count

| Module | HTTP Endpoints | Background Jobs |
|---|---|---|
| M1 — Core Admin & Hierarchy | 36 | 0 |
| M2 — Manager Schedule | 12 | 2 |
| M3 — Audit & Compliance | 11 | 0 |
| **Total** | **59** | **2** |

---

## Full DB Table Reference

| Table | Module | Purpose |
|---|---|---|
| `users` | M1 | User accounts (name, email, password, department_id, user_level_id, user_level_tier_id, is_admin) |
| `departments` | M1 | Department hierarchy (name, parent_id, path, is_active) |
| `user_levels` | M1 | Level definitions (code, name, hierarchy_rank) |
| `user_level_tiers` | M1 | Tier definitions per level (tier_name, tier_order, user_level_id) |
| `roles` | M1 | Spatie roles table |
| `permissions` | M1 | Spatie permissions table |
| `model_has_roles` | M1 | Pivot: user ↔ role |
| `role_has_permissions` | M1 | Pivot: role ↔ permission |
| `personal_access_tokens` | M1 | Sanctum tokens |
| `weekly_schedules` | M2 | One header row per dept per week (status, published_at, published_by) |
| `schedule_assignments` | M2 | One row per employee per day (type, shift_id, is_cover, covered_employee_id, comment) |
| `assignment_changes` | M2 | Immutable audit log per assignment change |
| `shifts` | M2/M3 | Company-wide shift definitions (name, start_time, end_time, duration_hours, is_overnight) |
| `fingerprint_records` | M3 | Imported clock-in/out data (user_id, date, clock_in, clock_out) |

---

*— End of Section 7: Full Backend Technical Flow (M1 + M2 + M3) —*

---

**CONFIDENTIAL — NVT Internal Use Only**
