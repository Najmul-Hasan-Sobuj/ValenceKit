<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    Check,
    CheckCheck,
    RotateCcw,
    Search,
    X,
    ArrowLeft
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { roles as rolesRoute } from '@/routes/admin/authorization';
import { PERMISSION_GROUPS, allPermissionKeys, roles, type PermissionGroup } from './mockData';

const formRoleName = ref('');
const formRoleDescription = ref('');
const selectedPermissionIds = ref<string[]>(['users.view', 'content.view', 'content.create']);
const permissionSearchQuery = ref('');
const nameError = ref('');

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
    if (checked === true) {
        if (!selectedPermissionIds.value.includes(id)) {
            selectedPermissionIds.value.push(id);
        }
    } else {
        selectedPermissionIds.value = selectedPermissionIds.value.filter((p) => p !== id);
    }
}

function selectAllPermissions(): void {
    selectedPermissionIds.value = [...allPermissionKeys];
}

function clearAllPermissions(): void {
    selectedPermissionIds.value = [];
}

function toggleGroupPermissions(group: PermissionGroup): void {
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
    const trimmedName = formRoleName.value.trim();
    if (!trimmedName) {
        nameError.value = 'Role name is required';
        return;
    }

    const nextId = Math.max(...roles.value.map((r) => r.id), 0) + 1;
    roles.value.push({
        id: nextId,
        name: trimmedName,
        description: formRoleDescription.value.trim() || 'Custom access profile',
        members: 0,
        permissionKeys: [...selectedPermissionIds.value],
        system: false,
    });

    router.get(rolesRoute.url());
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Roles', href: rolesRoute.url() }, { title: 'Create Role', href: '' }] },
});
</script>

<template>
    <Head title="Create Role" />

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
                    <h1 class="text-xl font-bold tracking-tight">Create New Role</h1>
                    <p class="text-sm text-muted-foreground mt-1">Define a new role and configure its operational access boundaries.</p>
                </div>
            </div>
            <div class="flex items-center gap-3 self-end sm:self-auto">
                <Button variant="outline" as-child>
                    <Link :href="rolesRoute.url()">Cancel</Link>
                </Button>
                <Button :disabled="!formRoleName.trim()" @click="saveRole">
                    <Check class="size-4 mr-1.5" />
                    Create role
                </Button>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Role Details Form -->
            <div class="rounded-xl border bg-card p-5 shadow-xs">
                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="role-name" class="font-medium">
                            Role name
                            <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            id="role-name"
                            v-model="formRoleName"
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
                            placeholder="e.g. Manage content and media workflows"
                        />
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
                        <div class="flex items-center gap-2">
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
                                        : 'border-border/60 bg-background/50 hover:bg-muted/40 hover:border-border text-muted-foreground hover:text-foreground'
                                ]"
                            >
                                <Checkbox
                                    :model-value="isPermissionSelected(permission.id)"
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