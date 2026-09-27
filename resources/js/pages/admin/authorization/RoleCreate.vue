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
import { useForm } from '@inertiajs/vue3';
import { FileText, KeyRound, Settings as SettingsIcon, ShieldCheck, Users, FolderOpen, Wallet } from '@lucide/vue';
import authorization from '@/routes/admin/authorization';
const rolesRoute = authorization.roles;

const props = defineProps<{
    permissions: Record<string, { id: number; name: string; guard_name: string; group_name: string }[]>;
    initialRole?: {
        source_id?: number;
        source_name?: string;
        name: string;
        description: string | null;
        permissions: string[];
    } | null;
}>();

const PERMISSION_GROUPS_META: Record<string, { description: string; icon: any }> = {
    'User Management': { description: 'Manage administrator accounts, invitations, and profiles', icon: Users },
    'Role Management': { description: 'Manage access control roles, privileges, and boundaries', icon: KeyRound },
    'System Settings': { description: 'Configure profile, security, and appearance settings', icon: SettingsIcon },
    'Category Management': { description: 'Manage dynamic categories for all modules', icon: FolderOpen },
    'Finance Tracker': { description: 'Manage financial transactions and budgets', icon: Wallet },
};

const PERMISSION_GROUPS = computed(() => {
    return Object.entries(props.permissions).map(([groupName, perms]) => {
        const meta = PERMISSION_GROUPS_META[groupName] || { description: '', icon: SettingsIcon };
        return {
            id: groupName,
            name: groupName,
            description: meta.description,
            icon: meta.icon,
            permissions: perms.map((p) => ({
                id: p.name,
                name: p.name,
                description: '',
            })),
        };
    });
});

const allPermissionKeys = computed(() => {
    return Object.values(props.permissions).flatMap((p) => p).map((p) => p.name);
});

const isDuplicating = computed(() => !!props.initialRole);

const form = useForm({
    name: props.initialRole?.name ?? '',
    description: props.initialRole?.description ?? '',
    permissions: props.initialRole?.permissions ? [...props.initialRole.permissions] : [],
});

const permissionSearchQuery = ref('');

function permissionBadgeClass(permissionId: string): string {
    if (permissionId.includes('.delete') || permissionId.includes('mfa') || permissionId.includes('tokens') || permissionId.includes('export')) {
        return 'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400';
    }
    if (permissionId.includes('.edit') || permissionId.includes('.update') || permissionId.includes('.audit')) {
        return 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400';
    }
    if (permissionId.includes('.create') || permissionId.includes('.invite')) {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
    }
    return 'border-blue-500/30 bg-blue-500/10 text-blue-600 dark:text-blue-400';
}

const filteredPermissionGroups = computed(() => {
    const q = permissionSearchQuery.value.trim().toLowerCase();
    if (!q) {
        return PERMISSION_GROUPS.value;
    }
    return PERMISSION_GROUPS.value.map((group) => {
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
    return form.permissions.includes(id);
}

function togglePermission(id: string, checked: boolean | 'indeterminate'): void {
    if (checked === true) {
        if (!form.permissions.includes(id)) {
            form.permissions.push(id);
        }
    } else {
        form.permissions = form.permissions.filter((p) => p !== id);
    }
}

function selectAllPermissions(): void {
    form.permissions = [...allPermissionKeys.value];
}

function clearAllPermissions(): void {
    form.permissions = [];
}

function toggleGroupPermissions(group: any): void {
    const groupIds = group.permissions.map((p: any) => p.id);
    const allSelected = groupIds.every((id: string) => form.permissions.includes(id));

    if (allSelected) {
        form.permissions = form.permissions.filter((id) => !groupIds.includes(id));
    } else {
        const toAdd = groupIds.filter((id: string) => !form.permissions.includes(id));
        form.permissions.push(...toAdd);
    }
}

function isGroupAllSelected(group: any): boolean {
    return group.permissions.every((p: any) => form.permissions.includes(p.id));
}

function groupSelectedCount(group: any): number {
    return group.permissions.filter((p: any) => form.permissions.includes(p.id)).length;
}

function saveRole(): void {
    form.post(rolesRoute.store.url());
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Roles', href: authorization.roles.index.url() }, { title: 'Create Role', href: '' }] },
});
</script>

<template>
    <Head :title="isDuplicating ? `Duplicate ${props.initialRole?.source_name ?? 'Role'}` : 'Create Role'" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <Button variant="outline" size="icon" as-child class="shrink-0">
                    <Link :href="rolesRoute.index.url()">
                        <ArrowLeft class="size-4" />
                        <span class="sr-only">Back</span>
                    </Link>
                </Button>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">
                        {{ isDuplicating ? `Duplicate Role: ${props.initialRole?.source_name}` : 'Create New Role' }}
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        {{ isDuplicating ? 'Review and adjust operational boundaries for this copied role.' : 'Define a new role and configure its operational access boundaries.' }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3 self-end sm:self-auto">
                <Button variant="outline" as-child>
                    <Link :href="rolesRoute.index.url()">Cancel</Link>
                </Button>
                <Button :disabled="!form.name.trim() || form.processing" @click="saveRole">
                    <Check class="size-4 mr-1.5" />
                    {{ isDuplicating ? 'Duplicate role' : 'Create role' }}
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
                            v-model="form.name"
                            placeholder="e.g. Support Specialist"
                            :class="form.errors.name ? 'border-destructive focus-visible:ring-destructive' : ''"
                        />
                        <p v-if="form.errors.name" class="text-destructive text-sm">{{ form.errors.name }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="role-description" class="font-medium">Description</Label>
                        <Input
                            id="role-description"
                            v-model="form.description"
                            placeholder="e.g. Manage user accounts and view system settings"
                        />
                        <p v-if="form.errors.description" class="text-destructive text-sm">{{ form.errors.description }}</p>
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
                            {{ form.permissions.length }} of {{ allPermissionKeys.length }} granted
                        </Badge>
                        <p v-if="form.errors.permissions" class="text-destructive text-sm ml-4">{{ form.errors.permissions }}</p>
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