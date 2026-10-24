---
name: github-issue-resolver
description: End-to-end resolution of single or multiple GitHub issues for ValenceKit, handled strictly one by one. Covers fast investigation, non-reproducible issue handling, planning, implementation, Pest tests, Chrome DevTools MCP browser validation with standard admin/user credentials, local Git commit (no push), and GitHub commenting/closure.
---

# GitHub Issue Resolver

Autonomous, sequential GitHub issue resolution for ValenceKit. Enforces strict conventions from `AGENTS.md`, dual verification (Pest + Chrome DevTools MCP), isolated per-issue commits, and careful issue state management.

---

## Core Guardrails

1. **NEVER PUSH**: `git push` is strictly forbidden. Commit locally only.
2. **SEQUENTIAL (ONE BY ONE)**: Process multiple issues one at a time. Finish, test, commit, comment, and close Issue 1 before touching Issue 2.
3. **WHEN AN ISSUE IS NOT FOUND OR NOT REPRODUCIBLE**:
   - If the codebase does not exhibit the problem, files/symbols mentioned do not exist, or the bug cannot be reproduced:
     - Post a detailed explanation in the GitHub issue comment section explaining what was checked and why it cannot be reproduced.
     - **DO NOT close the issue**. Leave it open for the user to review.
     - Move on to the next issue in the queue.
   - If the issue number does not exist on GitHub (404), report to the user and proceed to the next issue.
4. **MANDATORY DUAL VERIFICATION**: Every fixed issue must pass:
   - Automated Pest feature/unit tests.
   - Live browser validation via Chrome DevTools MCP.
5. **FOLLOW AGENTS.md**: PHP 8.5 conventions, single-root Vue templates, Pint formatting (`vendor/bin/pint`), and Vite asset compilation (`npm run build`).

---

## Browser Test Authentication Credentials

When browser flows require authentication, read credentials dynamically from the local `.env` file (do NOT hardcode credentials):

| Role | Login URL | Email Key (`.env`) | Password Key (`.env`) |
| :--- | :--- | :--- | :--- |
| **Admin Panel** | `https://valencekit.test/admin/login` | `ADMIN_BOOTSTRAP_EMAIL` | `ADMIN_BOOTSTRAP_PASSWORD` |
| **User Site** | `https://valencekit.test/login` | `USER_BOOTSTRAP_EMAIL` | `USER_BOOTSTRAP_PASSWORD` |

### Browser Login Helper Pattern:
1. Retrieve email & password from the active environment / `.env`.
2. Navigate to the login route via `chrome-devtools-mcp:navigate_page`.
3. Inspect inputs with `chrome-devtools-mcp:take_snapshot`.
4. Fill credentials with `chrome-devtools-mcp:fill` on the email & password uids.
5. Click submit via `chrome-devtools-mcp:click` and verify landing page redirect.

---

## Sequential Execution Flow

```
Queue: [Issue #A, Issue #B, ...]
           │
           ▼
┌────────────────────────────────────────────────────────┐
│ For each Issue in Queue:                               │
│                                                        │
│  1. Investigate Fast (GitHub MCP + Codebase search)    │
│     ├── Not reproducible / Not found?                  │
│     │   └── Comment on issue (KEEP OPEN) ──> NEXT ISSUE│
│     └── Confirmed defect / requirement:                │
│         2. Plan implementation & tests                 │
│         3. Implement fix (Vue/PHP + Pint + Build)      │
│         4. Write Pest feature test                     │
│         5. Open browser & login (Chrome DevTools MCP)  │
│         6. Verify on browser + run Pest test suite     │
│         7. Git commit (NO PUSH) + Comment + Close      │
│         8. Verify clean tree                           │
│                                                        │
│  ===> PROCEED TO NEXT ISSUE                            │
└────────────────────────────────────────────────────────┘
           │
           ▼
[Consolidated Summary Report]
```

---

## Per-Issue Protocol (8 Steps)

### Step 1: Investigate Fast & Verify Existence

1. **Fetch Issue**:
   - Call `github:get_issue` (`owner: Najmul-Hasan-Sobuj`, `repo: ValenceKit`, `issue_number: <number>`).
   - If 404/not found on GitHub: inform user and advance to next issue.
2. **Inspect Codebase**:
   - Search for affected files and symbols using `grep_search` and `view_file`.
3. **Check Reproducibility**:
   - **If the issue DOES NOT EXIST / CANNOT BE FOUND**:
     - Call `github:add_issue_comment`: Explain clearly what files/logic were checked, why the issue could not be reproduced or found, and what details are needed.
     - **CRITICAL: DO NOT close the issue**. Keep it open.
     - Proceed directly to the next issue in the queue.
   - **If the issue IS CONFIRMED**: Proceed to Step 2.

### Step 2: Make a Plan

- State the root cause and required changes.
- List exact files in scope (keep changes minimal and isolated).
- Define the test strategy (Pest feature test + browser scenarios).

### Step 3: Implement Changes

- Apply edits using `replace_file_content` / `multi_replace_file_content` / `write_to_file`.
- Maintain single-root element for Vue templates.
- Compile frontend assets: `npm run build`.
- Format PHP code: `vendor/bin/pint --dirty --format agent`.

### Step 4: Write Tests

- Create or update Pest feature test (`tests/Feature/...`).
- Assert happy path, failure modes, and permissions.
- Run targeted test: `php artisan test --compact --filter=<TestName>`.

### Step 5: Open Browser (Chrome DevTools MCP)

- Navigate to the page via `chrome-devtools-mcp:navigate_page` or `new_page` (`https://valencekit.test/<route>`).
- If the route requires authentication, sign in using credentials from `.env` (`ADMIN_BOOTSTRAP_EMAIL` / `ADMIN_BOOTSTRAP_PASSWORD` for Admin, `USER_BOOTSTRAP_EMAIL` / `USER_BOOTSTRAP_PASSWORD` for User).
- Check `list_console_messages` to ensure zero runtime errors.

### Step 6: Test on Browser & Run Test Suite

- Interactively test user flow using Chrome DevTools MCP (`click`, `fill`, `wait_for`, `take_screenshot`).
- Re-check console messages and network requests for errors.
- Run full test suite: `php artisan test --compact`.

### Step 7: Commit, Comment & Close

1. **Stage & Commit (Local Only - NO PUSH)**:
   - `git add <files>`
   - `git commit -m "fix(<scope>): <description> (closes #<issue_number>)"`
   - **DO NOT PUSH**.
2. **Comment on Issue**:
   - Call `github:add_issue_comment`: Summarize root cause, changes made, Pest test results, and browser verification status.
3. **Close Issue**:
   - Call `github:update_issue`: Set `state: "closed"`.

### Step 8: Clean Tree & Advance

1. Ensure working tree is clean via `git status`.
2. Transition to the next issue in the queue until all issues are processed.
3. Provide a final summary of all resolved issues and remaining open issues.
