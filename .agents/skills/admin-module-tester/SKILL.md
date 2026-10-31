---
name: admin-module-tester
description: Thoroughly tests any admin module in ValenceKit dynamically (e.g., Categories, Finance, Roles, Users). Automates admin login, CRUD, search/filters, pagination, large datasets, validation, and Pest tests via Chrome DevTools MCP. Documents all discovered defects as individual GitHub Issues in Bangla.
---

# Admin Module QA Tester

Autonomous, end-to-end QA testing workflow for any administrative module within ValenceKit (e.g., **Categories**, **Finance**, **Roles**, **Users**, **Audit Logs**, etc.).

Enforces dynamic module discovery, deep browser testing via **Chrome DevTools MCP Server**, backend verification with **Pest**, and strict defect documentation on **GitHub Issues** written in **Bangla**.

---

## 🛡️ Core Guardrails & Rules

1. **DYNAMIC MODULE RESOLUTION**:
   - The module name must never be hardcoded. The skill dynamically adapts to whichever module the user specifies (e.g., Categories, Finance, Users).
2. **COMMUNICATION & ISSUE LANGUAGE (BANGLA)**:
   - **Always communicate with the user in Bangla (বাংলা).**
   - **All GitHub Issues must be written entirely in Bangla (বাংলা)** using the standardized issue template.
3. **NON-DESTRUCTIVE TESTING**:
   - Test data must be clearly distinguishable (e.g., prefix test names with `[QA Test]`).
   - Clean up created test entities after verification or leave the database in a clean, consistent state.
   - Do NOT alter or delete existing seed/production records.
4. **ZERO UNRELATED CODE MODIFICATIONS**:
   - Do NOT edit or modify code in unrelated modules or functionality. Focus exclusively on testing and defect reporting.
5. **NEVER GIT PUSH**:
   - Do NOT run `git push`. All operations are strictly local or via the GitHub Issues API.

---

## 🔑 Admin Browser Authentication Credentials

When executing browser test flows, dynamically retrieve admin credentials from the active `.env` file:

| Parameter | Source / Value |
| :--- | :--- |
| **Admin Login URL** | `https://valencekit.test/admin/login` |
| **Email Key (`.env`)** | `ADMIN_BOOTSTRAP_EMAIL` |
| **Password Key (`.env`)** | `ADMIN_BOOTSTRAP_PASSWORD` |

### Browser Login Protocol (Chrome DevTools MCP):
1. Read `ADMIN_BOOTSTRAP_EMAIL` and `ADMIN_BOOTSTRAP_PASSWORD` from `.env`.
2. Navigate: `chrome-devtools-mcp:navigate_page` to `https://valencekit.test/admin/login`.
3. Snapshot: `chrome-devtools-mcp:take_snapshot` to inspect accessibility tree and identify input element UIDs.
4. Input: `chrome-devtools-mcp:fill` on the email and password fields.
5. Submit: `chrome-devtools-mcp:click` the submit button.
6. Verify: Confirm URL redirects to `https://valencekit.test/admin/dashboard` or targeted admin route.

---

## 🗺️ Step 0: Dynamic Module Discovery

Given a target `$MODULE` (e.g., `Categories`):

1. **Determine Slugs & Routes**:
   - Module Slug: kebab-case (e.g., `categories`, `audit-logs`, `roles`).
   - Route list inspection: `php artisan route:list --path=admin/<slug>`.
   - Primary Route: `https://valencekit.test/admin/<slug>`.
2. **Locate Codebase Assets**:
   - **Controller**: `app/Http/Controllers/Admin/<Module>Controller.php`
   - **Form Requests**: `app/Http/Requests/Admin/<Module>/*Request.php`
   - **Model & Factory**: `app/Models/<Module>.php`, `database/factories/<Module>Factory.php`
   - **Inertia Vue Pages**: `resources/js/pages/admin/<slug>/*.vue`
   - **Wayfinder Routes**: `resources/js/actions/App/Http/Controllers/Admin/<Module>Controller.ts`
   - **Pest Tests**: `tests/Feature/Admin/<Module>Test.php`

---

