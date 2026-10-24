# Secret Management and Credential Rotation Policy

## 1. Overview & Threat Model

ValenceKit strictly isolates application secrets and sensitive credentials from source control. All application configurations rely on environment variables injected at runtime, preventing secrets from being checked into Git.

### Key Principles:
- **Zero Secrets in Git**: No production keys, passwords, API tokens, or certificates may ever be committed to the repository.
- **Automated CI Gates**: Gitleaks actively scans every push and pull request to detect and block secret leaks before merge.
- **Least-Privilege Scoping**: All service keys (MCP, AWS, Mail) must only possess permissions required for their specific function.
- **Deterministic Rotation**: Every credential has a defined rotation lifecycle and incident response plan.

---

## 2. Environment Variable Isolation & Scanning

### Git Exclusion
- `.gitignore` rigorously ignores `.env`, `.env.*` (with exception of `.env.example`), `*.pem`, `*.key`, and `/storage/*.key`.
- `.env.example` contains only safe schema definitions and blank placeholders for secret fields.

### Gitleaks Secret Scanner
- **Configuration**: [`.gitleaks.toml`](file:///Users/najmulhasan/Herd/Personal/ValenceKit/.gitleaks.toml) configures rule extension and defines explicit allowlists for benign test fixtures and documentation.
- **CI Workflow**: [`.github/workflows/tests.yml`](file:///Users/najmulhasan/Herd/Personal/ValenceKit/.github/workflows/tests.yml) includes the `gitleaks` job with `fetch-depth: 0`, running before test execution on all branches and PRs.
- **Automated Regression Test**: [`tests/Feature/Security/SecretScanningTest.php`](file:///Users/najmulhasan/Herd/Personal/ValenceKit/tests/Feature/Security/SecretScanningTest.php) verifies repository invariants:
  - Validates that `.env`, `.env.*`, and private keys are never tracked in Git.
  - Asserts that `.env.example` contains blank placeholders for secret keys.
  - Scans all tracked source files against signatures for AWS keys, GitHub tokens, private keys, Slack tokens, and Stripe secret keys.

### Historical Audit Findings
- Full Git history audit was executed across all commits (`git log -p --full-history`).
- **Result**: No production API keys, private keys, or passwords exist in repository history.

---

## 3. Approved Production Secret Managers

Production environments must NOT rely on manual `.env` file editing on production servers. Use one of the following approved secret storage solutions:

1. **Laravel Cloud**: Managed environment variables injected securely via the Laravel Cloud dashboard / CLI (`cloud env:set`).
2. **AWS Secrets Manager / SSM Parameter Store**: Store encrypted key-value pairs; inject via container initialization or task definitions.
3. **HashiCorp Vault**: Centralized secrets engine with audit logging and dynamic credential capabilities.
4. **1Password CLI (`op run`)**: For secure developer operations and staging injection (`op run -- php artisan ...`).

---

## 4. Step-by-Step Credential Rotation Procedures

### A. Application Encryption Key (`APP_KEY`)
`APP_KEY` is a 32-byte base64-encoded key used by Laravel for AES-256-CBC encryption (session payloads, encrypted cookies, signed URLs, and Eloquent `encrypted` model casts).

#### Impact of Rotation:
- Invalidates all current user sessions (users must log in again).
- Invalidates existing signed URLs (e.g., password reset links, email verification links).
- *Warning*: If your database stores encrypted model attributes (`'column' => 'encrypted'`), changing `APP_KEY` without re-encrypting existing database rows will cause decryption exceptions.

#### Safe Rotation Procedure:
1. Generate a new key without overwriting `.env`:
   ```bash
   php artisan key:generate --show
   ```
2. If encrypted database columns exist:
   - Run a migration script using the old key to decrypt fields and re-encrypt with the new key.
3. Update `APP_KEY` in your production secret manager.
4. Restart PHP worker processes and clear cached configs:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan queue:restart
   ```

---

### B. MCP & OAuth Tokens
Tokens issued for Model Context Protocol (MCP) clients or Passport API access.

#### Revocation & Rotation Procedure:
1. **Per-Token Revocation**:
   - Access the Admin Dashboard at `/admin/settings/mcp`.
   - Locate the compromised token under **Active Tokens** and click **Revoke**.
2. **Emergency Mass Revocation**:
   Execute via Laravel Tinker:
   ```bash
   php artisan tinker --execute 'Laravel\Passport\Token::where("name", "like", "mcp%")->update(["revoked" => true]);'
   ```
3. **Re-issuance**:
   - Re-issue a token with strictly scoped permissions (`mcp:use`, `mcp:access`, `mcp:finance.read`, etc.).
   - Distribute via secure channel to authorized clients.

---

### C. Admin Bootstrap Credentials
`ADMIN_BOOTSTRAP_EMAIL` and `ADMIN_BOOTSTRAP_PASSWORD` are strictly intended for initial database seeding.

#### Procedure:
1. **Production Invariant**: In production environments, keep `ADMIN_BOOTSTRAP_PASSWORD` blank or remove it after the initial deployment.
2. **Account Password Rotation**:
   - Admins rotate their password directly via `/admin/settings/profile`.
   - Enforce Two-Factor Authentication (2FA) and Passkey authentication for all administrative accounts.
3. **Emergency Admin Password Reset**:
   ```bash
   php artisan tinker --execute '$admin = App\Models\Admin::where("email", "admin@example.com")->first(); $admin->password = Hash::make("StrongNewPasswordHere!"); $admin->save();'
   ```

---

### D. Cloud Storage Credentials (AWS S3)
Dual-key rotation ensures zero downtime during key renewal.

#### Procedure:
1. In the AWS IAM Console, navigate to the IAM user associated with ValenceKit storage.
2. Under **Security credentials**, create a **Second Access Key** (AWS allows 2 active access keys concurrently).
3. Update `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` in the production secret manager.
4. Deploy the update and verify media uploads/downloads on the application.
5. In the AWS IAM Console, set the **Old Access Key** state to **Deactivated**.
6. Monitor error logs for 24 hours.
7. Permanently **Delete** the old IAM access key.

---

### E. Mail & Third-Party API Keys (Resend, Mailgun, Stripe)
1. Log into the service provider dashboard.
2. Generate a new API token with minimum necessary privileges.
3. Update `MAIL_PASSWORD` or provider API keys in the secret manager.
4. Trigger a test email (`php artisan tinker` with `Mail::raw()`).
5. Revoke the prior API key in the provider dashboard.

---

## 5. Incident Response Playbook (Leaked Secrets)

If a secret is ever inadvertently exposed in a repository, issue, or log:

1. **Step 1: Immediate Invalidation (Containment)**
   - Do NOT wait to clean Git history.
   - Immediately revoke or delete the compromised key at the issuing provider (AWS, Stripe, mail service, etc.).
2. **Step 2: Key Regeneration & Deployment**
   - Generate a fresh key and inject it into the secret manager.
   - Redeploy the application.
3. **Step 3: History Scrubbing (if committed to Git)**
   - If committed to a branch, use `git filter-repo` to permanently erase the commit and file from history:
     ```bash
     git filter-repo --invert-paths --path <compromised-file>
     ```
   - Coordinate with team members to re-clone the repository.
4. **Step 4: Forensic Audit**
   - Review audit logs (AWS CloudTrail, Mailgun logs, server access logs) during the window of exposure.
   - Check for anomalous API calls or unauthorized access.
5. **Step 5: Post-Incident Review**
   - Verify why local pre-commit or CI Gitleaks checks did not prevent the commit.
   - Update `.gitleaks.toml` rules and team guidelines accordingly.
