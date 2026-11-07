# Secret Management and Credential Rotation

This guide describes the repository's current safeguards and how to plan credential changes. It does not certify that the repository or its history is free of secrets. Agent rules are in [AGENTS.md](../../AGENTS.md).

## Repository safeguards

- [`.gitignore`](../../.gitignore) excludes `.env`, `.env.*` except `.env.example`, `*.pem`, and `*.key`. Ignore rules do not remove files already tracked by Git.
- Keep secret values blank in [`.env.example`](../../.env.example). Store actual values in the deployment platform's protected environment settings or the secret store used by that environment.
- [`.gitleaks.toml`](../../.gitleaks.toml) enables Gitleaks default rules and excludes generated `public/build` and `bootstrap/ssr` artifacts. It does not broadly exclude documentation, tests, or seeders.
- [The CI workflow](../../.github/workflows/tests.yml) runs on pushes to `main` and pull requests. Its Gitleaks job checks out full history, and the `ci` job depends on it. Blocking merges also requires repository branch protection or a ruleset; this guide does not establish that setting.
- [SecretScanningTest.php](../../tests/Feature/Security/SecretScanningTest.php) checks tracked environment/key files, selected blank placeholders, scanner configuration, CI wiring, and selected credential signatures in tracked files. Its signature scan has explicit exclusions and skips missing files; it does not scan untracked files or full Git history.

Run the focused regression checks with:

```bash
php artisan test --compact tests/Feature/Security/SecretScanningTest.php
```

Record the command, commit, scanned scope, date, and redacted findings when reporting an actual security scan. No historical scan result is asserted by this guide.

## Planning a credential change

1. Identify the affected environment, account, consumers, and credential owner.
2. Use the environment's existing protected secret store. Do not copy values into tickets, chat, source code, or shell command arguments.
3. For routine rotation, install the replacement, reload affected processes, verify the integration, and retire the old credential. For a known leak, prioritize containment and revoke the exposed credential promptly.
4. Record what changed and the verification result without recording credential values.

## Application encryption key

[`config/app.php`](../../config/app.php) configures `AES-256-CBC`, `APP_KEY`, and `APP_PREVIOUS_KEYS`. Before rotation, identify encrypted stored data, cookies, and signed-link consumers and plan how to preserve or replace them.

For routine rotation, Laravel can decrypt with previous keys listed in `APP_PREVIOUS_KEYS` while encrypting new values with `APP_KEY`. This does not automatically re-encrypt stored data. Re-encrypt or retire old encrypted data and account for existing cookies before removing previous keys. See [Laravel's key rotation documentation](https://laravel.com/framework/docs/encryption#gracefully-rotating-encryption-keys).

Do not retain a compromised key as an ordinary compatibility fallback. Plan incident recovery, session invalidation, and any required data migration explicitly. Deploy the changed configuration using the environment's established configuration-cache and worker-reload procedure, then verify decryption and authentication. Avoid indiscriminate application cache clearing.

## MCP and OAuth tokens

- Use **Admin → Settings → MCP** to revoke the affected personal access token and issue a replacement. The default page is `/admin/settings/mcp`; the admin prefix is configurable.
- The current [MCP settings controller](../../app/Http/Controllers/Admin/Settings/McpSettingsController.php) issues tokens with `mcp:use` and `mcp:access`. Tool access also depends on the admin's current permissions. Do not describe the current UI as a granular scope selector.
- For an OAuth client incident, identify the relevant client, owner, access tokens, and any associated refresh tokens before revocation. Revoking only an access token is insufficient if a refresh token can issue another one.
- Do not select tokens for bulk revocation by a name prefix: token names are user supplied. Verify exact targets and test that revoked credentials fail and intended replacement access succeeds.

## Bootstrap and account passwords

- Bootstrap credentials are used by seeders. Remove unneeded bootstrap secrets from production after initialization; deliberate reseeding requires valid credentials again.
- [AdminSeeder](../../database/seeders/AdminSeeder.php) uses `firstOrCreate`, so changing a bootstrap password does not rotate an existing account's password.
- Change an admin password through **Admin → Settings → Security**, at `/admin/settings/security` with the default prefix, or use the password-reset flow. See [admin routes](../../routes/admin.php).
- Do not put a replacement password in a Tinker command or shell history. After a compromise, also review active sessions and API tokens; changing the password alone should not be treated as revoking them.
- Passkey support is available. Do not assume that admin two-factor authentication or passkey enforcement exists without checking the current implementation.

## Storage, mail, and other providers

Only document and rotate integrations used by the target deployment. Follow that provider's current rotation procedure and use the least privileges needed. If it supports overlapping credentials, verify the replacement before retiring the old credential during routine rotation. Do not promise zero downtime solely because two keys can coexist.

Use an authorized test recipient or test storage object to verify delivery or storage access. Never include credentials in test output.

## Responding to an exposed secret

1. Identify and revoke or disable the exposed credential at its issuer. Removing the value from Git does not invalidate it.
2. Replace it through the protected deployment configuration and verify affected consumers.
3. Review relevant access logs and the exposure window; record redacted evidence.
4. Remove the exposed value from current files. If history cleanup is needed, agree on the exact scope and coordinate with maintainers before rewriting shared history. Rewriting history cannot remove copies from other clones, forks, logs, or caches.
5. Rerun the relevant checks and document their scope. Fix the cause of the leak without weakening scanner coverage.