## 🧪 Comprehensive Module Testing Matrix

Execute the testing lifecycle sequentially across all 5 verification pillars:

```
┌────────────────────────────────────────────────────────┐
│            Admin Module Testing Matrix                 │
├────────────────────────────────────────────────────────┤
│ 1. List, Search & Filter Verification                  │
│    ├── Table & Column rendering                        │
│    ├── Debounced Search & empty states                 │
│    └── Filter dropdowns, date filters, reset           │
├────────────────────────────────────────────────────────┤
│ 2. Complete CRUD Verification                          │
│    ├── Create: valid data, toasts, list refresh        │
│    ├── Update: drawer pre-fill, edits, reflection      │
│    └── Delete: confirmation modal, cancel vs confirm   │
├────────────────────────────────────────────────────────┤
│ 3. Pagination & Large Datasets                         │
│    ├── Next/Prev, page jumps, count indicators         │
│    └── Layout stability with 25-50+ records            │
├────────────────────────────────────────────────────────┤
│ 4. Validation, Edge Cases & Error Handling             │
│    ├── Required fields, empty submissions              │
│    ├── Length boundaries, special chars, XSS strings   │
│    ├── Duplicate unique keys (slug, name, code)        │
│    └── Console message audit (0 JS / 500 errors)       │
├────────────────────────────────────────────────────────┤
│ 5. Automated Pest Test Execution                       │
│    └── php artisan test --compact --filter=<Module>    │
└────────────────────────────────────────────────────────┘
```

---

### Pillar 1: List, Search & Filters

1. **Navigate to Index**:
   - Visit `https://valencekit.test/admin/<slug>`.
   - Take snapshot and verify table header, action buttons, filter bars, and data rows.
2. **Search Verification**:
   - Locate search input field (`take_snapshot`).
   - Type an existing record keyword via `chrome-devtools-mcp:fill` or `type_text`.
   - Verify debounced search filters results without page reload.
   - Search for a non-existent string (e.g., `xyz_nonexistent_999`).
   - Verify empty state message/illustration is properly displayed.
   - Clear search input and verify full list restores.
3. **Filter Controls**:
   - Select filter dropdowns (e.g., Type, Status, Parent Category).
   - Verify table immediately reflects filtered records.
   - Test "Clear Filters" or reset button to ensure state resets cleanly.
4. **Sorting**:
   - Click sortable column headers (e.g., Name, Date, ID).
   - Check URL query params for sort keys (`?sort=...&direction=...`) and table row order.

---

### Pillar 2: Complete CRUD Verification

1. **Create Action**:
   - Click "Create / Add" button to open modal, drawer, or page.
   - Fill valid inputs with recognizable prefix: e.g., `[QA Test] New Item`.
   - Select parent, type, color, icon, or options if applicable.
   - Click Save/Submit.
   - Verify:
     - Drawer/modal closes automatically.
     - Success toast appears.
     - New record is visible in the list table.
2. **Update Action**:
   - Locate the test record created above.
   - Click "Edit" action from row actions / dropdown menu.
   - Verify all fields correctly pre-populate with existing values.
   - Modify fields (e.g., update name to `[QA Test] Updated Item`).
   - Click Update/Save.
   - Verify success toast and that the updated values reflect immediately in the table.
3. **Delete Action**:
   - Click "Delete" action on the test record.
   - Verify confirmation modal / alert dialog appears.
   - Click "Cancel" -> Verify record remains intact in the table.
   - Click "Delete" again -> Confirm deletion.
   - Verify success notification and verify record is removed from the list table.

---

### Pillar 3: Pagination & Large Datasets

1. **Pagination Controls**:
   - Inspect pagination footer: check current page, total count, "Showing X to Y of Z".
   - Click "Next", page numbers, and "Previous" buttons.
   - Verify URL query parameters update cleanly (`?page=2`).
2. **Large Dataset Handling**:
   - If current dataset is under 15 records, check whether pagination renders correctly or test with additional mock records.
   - Verify no horizontal table breakage, proper sticky headers, and responsive rendering.

---

### Pillar 4: Validation, Edge Cases & Error Handling

