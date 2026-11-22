---
name: drivesnap-development
description: Install, configure, customize or troubleshoot DriveSnap encrypted database backups in a Laravel Inertia application, including Google Drive, backup permissions, dump tools, queues, readiness and recovery. Use for DriveSnap tasks, not unrelated database administration.
license: MIT
---

# DriveSnap development

## Find the installed integration

Read `composer show najmul-hasan-sobuj/drivesnap`, `config/drivesnap.php`, the installed package README and `php artisan route:list --name=drivesnap`. Match the installed Laravel/Inertia versions and existing Vue, React or Svelte stack. Paths below are relative to the consuming Laravel project. The package source lives under `vendor/najmul-hasan-sobuj/drivesnap`; change published application files for app customizations, not vendor files.

## Installation and upgrades

- `php artisan drivesnap:install --stack=vue --page=admin/backups/Index` is an example: retain the application's actual stack and page name. Keep `config('drivesnap.page')` consistent with the published page and its Inertia resolver/layout.
- Run `php artisan migrate` for missing upgrade migrations. Preserve original published migrations, existing archives and Google credentials; an upgrade does not require `migrate:fresh`, reseeding, disconnecting Google or regenerating `APP_KEY`.
- The installer preserves existing config/UI by default. `--force` replaces published UI files, including a customized page; review changes before using it. Rebuild the frontend after UI updates.

## Authorization and private data

DriveSnap is a single-installation administrator tool. Each archive includes the entire configured database, not a tenant's subset. Default abilities are `viewDriveSnap` for viewing and `manageDriveSnap` for other actions; configure `abilities`, `guard` and `middleware` to match the host's permission system. Every action requires view access plus its specific ability. Never grant all authenticated users backup access or remove the package's queued-requester authorization recheck.

Download permission also controls project-relative archive paths and Drive links. Keep OAuth tokens, upload-session URLs and absolute server paths out of responses and audit payloads. Storage stays private at `storage/app/private/drivesnap`; never expose it through a public disk, symlink or browser file-manager endpoint. Drive links open existing private files; do not make archives publicly shared.

## Dump tools and runtime settings

- MySQL/MariaDB use an executable compatible with the database server; SQLite uses `VACUUM INTO` without an external binary.
- Set `DRIVESNAP_DUMP_BINARY` / `backup_binary` in server configuration. This is the dump executable, not the archive directory. `DB_BACKUP_BINARY` is not a package setting: an application may explicitly map its legacy setting into `backup_binary`.
- Configure approved choices in `binaries`; the dashboard accepts only their keys. Never accept a browser-supplied executable path, command or shell arguments. Credentials belong in protected environment/config and the package's private options file, not process arguments or logs.
- Defaults: dump timeout 120 seconds, job timeout 300. Dump must be 1–3600, job 30–7200, with job exceeding dump by more than 30 seconds. Queue `retry_after` must exceed job timeout; align external worker/listener limits, process-manager grace periods and SQS visibility when applicable. Restart long-lived workers after config changes.

## Google Drive and encryption

Enable Google Drive API, configure an OAuth web client and register the exact absolute `drivesnap.callback` URL for the app's prefix. Use `DRIVESNAP_GOOGLE_CLIENT_ID` and `DRIVESNAP_GOOGLE_CLIENT_SECRET`. Prefer a dedicated Google project; `drive.file`, `openid` and `email` are the requested scopes. Do not broaden scopes to troubleshoot a connection.

`revoke_on_disconnect` defaults to true. Revocation can affect other integrations in the same Google project; for deliberately shared projects, review `false` before disconnecting. Never disconnect a live integration merely to test UI changes.

Archives are encrypted before upload using a key derived from `APP_KEY`, or a dedicated `DRIVESNAP_ENCRYPTION_KEY`. Preserve recoverable keys separately from backups. Do not rotate keys or send/upload live database contents as an incidental diagnostic step.

## Queues, scheduling and readiness

Run the existing supervised worker with the configured backup queue (default `backups`) and configure Laravel's scheduler. Daily backups remain off until enabled by an authorized operator; do not enable schedules or shorten retention as a side effect of testing. Retention preserves the newest completed backup and requires successful remote deletion before removing its local copy/record.

`php artisan drivesnap:doctor` (or `--json`) runs sanitized CLI checks and caches results for the dashboard. Opening/polling the dashboard must not execute tools or call Google. Heartbeats mean a recent worker/scheduler signal was observed, not proof a daemon stays alive. An idle `queue:listen` may have no recent signal. Database connectivity does not prove dump privileges or recoverability.

## UI and host integrations

Published adapters use `resources/js/drivesnap/dashboard.js`, `.css` and `.d.ts`; share changes there across stacks rather than duplicating renderer logic. Backend-provided named-route URLs respect custom prefixes. Keep endpoint-origin validation, CSRF, accessible dialogs, permission-based actions and unsaved settings preservation.

Listen to `DriveSnap\BackupActivity` for the host's audit/notification conventions. Actions include requested, retried, completed, failed, downloaded and deleted. Backup IDs are UUID strings: do not write them into integer audit subject columns. Use `auditLogValues()` for sanitized metadata.

## Verify the changed behavior

Use the host's existing test runner for application integration changes. In the package source checkout, run `composer test` and `npm test`; Google HTTP responses are faked. Cover changed permission/failure boundaries. For UI changes, verify the relevant dialog/actions and mobile layout in the browser when available; distinguish automated checks from live testing.

A real recovery check decrypts into a new private output file with `drivesnap:decrypt` and restores into an isolated database. Never overwrite the running database. Live consent, upload, deletion or key changes require the user's task authorization; protect actual archives and clean up only identified disposable test artifacts. Report exactly what was verified and any remaining runtime dependencies.
