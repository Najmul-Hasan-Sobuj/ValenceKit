<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import {
    Check,
    CheckCheck,
    RotateCcw,
    Search,
    X,
    ArrowLeft,
    Lock
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { roles as rolesRoute } from '@/routes/admin/authorization';
import { PERMISSION_GROUPS, allPermissionKeys, roles, type PermissionGroup, type Role } from './mockData';

const page = usePage();
const roleId = page.props.roleId as number;

const formRoleName = ref('');
const formRoleDescription = ref('');
const selectedPermissionIds = ref<string[]>([]);
const permissionSearchQuery = ref('');
const nameError = ref('');
const selectedRole = ref<Role | null>(null);

onMounted(() => {
    const foundRole = roles.value.find((r) => r.id === roleId);
    if (foundRole) {
        selectedRole.value = foundRole;
        formRoleName.value = foundRole.name;
        formRoleDescription.value = foundRole.description;
        selectedPermissionIds.value = [...foundRole.permissionKeys];
    } else {
        router.get(rolesRoute.url());
    }
});

function permissionBadgeClass(permissionId: string): string {
    if (permissionId.includes('.delete') || permissionId.includes('mfa') || permissionId.includes('tokens') || permissionId.includes('export')) {
        return 'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400';
    }
    if (permissionId.includes('.edit') || permissionId.includes('.update') || permissionId.includes('.audit')) {
        return 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400';
    }
    if (permissionId.includes('.create') || permissionId.includes('.invite') || permissionId.includes('.roles')) {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
    }
    return 'border-blue-500/30 bg-blue-500/10 text-blue-600 dark:text-blue-400';
}

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
        router.get(rolesRoute.url());
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
    }

    router.get(rolesRoute.url());
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Roles', href: rolesRoute.url() }, { title: 'Edit Role', href: '' }] },
});
</script>

