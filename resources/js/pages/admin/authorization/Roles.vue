<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    Check,
    CheckCheck,
    FileText,
    KeyRound,
    Lock,
    MoreHorizontal,
    Plus,
    RotateCcw,
    Search,
    Settings,
    ShieldCheck,
    Trash2,
    Users,
    X,
} from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { roles as rolesRoute } from '@/routes/admin/authorization';

type Permission = {
    id: string;
    name: string;
    description: string;
};

type PermissionGroup = {
    id: string;
    name: string;
    description: string;
    icon: any;
    permissions: Permission[];
};

type Role = {
    id: number;
    name: string;
    description: string;
    members: number;
    permissionKeys: string[];
    system?: boolean;
};

const PERMISSION_GROUPS: PermissionGroup[] = [
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

const allPermissionKeys = PERMISSION_GROUPS.flatMap((group) => group.permissions.map((p) => p.id));

const roles = ref<Role[]>([
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

const selectedRole = ref<Role | null>(null);
const drawerOpen = ref(false);
const deleteDialogOpen = ref(false);

const formRoleName = ref('');
const formRoleDescription = ref('');
const selectedPermissionIds = ref<string[]>([]);
const permissionSearchQuery = ref('');
const nameError = ref('');

const search = ref('');
const selectedType = ref('All types');
const currentPage = ref(1);
const pageSize = 3;
const roleTypes = ['All types', 'System', 'Custom'];

const filteredRoles = computed(() =>
    roles.value.filter((role) => {
        const matchesSearch = `${role.name} ${role.description}`.toLowerCase().includes(search.value.toLowerCase());
        const roleType = role.system ? 'System' : 'Custom';
        const matchesType = !selectedType.value || selectedType.value === 'All types' || selectedType.value === roleType;

        return matchesSearch && matchesType;
    }),
);

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRoles.value.length / pageSize)));
const paginatedRoles = computed(() =>
    filteredRoles.value.slice((currentPage.value - 1) * pageSize, currentPage.value * pageSize),
);
const pageNumbers = computed(() => Array.from({ length: totalPages.value }, (_, index) => index + 1));

const filteredPermissionGroups = computed(() => {
    const q = permissionSearchQuery.value.trim().toLowerCase();
    if (!q) {
        return PERMISSION_GROUPS;
    }
    return PERMISSION_GROUPS.map((group) => {
        const matching = group.permissions.filter((p) =>
            p.name.toLowerCase().includes(q) ||
            p.description.toLowerCase().includes(q) ||
            group.name.toLowerCase().includes(q),
        );
        return {
            ...group,
            permissions: matching,
        };
    }).filter((group) => group.permissions.length > 0);
});

watch([search, selectedType], () => {
    currentPage.value = 1;
});

watch(totalPages, (value) => {
    currentPage.value = Math.min(currentPage.value, value);
});

function openRoleDrawer(role?: Role): void {
    selectedRole.value = role ?? null;
    nameError.value = '';
    permissionSearchQuery.value = '';

    if (role) {
        formRoleName.value = role.name;
        formRoleDescription.value = role.description;
        selectedPermissionIds.value = [...role.permissionKeys];
    } else {
        formRoleName.value = '';
        formRoleDescription.value = '';
        selectedPermissionIds.value = ['users.view', 'content.view', 'content.create'];
    }

    drawerOpen.value = true;
}

function closeDrawer(): void {
    drawerOpen.value = false;
    nameError.value = '';
}

function isPermissionSelected(id: string): boolean {
    return selectedPermissionIds.value.includes(id);
}

function togglePermission(id: string, checked: boolean | 'indeterminate'): void {
    if (selectedRole.value?.system) return;

    if (checked === true) {
        if (!selectedPermissionIds.value.includes(id)) {
            selectedPermissionIds.value.push(id);
        }
    } else {
        selectedPermissionIds.value = selectedPermissionIds.value.filter((p) => p !== id);
    }
}

function selectAllPermissions(): void {
    if (selectedRole.value?.system) return;
    selectedPermissionIds.value = [...allPermissionKeys];
}

function clearAllPermissions(): void {
    if (selectedRole.value?.system) return;
    selectedPermissionIds.value = [];
}

function toggleGroupPermissions(group: PermissionGroup): void {
    if (selectedRole.value?.system) return;

    const groupIds = group.permissions.map((p) => p.id);
    const allSelected = groupIds.every((id) => selectedPermissionIds.value.includes(id));

    if (allSelected) {
        selectedPermissionIds.value = selectedPermissionIds.value.filter((id) => !groupIds.includes(id));
    } else {
        const toAdd = groupIds.filter((id) => !selectedPermissionIds.value.includes(id));
        selectedPermissionIds.value.push(...toAdd);
    }
}

