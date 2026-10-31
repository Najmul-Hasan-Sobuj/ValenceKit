---
name: rbac-system-tester
description: Thoroughly tests the User, Role & Permission (RBAC) system in ValenceKit. Automates creating roles/users, assigning granular permissions, multi-session testing via Chrome DevTools MCP, frontend/backend authorization checks, permission toggling/cache invalidation, Pest automated tests, and defect logging to GitHub Issues in Bangla.
---

# User, Role & Permission (RBAC) System QA Tester

Autonomous, end-to-end QA testing workflow for the **User, Role & Permission (RBAC)** architecture in ValenceKit.

Enforces dynamic provisioning of test roles and users, isolated multi-session browser testing via **Chrome DevTools MCP Server**, dual-layer verification (frontend UI visibility vs. backend route/API gate authorization), live permission toggling and cache invalidation auditing, backend verification with **Pest**, and strict defect documentation on **GitHub Issues** written in **Bangla (বাংলা)**.

---

## 🛡️ Core Guardrails & Rules

1. **PROJECT-SCOPED SKILL**:
   - This skill is strictly configured for this project (`ValenceKit`) and operates within `.agents/skills/rbac-system-tester/`.
2. **COMMUNICATION & ISSUE LANGUAGE (BANGLA)**:
   - **Always communicate with the user in Bangla (বাংলা).**
   - **All GitHub Issues must be written entirely in Bangla (বাংলা)** using the standardized issue template.
3. **NON-DESTRUCTIVE TESTING & AUTOMATIC CLEANUP**:
   - All test entities (roles, users, invitations) **MUST** be prefixed with `[QA Test]` (e.g., `[QA Test] Manager Role`, `[QA Test] User Alpha`).
   - Use test data only. Never alter or delete existing system/seeded roles (`Super Admin`, `Staff`, `Support`) or existing admin accounts.
   - Automatically delete all created `[QA Test]` users and roles upon completing testing, leaving the database clean and consistent.
4. **ZERO UNRELATED CODE MODIFICATIONS**:
   - Do NOT edit or modify application code in unrelated modules. Focus exclusively on testing, authorization verification, and defect reporting.
5. **NEVER GIT PUSH**:
   - Do NOT run `git push`. All operations are strictly local or via the GitHub Issues API.

---

## 🔑 Authentication & Environment Context

| Parameter | Source / Value | Description |
| :--- | :--- | :--- |
| **Admin Login URL** | `https://valencekit.test/admin/login` | Fortify Admin authentication endpoint |
| **Super Admin Email** | `.env` -> `ADMIN_BOOTSTRAP_EMAIL` | Default: `admin@example.com` |
| **Super Admin Password** | `.env` -> `ADMIN_BOOTSTRAP_PASSWORD` | Default bootstrap credentials |
| **Test User Password** | Standardized for QA: `Password123!` | Used for newly created test users |
| **Admin Guard** | `admin` | Spatie Permission guard name |

---

## 🗺️ Codebase & RBAC Architecture Reference

Before running tests, keep the application's RBAC plumbing in mind:

- **Seeder & Permission Groups**: `database/seeders/RoleAndPermissionSeeder.php`
  - Groups: `User Management`, `Role Management`, `System Settings`, `Audit & Compliance`, `Category Management`, `Finance Tracker`.
- **Backend Spatie Setup**:
  - `App\Models\Admin` uses `HasRoles` trait with `protected string $guard_name = 'admin';`.
  - Roles table: `roles` (`name`, `guard_name`, `is_system`, `description`).
  - Permissions table: `permissions` (`name`, `guard_name`, `group_name`).
- **Inertia Props Middleware**:
  - `App\Http\Middleware\HandleInertiaRequests` shares:
    - `auth.admin.roles` (list of assigned role names)
    - `auth.admin.permissions` (list of permission names, cached forever for Super Admin)
- **Frontend Composable**:
  - `resources/js/composables/usePermissions.ts` exposes:
    - `can(permission)`, `canAny(permissions)`, `canAll(permissions)`
    - `hasRole(roleName)`, `isSuperAdmin`
    - High-level guards: `canEditUser`, `canDeleteUser`, `canAssignRole`, `filterAssignableRoles`, `canEditRole`, `canDeleteRole`.
- **Routes to Test**:
  - Roles: `admin.authorization.roles.*` (`/admin/authorization/roles`)
  - Users: `admin.users.*` (`/admin/authorization/users`)
  - Finance: `admin.finance.*` (`/admin/finance`)
  - Categories: `admin.categories.*` (`/admin/categories`)
  - Settings: `admin.settings.*` (`/admin/settings/*`)
  - MCP: `admin.mcp.*` (`/admin/mcp/usage`, `/admin/settings/mcp`)
  - Audit Logs: `admin.audit-logs.*` (`/admin/audit-logs`)