1. **Empty Submissions (Required Fields)**:
   - Open Create drawer/modal and submit without filling required fields.
   - Verify form is blocked from submitting and validation errors render below the corresponding inputs.
2. **Edge-Case Inputs & Boundary Testing**:
   - **Special Characters & XSS**: Test strings like `<script>alert(1)</script>`, `Test "quotes" & 'apostrophes'`, `Emoji 🚀🎉`.
   - Verify inputs are properly escaped and do not cause script execution or layout breaks.
   - **Boundary Length**: Very long strings (e.g., 255+ characters) to verify database schema constraints and truncation/wrapping.
3. **Duplicate Keys & Unique Constraints**:
   - Attempt to create an item with duplicate unique attributes (e.g., identical name or slug).
   - Verify server returns validation error gracefully (no raw 500 SQL error screen).
4. **Browser Console & Network Audit**:
   - Call `chrome-devtools-mcp:list_console_messages` to check for:
     - JavaScript uncaught exceptions or TypeError.
     - Vue warnings (`[Vue warn]: ...`).
   - Call `chrome-devtools-mcp:list_network_requests` to check for:
     - Failed network requests (4xx errors other than expected 422 validation, or 500 Internal Server Errors).

---

### Pillar 5: Automated Backend Pest Tests

1. Run the module's targeted feature test suite:
   ```bash
   php artisan test --compact --filter=<Module>
   ```
2. Check for any failing assertions, regressions, or unhandled exceptions.
3. If tests fail, inspect the failure trace and underlying controller/request/model logic.

---

## 📝 GitHub Issue Creation Protocol (in Bangla)

If any defects, edge-case failures, UI bugs, or test errors are discovered:

1. **Document each issue individually** using `github:create_issue`.
2. **Target Repository**: `Najmul-Hasan-Sobuj/ValenceKit`.
3. **Language**: **Strictly in Bangla (বাংলা).**
4. **Format & Template**:

```markdown
### 🔍 সমস্যার বিবরণ (Problem Description)
[সমস্যাটির স্পষ্ট ও বিস্তারিত বিবরণ বাংলায় লিখুন]

### 🔁 পুনরুৎপাদনের ধাপসমূহ (Reproduction Steps)
1. অ্যাডমিন প্যানেলে লগইন করুন (`https://valencekit.test/admin/login`)।
2. `<মডিউল নাম>` মেনুতে যান (`https://valencekit.test/admin/<slug>`)।
3. [নির্দিষ্ট বাটন বা ফিল্ডে ক্লিক/ইনপুট করুন]
4. [নির্দিষ্ট ডেটা সাবমিট করুন]

### 🎯 প্রত্যাশিত বনাম প্রকৃত ফলাফল (Expected vs Actual Result)
- **প্রত্যাশিত ফলাফল (Expected Result):** [সিস্টেমে কী ঘটার কথা ছিল]
- **প্রকৃত ফলাফল (Actual Result):** [প্রকৃতপক্ষে কী ঘটছে বা কী সমস্যা দেখা দিচ্ছে]

### 💡 প্রস্তাবিত সমাধান (Suggested Solution)
[কোড, রিকোয়েস্ট ভ্যালিডেশন, কন্ট্রোলার বা ফ্রন্টএন্ড কম্পোনেন্টে কী পরিবর্তন আনতে হবে তার পরামর্শ]

### 🖥️ অতিরিক্ত তথ্য (Additional Context)
- **মডিউল:** `<মডিউল নাম>`
- **রুট/পেজ:** `admin/<slug>`
- **কনসোল এরর/নেটওয়ার্ক রেসপন্স:**
```json
[যদি থাকে তবে কনসোল লগ বা নেটওয়ার্ক এরর কোড উল্লেখ করুন]
```
```

---

## 💬 Response & Reporting Guidelines

- **Always communicate with the user in Bangla.**
- Summarize the QA audit clearly:
  - Target module name.
  - Test pillars completed (CRUD, Search/Filter, Pagination, Validation, Pest tests).
  - List of created GitHub Issues with their issue numbers and titles.
  - Overall health score and recommendations for the module.

