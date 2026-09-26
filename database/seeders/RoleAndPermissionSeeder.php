<?php

namespace Database\Seeders;

use App\Enums\SystemRoleStatus;
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

        'Role Management' => [
            'roles.view' => 'Browse and inspect role definitions',
            'roles.create' => 'Create new access roles',
            'roles.edit' => 'Update role permissions and details',
            'roles.delete' => 'Remove custom roles from the system',
        ],

        'System Settings' => [
            'settings.profile.view' => 'View admin profile settings tab',
            'settings.profile.edit' => 'Update admin profile name and email',
            'settings.security.view' => 'View security credentials and passkeys tab',
            'settings.security.edit' => 'Update password and manage passkeys',
            'settings.appearance.view' => 'View theme and appearance preferences tab',
            'settings.appearance.edit' => 'Customize theme and visual preferences',
            'settings.mcp.view' => 'View MCP (AI Tools) integrations and tokens tab',
            'settings.mcp.edit' => 'Manage MCP tools and generate new AI tokens',
            'settings.mcp.usage.view' => 'View MCP usage logs to monitor who uses the server',
        ],
    ];

    /**
     * Roles and their attributes and permissions.
     *
     * @var array<string, array{description: string, is_system: SystemRoleStatus, permissions: list<string>|string}>
     */
    private const ROLES = [
        'Super Admin' => [
            'description' => 'Unrestricted access to all system features',
            'is_system' => SystemRoleStatus::System,
            'permissions' => '*',
        ],
        'Staff' => [
            'description' => 'Manage user accounts and view system settings',
            'is_system' => SystemRoleStatus::Custom,
            'permissions' => [
                'users.view',
                'users.invite',
                'users.edit',
                'users.delete',
                'settings.profile.view',
                'settings.profile.edit',
                'settings.security.view',
                'settings.appearance.view',
                'settings.mcp.view',
            ],
        ],
        'Support' => [
            'description' => 'Inspect user directory and view appearance settings',
            'is_system' => SystemRoleStatus::Custom,
            'permissions' => [
                'users.view',
                'settings.profile.view',
                'settings.appearance.view',
            ],
        ],
    ];

    public function run(): void
    {
        // Delete obsolete and dummy permissions
        Permission::whereIn('name', [
            'settings.roles',
            'settings.view',
            'settings.edit',
            'settings.audit',
            'content.view',
            'content.create',
            'content.edit',
            'content.delete',
            'security.mfa',
            'security.tokens',
            'security.export',
        ])->delete();

        // Delete obsolete dummy roles like Content Manager
        Role::where('name', 'Content Manager')->delete();

        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'admin';
        $allPermissions = [];

        // Sync permissions — creates missing, updates group_name if needed
        foreach (self::GROUPS as $groupName => $permissions) {
            foreach ($permissions as $name => $description) {
                $permission = Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => $guard],
                    ['group_name' => $groupName],
                );
                $permission->update(['group_name' => $groupName]);
                $allPermissions[] = $permission;
            }
        }

        // Sync roles and attach permissions
        foreach (self::ROLES as $roleName => $roleData) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => $guard],
                [
                    'description' => $roleData['description'],
                    'is_system' => $roleData['is_system']->value,
                ]
            );

            // In case the role already existed, ensure attributes are up-to-date
            $role->update([
                'description' => $roleData['description'],
                'is_system' => $roleData['is_system']->value,
            ]);

            if ($roleData['permissions'] === '*') {
                $role->syncPermissions($allPermissions);
            } else {
                $role->syncPermissions(
                    collect($allPermissions)->whereIn('name', $roleData['permissions'])->all(),
                );
            }
        }
    }
}