---

## 🧪 RBAC Comprehensive Testing Lifecycle

Execute the RBAC testing workflow through the following 8 standardized phases:

```
┌────────────────────────────────────────────────────────┐
│            RBAC Comprehensive QA Matrix                │
├────────────────────────────────────────────────────────┤
│ Phase 1: Test Role & User Provisioning                 │
│    ├── Create Role A & Role B with [QA Test] prefix    │
│    └── Create User Alpha & User Beta                   │
├────────────────────────────────────────────────────────┤
│ Phase 2: Granular Role & Permission Assignment         │
│    ├── Role A: Finance + Category access               │
│    └── Role B: User Directory + Audit Log access       │
├────────────────────────────────────────────────────────┤
│ Phase 3: Multi-Session Browser Login (Chrome MCP)      │
│    ├── Session 1: Super Admin (Orchestrator)           │
│    ├── Session 2: User Alpha                           │
│    └── Session 3: User Beta                            │
├────────────────────────────────────────────────────────┤
│ Phase 4: Frontend UI Visibility Auditing               │
│    ├── Sidebar Navigation Items & Menus                │
│    └── Action Buttons (Create, Edit, Delete, Export)   │
├────────────────────────────────────────────────────────┤
│ Phase 5: Backend Authorization & Direct Access Checks  │
│    ├── Direct URL navigation to forbidden pages (403)  │
│    └── Direct API/Form submissions to blocked routes   │
├────────────────────────────────────────────────────────┤
│ Phase 6: Permission Toggling & Cache Invalidation      │
│    ├── Grant/Revoke live permissions                   │
│    └── Verify real-time reflection & isolation         │
├────────────────────────────────────────────────────────┤
│ Phase 7: Automated Backend Pest Test Execution         │
│    └── Run Role, User, and Authorization Pest suites   │
├────────────────────────────────────────────────────────┤
│ Phase 8: Teardown & GitHub Defect Reporting            │
│    ├── Clean up [QA Test] roles and users              │
│    └── Report defects to GitHub Issues in Bangla       │
└────────────────────────────────────────────────────────┘
```

---

### Phase 1: Test Role & User Provisioning

1. **Login as Super Admin**:
   - Access `https://valencekit.test/admin/login` via `chrome-devtools-mcp:navigate_page`.
   - Fill credentials from `.env` (`ADMIN_BOOTSTRAP_EMAIL` & `ADMIN_BOOTSTRAP_PASSWORD`).
   - Click Submit and verify redirect to `/admin/dashboard`.
2. **Create Test Roles**:
   - Navigate to `https://valencekit.test/admin/authorization/roles/create` or open Create Drawer on `/admin/authorization/roles`.
   - **Role A**:
     - Name: `[QA Test] Finance Manager`
     - Description: `QA Test role for financial ledger and category oversight`
   - **Role B**:
     - Name: `[QA Test] Compliance Auditor`
     - Description: `QA Test role for user directory inspection and audit logs`
3. **Create Test Users**:
   - Navigate to `https://valencekit.test/admin/authorization/users`.
   - Click "Add Admin" / "Invite Admin".
   - **User Alpha**:
     - Name: `[QA Test] User Alpha`
     - Email: `qa-alpha@example.com`
     - Role: `[QA Test] Finance Manager`
     - Status: Active
     - Password: Set to `Password123!` (or generate/update via Tinker if invite flow uses email tokens).
   - **User Beta**:
     - Name: `[QA Test] User Beta`
     - Email: `qa-beta@example.com`
     - Role: `[QA Test] Compliance Auditor`
     - Status: Active
     - Password: Set to `Password123!`.

> **Tip for Password Setting**: If user creation in the UI sends an invitation email without a direct password input, set the password directly via Tinker:
> ```bash
> php artisan tinker --execute '
> $u1 = App\Models\Admin::where("email", "qa-alpha@example.com")->first();
> if ($u1) { $u1->update(["password" => Hash::make("Password123!"), "is_active" => true]); }
> $u2 = App\Models\Admin::where("email", "qa-beta@example.com")->first();
> if ($u2) { $u2->update(["password" => Hash::make("Password123!"), "is_active" => true]); }
> '
> ```

---

### Phase 2: Granular Role & Permission Assignment

Configure the permission matrix through the Roles UI (`/admin/authorization/roles/{role}/edit`):

| Target Role | Granted Permissions | Explicitly Forbidden Permissions |
| :--- | :--- | :--- |
| **[QA Test] Finance Manager** (User Alpha) | `finance.view`<br>`finance.create`<br>`finance.edit`<br>`categories.view`<br>`categories.create`<br>`settings.profile.view` | `users.*`<br>`roles.*`<br>`audit.*`<br>`settings.mcp.*`<br>`settings.tracking.*`<br>`finance.delete` |
| **[QA Test] Compliance Auditor** (User Beta) | `users.view`<br>`audit.view`<br>`audit.export`<br>`settings.profile.view` | `finance.*`<br>`categories.*`<br>`roles.*`<br>`users.create`<br>`users.edit`<br>`users.delete`<br>`settings.mcp.*` |