function isGroupAllSelected(group: PermissionGroup): boolean {
    return group.permissions.every((p) => selectedPermissionIds.value.includes(p.id));
}

function groupSelectedCount(group: PermissionGroup): number {
    return group.permissions.filter((p) => selectedPermissionIds.value.includes(p.id)).length;
}

function saveRole(): void {
    if (selectedRole.value?.system) {
        closeDrawer();
        return;
    }

    const trimmedName = formRoleName.value.trim();
    if (!trimmedName) {
        nameError.value = 'Role name is required';
        return;
    }

    if (selectedRole.value) {
        const target = roles.value.find((r) => r.id === selectedRole.value!.id);
        if (target) {
            target.name = trimmedName;
            target.description = formRoleDescription.value.trim() || 'Custom access profile';
            target.permissionKeys = [...selectedPermissionIds.value];
        }
    } else {
        const nextId = Math.max(...roles.value.map((r) => r.id), 0) + 1;
        roles.value.push({
            id: nextId,
            name: trimmedName,
            description: formRoleDescription.value.trim() || 'Custom access profile',
            members: 0,
            permissionKeys: [...selectedPermissionIds.value],
            system: false,
        });
    }

    closeDrawer();
}

function removeRole(): void {
    if (selectedRole.value && !selectedRole.value.system) {
        roles.value = roles.value.filter((role) => role.id !== selectedRole.value?.id);
    }
    deleteDialogOpen.value = false;
    closeDrawer();
}

function goToPage(page: number): void {
    currentPage.value = Math.min(Math.max(page, 1), totalPages.value);
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Roles', href: rolesRoute() }] },
});
</script>

