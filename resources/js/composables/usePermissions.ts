import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { Admin } from '@/types';

/**
 * Global composable to handle admin roles and permission checks across the UI.
 */
export function usePermissions() {
    const page = usePage();
    const admin = computed<Admin | null>(() => (page.props.auth?.admin as Admin) || null);

    const roles = computed<string[]>(() => admin.value?.roles || []);
    const permissions = computed<string[]>(() => admin.value?.permissions || []);

    const isSuperAdmin = computed<boolean>(() => roles.value.includes('Super Admin'));

    /**
     * Check if the current admin has a given permission.
     * Super Admins always return true.
     */
    function can(permission: string): boolean {
        if (isSuperAdmin.value) {
            return true;
        }
        return permissions.value.includes(permission);
    }

    /**
     * Check if the current admin has ANY of the given permissions.
     */
    function canAny(perms: string[]): boolean {
        if (isSuperAdmin.value) {
            return true;
        }
        return perms.some((p) => permissions.value.includes(p));
    }

    /**
     * Check if the current admin has ALL of the given permissions.
     */
    function canAll(perms: string[]): boolean {
        if (isSuperAdmin.value) {
            return true;
        }
        return perms.every((p) => permissions.value.includes(p));
    }

    /**
     * Check if the current admin has a specific role.
     */
    function hasRole(roleName: string): boolean {
        return roles.value.includes(roleName);
    }

    /**
     * Check if the current admin can edit a target user account.
     * Non-super admins cannot edit Super Admin accounts.
     */
    function canEditUser(targetUser: any): boolean {
        if (!can('users.edit')) {
            return false;
        }
        const targetIsSuperAdmin = targetUser?.roles?.some(
            (r: any) => r.name === 'Super Admin' || r === 'Super Admin',
        );
        if (targetIsSuperAdmin && !isSuperAdmin.value) {
            return false;
        }
        return true;
    }

    /**
     * Check if the current admin can delete a target user account.
     * Admins cannot delete themselves, and non-super admins cannot delete Super Admin accounts.
     */
    function canDeleteUser(targetUser: any): boolean {
        if (!can('users.delete')) {
            return false;
        }
        if (admin.value?.id === targetUser?.id) {
            return false;
        }
        const targetIsSuperAdmin = targetUser?.roles?.some(
            (r: any) => r.name === 'Super Admin' || r === 'Super Admin',
        );
        if (targetIsSuperAdmin && !isSuperAdmin.value) {
            return false;
        }
        return true;
    }

    /**
     * Check if the current admin can assign a specific role.
     * Only Super Admins can assign the 'Super Admin' role.
     */
    function canAssignRole(roleName: string): boolean {
        if (roleName === 'Super Admin' && !isSuperAdmin.value) {
            return false;
        }
        return true;
    }

    /**
     * Filter a list of roles to only those the current admin is allowed to assign.
     */
    function filterAssignableRoles<T extends { name: string }>(roleList: T[]): T[] {
        if (isSuperAdmin.value) {
            return roleList;
        }
        return roleList.filter((r) => r.name !== 'Super Admin');
    }

    /**
     * Check if the current admin can edit a given role.
     * Non-super admins cannot edit system roles.
     */
    function canEditRole(role: any): boolean {
        if (!can('roles.edit')) {
            return false;
        }
        const isSystem = role?.system || role?.is_system;
        if (isSystem && !isSuperAdmin.value) {
            return false;
        }
        return true;
    }

    /**
     * Check if the current admin can delete a given role.
     * System roles cannot be deleted.
     */
    function canDeleteRole(role: any): boolean {
        if (!can('roles.delete')) {
            return false;
        }
        const isSystem = role?.system || role?.is_system;
        if (isSystem) {
            return false;
        }
        return true;
    }

    /**
     * Check if the current admin can duplicate a given role.
     * System roles can only be duplicated by Super Admins.
     */
    function canDuplicateRole(role: any): boolean {
        if (!can('roles.create')) {
            return false;
        }
        const isSystem = role?.system || role?.is_system;
        if (isSystem && !isSuperAdmin.value) {
            return false;
        }
        return true;
    }

    return {
        admin,
        roles,
        permissions,
        isSuperAdmin,
        can,
        canAny,
        canAll,
        hasRole,
        canEditUser,
        canDeleteUser,
        canAssignRole,
        filterAssignableRoles,
        canEditRole,
        canDeleteRole,
        canDuplicateRole,
    };
}