Verify that the roles and attached permissions are stored correctly in the database.

---

### Phase 3: Multi-Session Browser Login (Chrome DevTools MCP)

Test the experience of each user using isolated browser sessions:

1. **Testing User Alpha (`qa-alpha@example.com`)**:
   - Log out current admin or clear session cookies.
   - Navigate to `https://valencekit.test/admin/login`.
   - Log in with `qa-alpha@example.com` and `Password123!`.
   - Verify successful authentication and redirection to dashboard.
   - Capture accessibility snapshot (`take_snapshot`).
2. **Testing User Beta (`qa-beta@example.com`)**:
   - Log out User Alpha.
   - Log in with `qa-beta@example.com` and `Password123!`.
   - Capture accessibility snapshot (`take_snapshot`).

---

### Phase 4: Frontend UI Visibility Auditing

Verify that the frontend UI strictly reflects each user's permissions:

#### For User Alpha (`[QA Test] Finance Manager`):
1. **Sidebar Navigation**:
   - **Visible**: "Finance Tracker", "Categories", "Settings -> Profile".
   - **Hidden**: "Authorization" (Users & Roles), "Audit Logs", "MCP Usage", "Settings -> Security/MCP/Tracking/Appearance".
2. **Action Buttons**:
   - On `/admin/finance`: "Log Transaction" is **Visible**, "Delete" action button is **Hidden** (due to missing `finance.delete`).
   - On `/admin/categories`: "Create Category" is **Visible**, "Delete Category" is **Hidden**.

#### For User Beta (`[QA Test] Compliance Auditor`):
1. **Sidebar Navigation**:
   - **Visible**: "Authorization -> Users", "Audit Logs", "Settings -> Profile".
   - **Hidden**: "Authorization -> Roles", "Finance Tracker", "Categories", "MCP Usage", "Settings -> Finance/MCP/Tracking".
2. **Action Buttons**:
   - On `/admin/authorization/users`: User list is readable; "Add User" / "Invite Admin" button is **Hidden**; Row actions "Edit" and "Delete" are **Hidden**.
   - On `/admin/audit-logs`: Audit logs are visible; "Export" button is **Visible** (`audit.export` granted).

---

### Phase 5: Backend Authorization & Direct Access Verification

Ensure frontend obscurity is backed by strict backend authorization gates:

1. **Direct URL Navigation (HTTP GET)**:
   - As **User Alpha**, directly navigate to forbidden routes:
     - `https://valencekit.test/admin/authorization/roles` -> **Must return 403 Forbidden**.
     - `https://valencekit.test/admin/authorization/users` -> **Must return 403 Forbidden**.
     - `https://valencekit.test/admin/audit-logs` -> **Must return 403 Forbidden**.
     - `https://valencekit.test/admin/settings/mcp` -> **Must return 403 Forbidden**.
   - As **User Beta**, directly navigate to forbidden routes:
     - `https://valencekit.test/admin/finance` -> **Must return 403 Forbidden**.
     - `https://valencekit.test/admin/categories` -> **Must return 403 Forbidden**.
     - `https://valencekit.test/admin/authorization/roles` -> **Must return 403 Forbidden**.
2. **Direct API / Mutation Submissions (POST / PUT / DELETE)**:
   - Verify that forged or direct HTTP requests to forbidden endpoints are blocked:
     - User Alpha attempting `DELETE /admin/finance/{id}` -> **403 Forbidden**.
     - User Alpha attempting `POST /admin/authorization/roles` -> **403 Forbidden**.
     - User Beta attempting `POST /admin/authorization/users` -> **403 Forbidden**.
     - User Beta attempting `DELETE /admin/authorization/users/{id}` -> **403 Forbidden**.
3. **Privilege Escalation & Boundary Testing**:
   - Test that non-Super Admins **cannot assign the `Super Admin` role** to any account (must return validation error / 403).
   - Test that admins **cannot delete their own user account** (`canDeleteUser`).
   - Test that non-Super Admins **cannot edit or delete system roles** (`Super Admin`).

---

### Phase 6: Permission Toggling & Cache Invalidation

Audit how the system handles dynamic permission updates without requiring server restarts:

1. **Log in as Super Admin**.
2. **Grant Additional Permission**:
   - Edit `[QA Test] Finance Manager` role -> Grant `finance.delete`.
   - Log back in as **User Alpha** (or refresh their active session).
   - Verify that User Alpha can now see and execute the "Delete" action on transactions.