<template>
    <Head :title="selectedRole?.name ? `Edit ${selectedRole.name}` : 'Edit Role'" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <Button variant="outline" size="icon" as-child class="shrink-0">
                    <Link :href="rolesRoute.url()">
                        <ArrowLeft class="size-4" />
                        <span class="sr-only">Back</span>
                    </Link>
                </Button>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-bold tracking-tight">
                            {{ selectedRole?.system ? 'System Role Details' : 'Edit Role' }}
                        </h1>
                        <Badge
                            v-if="selectedRole?.system"
                            variant="outline"
                            class="border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400 gap-1"
                        >
                            <Lock class="size-3" />
                            Protected
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground mt-1">Configure permissions and attributes for this role profile.</p>
                </div>
            </div>
            <div class="flex items-center gap-3 self-end sm:self-auto">
                <Button variant="outline" as-child>
                    <Link :href="rolesRoute.url()">Cancel</Link>
                </Button>
                <Button :disabled="selectedRole?.system || !formRoleName.trim()" @click="saveRole">
                    <Check class="size-4 mr-1.5" />
                    Save changes
                </Button>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Left Column: Role Details -->
            <div class="space-y-4">
            <!-- System Role Notice -->
            <div
                v-if="selectedRole?.system"
                class="flex items-start gap-3 rounded-xl border border-amber-500/25 bg-amber-500/5 p-4 text-sm text-muted-foreground"
            >
                <Lock class="size-5 shrink-0 text-amber-500 mt-0.5" />
                <div class="space-y-1">
                    <p class="font-medium text-foreground">Core System Role</p>
                    <p>This role is foundational to platform administration. Its permissions and identity are protected to prevent accidental lockouts.</p>
                </div>
            </div>

            <!-- Role Details Form -->
            <div class="rounded-xl border bg-card p-5 shadow-xs">
                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="role-name" class="font-medium">
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
                        <p v-if="nameError" class="text-destructive text-sm">{{ nameError }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="role-description" class="font-medium">Description</Label>
                        <Input
                            id="role-description"
                            v-model="formRoleDescription"
                            :disabled="selectedRole?.system"
                            placeholder="e.g. Manage content and media workflows"
                        />
                    </div>
                </div>
            </div>
            </div>

            <!-- Permissions Section -->
            <div class="rounded-xl border bg-card p-5 shadow-xs space-y-4">
                <!-- Controls Bar -->
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b pb-4">
                    <div class="flex items-center gap-3">
                        <h3 class="text-base font-semibold text-foreground">Permissions</h3>
                        <Badge variant="outline" class="font-normal">
                            {{ selectedPermissionIds.length }} of {{ allPermissionKeys.length }} granted
                        </Badge>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="relative w-full sm:w-64">
                            <Search class="text-muted-foreground absolute top-2.5 left-3 size-4" />
                            <Input
                                v-model="permissionSearchQuery"
                                placeholder="Search permissions..."
                                class="pl-9 pr-8"
                                aria-label="Search permissions"
                            />
                            <button
                                v-if="permissionSearchQuery"
                                type="button"
                                class="text-muted-foreground hover:text-foreground absolute top-2.5 right-2"
                                @click="permissionSearchQuery = ''"
                            >
                                <X class="size-4" />
                                <span class="sr-only">Clear search</span>
                            </button>
                        </div>
                        <div v-if="!selectedRole?.system" class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                type="button"
                                class="gap-1.5"
                                @click="selectAllPermissions"
                            >
                                <CheckCheck class="size-4" />
                                Select all
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                type="button"
                                class="gap-1.5"
                                @click="clearAllPermissions"
                            >
                                <RotateCcw class="size-4" />
                                Clear
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Groups List -->
                <div v-if="filteredPermissionGroups.length > 0" class="grid gap-4 lg:grid-cols-2">
                    <div
                        v-for="group in filteredPermissionGroups"
                        :key="group.id"
                        class="flex flex-col rounded-xl border bg-card/60 p-4 shadow-xs gap-3"
                    >
                        <!-- Group Header -->
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex size-9 items-center justify-center rounded-lg bg-muted text-muted-foreground shrink-0">
                                    <component :is="group.icon" class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-sm font-semibold text-foreground">{{ group.name }}</span>
                                        <Badge variant="secondary" class="font-normal px-2 py-0.5">
                                            {{ groupSelectedCount(group) }}/{{ group.permissions.length }}
                                        </Badge>
                                    </div>
                                    <p class="text-sm text-muted-foreground">{{ group.description }}</p>
                                </div>
                            </div>
                            <Button
                                v-if="!selectedRole?.system"
                                variant="ghost"
                                type="button"
                                class="text-sm text-muted-foreground hover:text-foreground self-start sm:self-auto"
                                @click="toggleGroupPermissions(group)"
                            >
                                {{ isGroupAllSelected(group) ? 'Deselect group' : 'Select group' }}
                            </Button>
                        </div>

                        <!-- Group Permissions Grid -->
                        <div class="grid gap-2 grid-cols-1">
                            <label
                                v-for="permission in group.permissions"
                                :key="permission.id"
                                :class="[
                                    'flex items-start gap-2.5 rounded-lg border p-3 cursor-pointer transition-all select-none',
                                    isPermissionSelected(permission.id)
                                        ? 'border-primary/40 bg-primary/5 text-foreground shadow-xs'
                                        : 'border-border/60 bg-background/50 hover:bg-muted/40 hover:border-border text-muted-foreground hover:text-foreground',
                                    selectedRole?.system ? 'cursor-not-allowed opacity-80' : ''
                                ]"
                            >
                                <Checkbox
                                    :model-value="isPermissionSelected(permission.id)"
                                    :disabled="selectedRole?.system"
                                    class="mt-1"
                                    @update:model-value="togglePermission(permission.id, $event)"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-sm font-medium leading-none text-foreground">{{ permission.name }}</span>
                                        <Badge
                                            class="text-xs px-2 py-0.5 font-normal border"
                                            :class="permissionBadgeClass(permission.id)"
                                            variant="outline"
                                        >
                                            {{ permission.id.split('.')[1] ?? permission.id.split('.')[0] }}
                                        </Badge>
                                    </div>
                                    <div class="text-sm text-muted-foreground mt-1.5 leading-relaxed">
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
                    class="flex flex-col items-center justify-center py-12 text-center rounded-xl border border-dashed bg-muted/10 px-4"
                >
                    <div class="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground mb-4">
                        <Search class="size-5" />
                    </div>
                    <p class="text-base font-medium text-foreground">No matching permissions found</p>
                    <p class="text-sm text-muted-foreground mt-1 max-w-sm">
                        No permissions match "{{ permissionSearchQuery }}". Try searching for another keyword like "users", "content", or "settings".
                    </p>
                    <Button
                        variant="outline"
                        class="mt-4"
                        @click="permissionSearchQuery = ''"
                    >
                        Clear search filter
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>