<template>
    <Head title="Admin Roles" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <Heading
                variant="small"
                title="Roles"
                description="Create reusable access profiles for your administrators"
            />
            <Button class="w-fit" @click="openRoleDrawer()">
                <Plus class="size-4" />
                Create role
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Roles</div>
                <div class="mt-2 text-2xl font-semibold">{{ roles.length }}</div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Total permissions</div>
                <div class="mt-2 text-2xl font-semibold">{{ allPermissionKeys.length }}</div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Assigned admins</div>
                <div class="mt-2 text-2xl font-semibold">{{ roles.reduce((total, role) => total + role.members, 0) }}</div>
            </div>
        </div>

        <div class="grid gap-3 rounded-xl border bg-card p-3 shadow-xs md:grid-cols-[minmax(0,1fr)_14rem] md:items-center">
            <div class="relative min-w-0">
                <Search class="text-muted-foreground absolute top-2.5 left-3 size-4" />
                <Input v-model="search" class="pl-9" placeholder="Search roles" aria-label="Search roles" />
            </div>
            <Combobox v-model="selectedType">
                <ComboboxAnchor class="w-full">
                    <ComboboxInput
                        aria-label="Filter by type"
                        :display-value="(value) => String(value || 'All types')"
                        placeholder="Filter by type"
                    />
                </ComboboxAnchor>
                <ComboboxList>
                    <ComboboxEmpty>No matching types.</ComboboxEmpty>
                    <ComboboxItem v-for="type in roleTypes" :key="type" :value="type">{{ type }}</ComboboxItem>
                </ComboboxList>
            </Combobox>
        </div>

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-5">Role</TableHead>
                        <TableHead>Members</TableHead>
                        <TableHead>Permissions</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead class="w-12 pr-5"><span class="sr-only">Actions</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="role in paginatedRoles"
                        :key="role.id"
                        class="cursor-pointer"
                        @click="openRoleDrawer(role)"
                    >
                        <TableCell class="pl-5">
                            <div class="flex items-center gap-3">
                                <div class="bg-primary/10 text-primary flex size-9 items-center justify-center rounded-lg">
                                    <KeyRound class="size-4" />
                                </div>
                                <div>
                                    <div class="font-medium">{{ role.name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ role.description }}</div>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>{{ role.members }}</TableCell>
                        <TableCell>
                            <Badge variant="secondary">{{ role.permissionKeys.length }} permissions</Badge>
                        </TableCell>
                        <TableCell>
                            <Badge :variant="role.system ? 'outline' : 'default'">
                                {{ role.system ? 'System' : 'Custom' }}
                            </Badge>
                        </TableCell>
                        <TableCell class="pr-5 text-right">
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                aria-label="Open role actions"
                                @click.stop="openRoleDrawer(role)"
                            >
                                <MoreHorizontal class="size-4" />
                            </Button>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="paginatedRoles.length === 0">
                        <TableCell colspan="5" class="text-muted-foreground h-24 text-center">
                            No roles found matching your criteria.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div class="flex flex-col justify-between gap-3 text-sm sm:flex-row sm:items-center">
            <p class="text-muted-foreground">Showing {{ paginatedRoles.length }} of {{ filteredRoles.length }} roles</p>
            <div class="flex items-center gap-1">
                <Button variant="outline" size="sm" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">
                    Previous
                </Button>
                <Button
                    v-for="page in pageNumbers"
                    :key="page"
                    :variant="page === currentPage ? 'default' : 'outline'"
                    size="sm"
                    class="min-w-9"
                    @click="goToPage(page)"
                >
                    {{ page }}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="currentPage === totalPages"
                    @click="goToPage(currentPage + 1)"
                >
                    Next
                </Button>
            </div>
        </div>
    </div>

    <!-- Add / Edit Role Drawer -->
    <Drawer v-model:open="drawerOpen">
        <DrawerContent class="md:inset-x-auto md:right-2 md:left-64 h-[88vh] max-h-[88vh] p-0 pt-2.5 gap-0 border-x rounded-t-2xl shadow-2xl flex flex-col overflow-hidden">
            <!-- Header (Pinned at top) -->
            <DrawerHeader class="border-b px-6 pt-2 pb-4 shrink-0 bg-background pr-14 text-left">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary shrink-0">
                        <KeyRound class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <DrawerTitle class="text-lg font-semibold tracking-tight">
                                {{ selectedRole ? (selectedRole.system ? 'System Role Details' : 'Edit Role') : 'Create New Role' }}
                            </DrawerTitle>
                            <Badge
                                v-if="selectedRole?.system"
                                variant="outline"
                                class="border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400 gap-1 text-xs font-normal"
                            >
                                <Lock class="size-3" />
                                Protected System Role
                            </Badge>
                            <Badge v-else-if="selectedRole" variant="secondary" class="text-xs font-normal">
                                Custom Access Profile
                            </Badge>
                        </div>
                        <DrawerDescription class="text-xs text-muted-foreground mt-0.5">
                            {{ selectedRole ? 'Configure permissions and attributes for this role profile.' : 'Define a new role and configure its operational access boundaries.' }}
                        </DrawerDescription>
                    </div>
                </div>
            </DrawerHeader>

            <!-- Scrollable Body -->
            <div class="flex-1 overflow-y-auto px-6 py-5 space-y-6">
                <!-- System Role Notice -->
                <div
                    v-if="selectedRole?.system"
                    class="flex items-start gap-3 rounded-xl border border-amber-500/25 bg-amber-500/5 p-4 text-xs text-muted-foreground"
                >
                    <Lock class="size-4 shrink-0 text-amber-500 mt-0.5" />
                    <div class="space-y-0.5">
                        <p class="font-medium text-foreground">Core System Role</p>
                        <p>This role is foundational to platform administration. Its permissions and identity are protected to prevent accidental lockouts.</p>
                    </div>
                </div>

                <!-- Role Details Form -->
                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="role-name" class="text-xs font-medium">
                                Role name
                                <span v-if="!selectedRole?.system" class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="role-name"
                                v-model="formRoleName"
                                :disabled="selectedRole?.system"
                                placeholder="e.g. Content Manager"
                                :class="nameError ? 'border-destructive focus-visible:ring-destructive' : ''"
                            />
                            <p v-if="nameError" class="text-destructive text-xs">{{ nameError }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="role-description" class="text-xs font-medium">Description</Label>
                            <Input
                                id="role-description"
                                v-model="formRoleDescription"
                                :disabled="selectedRole?.system"
                                placeholder="e.g. Manage content and media workflows"
                            />
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- Permissions Section -->
                <div class="space-y-4">
                    <!-- Controls Bar -->
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-foreground">Permissions</h3>
                            <Badge variant="outline" class="font-normal text-xs">
                                {{ selectedPermissionIds.length }} of {{ allPermissionKeys.length }} granted
                            </Badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="relative w-full sm:w-56">
                                <Search class="text-muted-foreground absolute top-2.5 left-2.5 size-3.5" />
                                <Input
                                    v-model="permissionSearchQuery"
                                    placeholder="Search permissions..."
                                    class="h-8 pl-8 pr-7 text-xs"
                                    aria-label="Search permissions"
                                />
                                <button
                                    v-if="permissionSearchQuery"
                                    type="button"
                                    class="text-muted-foreground hover:text-foreground absolute top-2 right-2"
                                    @click="permissionSearchQuery = ''"
                                >
                                    <X class="size-3.5" />
                                    <span class="sr-only">Clear search</span>
                                </button>
                            </div>
                            <div v-if="!selectedRole?.system" class="flex items-center gap-1.5">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    type="button"
                                    class="h-8 text-xs gap-1"
                                    @click="selectAllPermissions"
                                >
                                    <CheckCheck class="size-3.5" />
                                    Select all
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    type="button"
                                    class="h-8 text-xs gap-1"
                                    @click="clearAllPermissions"
                                >
                                    <RotateCcw class="size-3.5" />
                                    Clear
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Groups List -->
                    <div v-if="filteredPermissionGroups.length > 0" class="space-y-4">
                        <div
                            v-for="group in filteredPermissionGroups"
                            :key="group.id"
                            class="rounded-xl border bg-card/60 p-4 shadow-xs space-y-3"
                        >
                            <!-- Group Header -->
                            <div class="flex flex-col gap-2 pb-2.5 border-b sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex size-7 items-center justify-center rounded-lg bg-muted text-muted-foreground shrink-0">
                                        <component :is="group.icon" class="size-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-semibold text-foreground">{{ group.name }}</span>
                                            <Badge variant="secondary" class="text-[11px] font-normal px-1.5 py-0 h-4">
                                                {{ groupSelectedCount(group) }}/{{ group.permissions.length }}
                                            </Badge>
                                        </div>
                                        <p class="text-xs text-muted-foreground">{{ group.description }}</p>
                                    </div>
                                </div>
                                <Button
                                    v-if="!selectedRole?.system"
                                    variant="ghost"
                                    type="button"
                                    class="h-7 px-2.5 text-xs text-muted-foreground hover:text-foreground self-start sm:self-auto"
                                    @click="toggleGroupPermissions(group)"
                                >
                                    {{ isGroupAllSelected(group) ? 'Deselect group' : 'Select group' }}
                                </Button>
                            </div>

                            <!-- Group Permissions Grid -->
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="permission in group.permissions"
                                    :key="permission.id"
                                    :class="[
                                        'flex items-start gap-3 rounded-lg border p-3 cursor-pointer transition-all select-none',
                                        isPermissionSelected(permission.id)
                                            ? 'border-primary/40 bg-primary/5 text-foreground shadow-xs'
                                            : 'border-border/60 bg-background/50 hover:bg-muted/40 hover:border-border text-muted-foreground hover:text-foreground',
                                        selectedRole?.system ? 'cursor-not-allowed opacity-80' : ''
                                    ]"
                                >
                                    <Checkbox
                                        :model-value="isPermissionSelected(permission.id)"
                                        :disabled="selectedRole?.system"
                                        class="mt-0.5"
                                        @update:model-value="togglePermission(permission.id, $event)"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-medium leading-none text-foreground">
                                            {{ permission.name }}
                                        </div>
                                        <div class="text-xs text-muted-foreground mt-1 leading-snug">
                                            {{ permission.description }}
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state if search returns nothing -->
                    <div
                        v-else
                        class="flex flex-col items-center justify-center py-10 text-center rounded-xl border border-dashed bg-muted/10 px-4"
                    >
                        <div class="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground mb-3">
                            <Search class="size-4" />
                        </div>
                        <p class="text-sm font-medium text-foreground">No matching permissions found</p>
                        <p class="text-xs text-muted-foreground mt-1 max-w-sm">
                            No permissions match "{{ permissionSearchQuery }}". Try searching for another keyword like "users", "content", or "settings".
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            class="mt-3 text-xs"
                            @click="permissionSearchQuery = ''"
                        >
                            Clear search filter
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Footer (Pinned at bottom) -->
            <DrawerFooter class="mt-0 border-t bg-card/60 backdrop-blur px-6 py-3.5 shrink-0 flex flex-row items-center justify-between gap-3">
                <div class="flex items-center">
                    <AlertDialog v-if="selectedRole && !selectedRole.system" v-model:open="deleteDialogOpen">
                        <AlertDialogTrigger as-child>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:text-destructive hover:bg-destructive/10 gap-1.5"
                            >
                                <Trash2 class="size-4" />
                                Delete role
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Delete {{ selectedRole.name }}?</AlertDialogTitle>
                                <AlertDialogDescription>
                                    This removes the role from your authorization setup. Admin users will keep their accounts but lose this role's specific permissions.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <AlertDialogAction
                                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                    @click="removeRole"
                                >
                                    Delete role
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                    <span v-else />
                </div>

                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="closeDrawer">
                        Cancel
                    </Button>
                    <Button
                        size="sm"
                        :disabled="selectedRole?.system || !formRoleName.trim()"
                        @click="saveRole"
                    >
                        <Check class="size-4 mr-1.5" />
                        {{ selectedRole ? 'Save changes' : 'Create role' }}
                    </Button>
                </div>
            </DrawerFooter>
        </DrawerContent>
    </Drawer>
</template>