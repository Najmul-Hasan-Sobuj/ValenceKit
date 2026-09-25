import { FileText, Settings, ShieldCheck, Users } from '@lucide/vue';
import { ref } from 'vue';

export type Permission = {
    id: string;
    name: string;
    description: string;
};

export type PermissionGroup = {
    id: string;
    name: string;
    description: string;
    icon: any;
    permissions: Permission[];
};

export type Role = {
    id: number;
    name: string;
    description: string;
    members: number;
    permissionKeys: string[];
    system?: boolean;
};

export const PERMISSION_GROUPS: PermissionGroup[] = [
    {
        id: 'user_management',
        name: 'User Management',
        description: 'Manage administrator accounts, invitations, and profiles',
        icon: Users,
        permissions: [
            { id: 'users.view', name: 'View users', description: 'Browse directory and inspect admin accounts' },
            { id: 'users.invite', name: 'Invite users', description: 'Send email invitations to new teammates' },
            { id: 'users.edit', name: 'Edit users', description: 'Update profile details, roles, and status' },
            { id: 'users.delete', name: 'Delete users', description: 'Revoke admin access and remove profiles' },
        ],
    },
    {
        id: 'content',
        name: 'Content & Media',
        description: 'Publish, organize, and revise public website content',
        icon: FileText,
        permissions: [
            { id: 'content.view', name: 'View content', description: 'Access draft, scheduled, and published content' },
            { id: 'content.create', name: 'Create content', description: 'Draft articles, add pages, and upload media' },
            { id: 'content.edit', name: 'Edit content', description: 'Update existing articles, layout, and metadata' },
            { id: 'content.delete', name: 'Delete content', description: 'Permanently remove articles and revisions' },
        ],
    },
    {
        id: 'settings',
        name: 'System Settings',
        description: 'Configure environment parameters and platform preferences',
        icon: Settings,
        permissions: [
            { id: 'settings.view', name: 'View settings', description: 'Inspect system setup and environment variables' },
            { id: 'settings.edit', name: 'Edit settings', description: 'Modify application preferences and branding' },
            { id: 'settings.roles', name: 'Manage roles', description: 'Create and adjust access control permissions' },
            { id: 'settings.audit', name: 'View audit log', description: 'Inspect audit trail and security activity logs' },
        ],
    },
    {
        id: 'security',
        name: 'Security & Integrations',
        description: 'Control security policies, 2FA rules, and API endpoints',
        icon: ShieldCheck,
        permissions: [
            { id: 'security.mfa', name: 'Enforce 2FA', description: 'Require two-factor authentication for admins' },
            { id: 'security.tokens', name: 'API token management', description: 'Issue and revoke programmatic API keys' },
            { id: 'security.export', name: 'Export system data', description: 'Generate and download data archives' },
        ],
    },
];

export const allPermissionKeys = PERMISSION_GROUPS.flatMap((group) => group.permissions.map((p) => p.id));

export const roles = ref<Role[]>([
    {
        id: 1,
        name: 'Super Admin',
        description: 'Complete access to every admin area and security control',
        members: 1,
        permissionKeys: [...allPermissionKeys],
        system: true,
    },
    {
        id: 2,
        name: 'Content Manager',
        description: 'Manage content and media workflows across the platform',
        members: 2,
        permissionKeys: ['users.view', 'content.view', 'content.create', 'content.edit', 'settings.view'],
    },
    {
        id: 3,
        name: 'Support',
        description: 'View users, content, and resolve support requests',
        members: 1,
        permissionKeys: ['users.view', 'content.view', 'settings.audit'],
    },
]);
