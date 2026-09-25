<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds roles and permissions for the admin panel.
 *
 * Run after every new module or feature that introduces new permissions.
 * Structure: each group maps to a `group_name` on the permissions table,
 * making it trivial to display grouped permissions in the UI.
 *
 * @see Permission::$group_name
 */
class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Organized permission groups.
     *
     * Add new modules here as the application grows.
     * Format: 'Group Name' => ['permission.action' => 'Human description']
     *
     * @var array<string, array<string, string>>
     */
    private const GROUPS = [
        'User Management' => [
            'users.view' => 'Browse directory and inspect admin accounts',
            'users.invite' => 'Send email invitations to new teammates',
            'users.edit' => 'Update profile details, roles, and status',
            'users.delete' => 'Revoke admin access and remove profiles',
        ],

        'Content & Media' => [
            'content.view' => 'Access draft, scheduled, and published content',
            'content.create' => 'Draft articles, add pages, and upload media',
            'content.edit' => 'Update existing articles, layout, and metadata',
            'content.delete' => 'Permanently remove articles and revisions',
        ],

        'System Settings' => [
            'settings.view' => 'Inspect system setup and environment variables',
            'settings.edit' => 'Modify application preferences and branding',
            'settings.roles' => 'Create and adjust access control permissions',
            'settings.audit' => 'Inspect audit trail and security activity logs',
        ],

        'Security & Integrations' => [
            'security.mfa' => 'Require two-factor authentication for admins',
            'security.tokens' => 'Issue and revoke programmatic API keys',
            'security.export' => 'Generate and download data archives',
        ],
    ];

    /**
     * Roles and the permissions they receive.
     *
     * '*' grants all permissions (Super Admin).
     *
     * @var array<string, list<string>|string>
     */
    private const ROLES = [
        'Super Admin' => '*',
        'Content Manager' => [
            'users.view',
            'content.view',
            'content.create',
            'content.edit',
            'settings.view',
        ],
        'Support' => [
            'users.view',
            'content.view',
            'settings.audit',
        ],
    ];

    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'admin';
        $allPermissions = [];

        // Sync permissions — creates missing, skips existing
        foreach (self::GROUPS as $groupName => $permissions) {
            foreach ($permissions as $name => $description) {
                $allPermissions[] = Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => $guard],
                    ['group_name' => $groupName],
                );
            }
        }

        // Sync roles and attach permissions
        foreach (self::ROLES as $roleName => $permissionKeys) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => $guard],
            );

            if ($permissionKeys === '*') {
                $role->syncPermissions($allPermissions);
            } else {
                $role->syncPermissions(
                    collect($allPermissions)->whereIn('name', $permissionKeys)->all(),
                );
            }
        }
    }
}