3. **Revoke Permission**:
   - Super Admin revokes `categories.create` from `[QA Test] Finance Manager`.
   - Refresh User Alpha's session -> Verify that the "Create Category" button immediately disappears and direct `POST /admin/categories` returns 403.
4. **Cross-User Isolation**:
   - Verify that modifying User Alpha's role had **zero impact** on User Beta or Super Admin.
   - Verify that Super Admin retains unrestricted access (`*` permissions) at all times.

---

### Phase 7: Automated Backend Pest Test Execution

Run the relevant authorization test suites to confirm full regression coverage:

```bash
# Run Role and User Authorization tests
php artisan test --compact tests/Feature/Admin/Authorization/Role/RoleAuthorizationTest.php tests/Feature/Admin/Authorization/User/AuthorizationTest.php
```

Ensure all tests pass with 0 failures and 0 errors.

---

### Phase 8: Teardown & Clean-up Protocol

Leave the database in a pristine state:

```bash
php artisan tinker --execute '
// Remove test users
App\Models\Admin::where("email", "like", "qa-%@example.com")->orWhere("name", "like", "[QA Test]%")->delete();

// Remove test roles
Spatie\Permission\Models\Role::where("name", "like", "[QA Test]%")->delete();

// Clear permission cache
app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
Illuminate\Support\Facades\Cache::forget("admin_all_permissions");

echo "RBAC QA Clean-up complete.\n";
'
```

Verify that `Admin::where('name', 'like', '[QA Test]%')->count()` is `0` and `Role::where('name', 'like', '[QA Test]%')->count()` is `0`.

---

## 📝 GitHub Issue Creation Protocol (in Bangla)

If any unauthorized access, permission leak, UI discrepancy, or regression is discovered during testing:

1. **Document each issue individually** using `github:create_issue`.
2. **Target Repository**: `Najmul-Hasan-Sobuj/ValenceKit`.
3. **Language**: **Strictly in Bangla (বাংলা).**
4. **Format & Template**:

```markdown
### 🔍 সমস্যার বিবরণ (Problem Description)
[অননুমোদিত অ্যাক্সেস, পারমিশন লিক বা আরবিক ত্রুটির স্পষ্ট ও বিস্তারিত বিবরণ বাংলায় লিখুন]

### 🔁 পুনরুৎপাদনের ধাপসমূহ (Reproduction Steps)
1. `<টেস্ট রোল নাম>` রোল এবং `<টেস্ট ইউজার>` ইউজার তৈরি করুন।
2. ইউজারের জন্য শুধুমাত্র `<অনুমোদিত পারমিশনসমূহ>` নির্ধারণ করুন।
3. উক্ত ইউজার হিসেবে সিস্টেমে লগইন করুন (`https://valencekit.test/admin/login`)।
4. সরাসরি অননুমোদিত ইউআরএল বা অ্যাকশনে যান (`https://valencekit.test/admin/<restricted-path>`)।
5. [বাটন ক্লিক বা মিউটেশন রিকোয়েস্ট পাঠান]

### 🎯 প্রত্যাশিত বনাম প্রকৃত ফলাফল (Expected vs Actual Result)
- **প্রত্যাশিত ফলাফল (Expected Result):** ৪MD বা 403 Forbidden রিটার্ন করার কথা ছিল এবং UI এলিমেন্ট হাইড থাকার কথা ছিল।
- **প্রকৃত ফলাফল (Actual Result):** [অননুমোদিত ডেটা দেখা যাচ্ছে / 200 OK রেসপন্স এসেছে / 500 এরর হয়েছে]

### 💡 প্রস্তাবিত সমাধান (Suggested Solution)
[পলিসি (Policy), মিডলওয়্যার (Middleware), FormRequest authorize() মেথড বা usePermissions.ts এ কী পরিবর্তন করতে হবে তা সংক্ষেপে উল্লেখ করুন]

### 🖥️ অতিরিক্ত তথ্য (Additional Context)
- **ইউজার রোল:** `<রোল নাম>`
- **প্রভাবিত রুট/API:** `<route name বা URL>`
- **ব্যবহৃত পারমিশন:** `<permission.name>`
- **HTTP স্ট্যাটাস কোড:** `<Status Code>`
```

---

## 💬 Response & Reporting Guidelines

- **Always communicate with the user in Bangla.**
- Summarize the RBAC QA audit clearly with:
  1. Test roles and users created and configured.
  2. Permission matrix tested (Granted vs Denied).
  3. Frontend UI verification results (Menus, action buttons).
  4. Backend authorization verification results (Direct URL & API 403 checks).
  5. Permission toggling & cache invalidation outcome.
  6. Pest test execution results.
  7. Database clean-up confirmation.
  8. List of logged GitHub Issues (if defects found) or clear confirmation of zero defects.

