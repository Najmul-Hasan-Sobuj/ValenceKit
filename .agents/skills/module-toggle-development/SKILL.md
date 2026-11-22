---
name: module-toggle-development
description: Add or modify ValenceKit business modules and features that administrators can enable or disable. Use whenever a module is introduced, removed, renamed, or connected to routes, navigation, settings, permissions, or tests.
---

# ValenceKit module toggles

Use the existing module system; do not create a second feature-flag mechanism or add a dependency. Read the current implementations before editing:

- `config/modules.php`
- `app/Models/Module.php`
- `app/Http/Middleware/EnsureModuleIsEnabled.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `routes/admin.php`
- `resources/js/layouts/admin/AdminSidebar.vue`
- `resources/js/layouts/admin/AdminSettingsLayout.vue`
- `resources/js/pages/admin/settings/Modules.vue`
- `tests/Feature/Admin/ModuleSettingsTest.php`

## Adding a module

1. Add one stable lowercase key to `config/modules.php` with its admin-facing name and description. Renaming a persisted key is a data migration, not a copy edit.
2. Wrap every private entry point for that module in `module:<key>` middleware. Include list pages, mutations, downloads, exports, callbacks, settings routes, and package-provided routes. A hidden link is not access control.
3. Keep permissions separate from module state. Access requires both an enabled module and the existing permission; disabling a module must not delete or rewrite role permissions.
4. Gate every related admin navigation item with `moduleEnabled('<key>')` as well as its permission. Check both `AdminSidebar.vue` and `AdminSettingsLayout.vue` when applicable.
5. Use the shared Inertia prop `moduleStates`. Never expose the shared states as `modules`: settings pages already use `modules` for the configured module list, and the duplicate name overwrites page data.
6. Default configured modules to enabled when no database row exists. Unknown keys stay disabled. This lets a newly deployed module appear without requiring a seed operation while preventing typos from opening routes.

If a module owns public or guest flows, decide explicitly whether disabling it should also block those routes. Protect them when the feature must be unavailable to customers, not only hidden from administrators.

## Verification

Extend `ModuleSettingsTest.php` for the new key and verify all of these behaviors:

- authorized admins can view and update the module state;
- unauthorized admins cannot view or update module settings;
- disabling returns `404` for representative GET and mutation routes;
- the sidebar/settings link disappears after disabling;
- permissions remain unchanged;
- re-enabling restores routes and navigation;
- an unpersisted configured module defaults to enabled;
- unknown module keys are rejected or remain disabled.

Run the narrow Pest tests, frontend type-check, production build, and browser-test the toggle as an authorized admin. Then directly visit a protected URL while disabled and verify a normal user cannot gain admin access. Follow the project formatter and testing rules for every changed PHP or test file.
