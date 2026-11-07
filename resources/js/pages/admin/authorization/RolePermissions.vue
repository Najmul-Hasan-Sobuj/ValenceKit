<script setup lang="ts">
import { computed, ref } from 'vue';
import {
    CheckCheck,
    RotateCcw,
    Search,
    X,
    KeyRound,
    Settings as SettingsIcon,
    Users,
    FolderOpen,
    Wallet,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    permissions: Record<
        string,
        { id: number; name: string; guard_name: string; group_name: string }[]
    >;
    disabled?: boolean;
    error?: string;
}>();
const selectedPermissions = defineModel<string[]>({ required: true });

const PERMISSION_GROUPS_META: Record<
    string,
    { description: string; icon: any }
> = {
    'User Management': {
        description: 'Manage administrator accounts, invitations, and profiles',
        icon: Users,
    },
    'Role Management': {
        description: 'Manage access control roles, privileges, and boundaries',
        icon: KeyRound,
    },
    'System Settings': {
        description: 'Configure profile, security, and appearance settings',
        icon: SettingsIcon,
    },
    'Category Management': {
        description: 'Manage dynamic categories for all modules',
        icon: FolderOpen,
    },
    'Finance Tracker': {
        description: 'Manage financial transactions and budgets',
        icon: Wallet,
    },
};

const PERMISSION_GROUPS = computed(() => {
    return Object.entries(props.permissions).map(([groupName, perms]) => {
        const meta = PERMISSION_GROUPS_META[groupName] || {
            description: '',
            icon: SettingsIcon,
        };
        return {
            name: groupName,
            description: meta.description,
            icon: meta.icon,
            permissions: perms,
        };
    });
});

const allPermissionKeys = computed(() => {
    return Object.values(props.permissions)
        .flatMap((p) => p)
        .map((p) => p.name);
});

const permissionSearchQuery = ref('');

function permissionBadgeClass(permissionId: string): string {
    if (
        permissionId.includes('.delete') ||
        permissionId.includes('mfa') ||
        permissionId.includes('tokens') ||
        permissionId.includes('export')
    ) {
        return 'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400';
    }
    if (
        permissionId.includes('.edit') ||
        permissionId.includes('.update') ||
        permissionId.includes('.audit')
    ) {
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
    return PERMISSION_GROUPS.value
        .map((group) => {
            const matching = group.permissions.filter(
                (p) =>
                    p.name.toLowerCase().includes(q) ||
                    group.name.toLowerCase().includes(q),
            );
            return {
                ...group,
                permissions: matching,
            };
        })
        .filter((group) => group.permissions.length > 0);
});

function isPermissionSelected(id: string): boolean {
    return selectedPermissions.value.includes(id);
}

function togglePermission(
    id: string,
    checked: boolean | 'indeterminate',
): void {
    if (props.disabled) return;

    if (checked === true) {
        if (!selectedPermissions.value.includes(id)) {
            selectedPermissions.value.push(id);
        }
    } else {
        selectedPermissions.value = selectedPermissions.value.filter(
            (p) => p !== id,
        );
    }
}

function selectAllPermissions(): void {
    if (props.disabled) return;
    selectedPermissions.value = [...allPermissionKeys.value];
}

function clearAllPermissions(): void {
    if (props.disabled) return;
    selectedPermissions.value = [];
}

function toggleGroupPermissions(group: any): void {
    if (props.disabled) return;

    const groupIds = group.permissions.map((p: any) => p.name);
    const allSelected = groupIds.every((id: string) =>
        selectedPermissions.value.includes(id),
    );

    if (allSelected) {
        selectedPermissions.value = selectedPermissions.value.filter(
            (id: string) => !groupIds.includes(id),
        );
    } else {
        const toAdd = groupIds.filter(
            (id: string) => !selectedPermissions.value.includes(id),
        );
        selectedPermissions.value.push(...toAdd);
    }
}

function isGroupAllSelected(group: any): boolean {
    return group.permissions.every((p: any) =>
        selectedPermissions.value.includes(p.name),
    );
}

function groupSelectedCount(group: any): number {
    return group.permissions.filter((p: any) =>
        selectedPermissions.value.includes(p.name),
    ).length;
}
</script>

<template>
    <!-- Permissions Section -->
    <div class="space-y-4 rounded-xl border bg-card p-5 shadow-xs">
        <!-- Controls Bar -->
        <div
            class="flex flex-col gap-4 border-b pb-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex items-center gap-3">
                <h3 class="text-base font-semibold text-foreground">
                    Permissions
                </h3>
                <Badge variant="outline" class="font-normal">
                    {{ selectedPermissions.length }} of
                    {{ allPermissionKeys.length }} granted
                </Badge>
                <p v-if="props.error" class="ml-4 text-sm text-destructive">
                    {{ props.error }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full sm:w-64">
                    <Search
                        class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                    />
                    <Input
                        v-model="permissionSearchQuery"
                        placeholder="Search permissions..."
                        class="pr-8 pl-9"
                        aria-label="Search permissions"
                    />
                    <button
                        v-if="permissionSearchQuery"
                        type="button"
                        class="absolute top-2.5 right-2 text-muted-foreground hover:text-foreground"
                        @click="permissionSearchQuery = ''"
                    >
                        <X class="size-4" />
                        <span class="sr-only">Clear search</span>
                    </button>
                </div>
                <div v-if="!props.disabled" class="flex items-center gap-2">
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
        <div
            v-if="filteredPermissionGroups.length > 0"
            class="grid gap-4 lg:grid-cols-2"
        >
            <div
                v-for="group in filteredPermissionGroups"
                :key="group.name"
                class="flex flex-col gap-3 rounded-xl border bg-card/60 p-4 shadow-xs"
            >
                <!-- Group Header -->
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                        >
                            <component :is="group.icon" class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="text-sm font-semibold text-foreground"
                                    >{{ group.name }}</span
                                >
                                <Badge
                                    variant="secondary"
                                    class="px-2 py-0.5 font-normal"
                                >
                                    {{ groupSelectedCount(group) }}/{{
                                        group.permissions.length
                                    }}
                                </Badge>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ group.description }}
                            </p>
                        </div>
                    </div>
                    <Button
                        v-if="!props.disabled"
                        variant="ghost"
                        type="button"
                        class="self-start text-sm text-muted-foreground hover:text-foreground sm:self-auto"
                        @click="toggleGroupPermissions(group)"
                    >
                        {{
                            isGroupAllSelected(group)
                                ? 'Deselect group'
                                : 'Select group'
                        }}
                    </Button>
                </div>

                <!-- Group Permissions Grid -->
                <div class="grid grid-cols-1 gap-2">
                    <label
                        v-for="permission in group.permissions"
                        :key="permission.name"
                        :class="[
                            'flex cursor-pointer items-start gap-2.5 rounded-lg border p-3 transition-all select-none',
                            isPermissionSelected(permission.name)
                                ? 'border-primary/40 bg-primary/5 text-foreground shadow-xs'
                                : 'border-border/60 bg-background/50 text-muted-foreground hover:border-border hover:bg-muted/40 hover:text-foreground',
                            props.disabled
                                ? 'cursor-not-allowed opacity-80'
                                : '',
                        ]"
                    >
                        <Checkbox
                            :model-value="isPermissionSelected(permission.name)"
                            :disabled="props.disabled"
                            class="mt-1"
                            @update:model-value="
                                togglePermission(permission.name, $event)
                            "
                        />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="text-sm leading-none font-medium text-foreground"
                                    >{{ permission.name }}</span
                                >
                                <Badge
                                    class="border px-2 py-0.5 text-xs font-normal"
                                    :class="
                                        permissionBadgeClass(permission.name)
                                    "
                                    variant="outline"
                                >
                                    {{
                                        permission.name.split('.')[1] ??
                                        permission.name.split('.')[0]
                                    }}
                                </Badge>
                            </div>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Empty state if search returns nothing -->
        <div
            v-else
            class="flex flex-col items-center justify-center rounded-xl border border-dashed bg-muted/10 px-4 py-12 text-center"
        >
            <div
                class="mb-4 flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground"
            >
                <Search class="size-5" />
            </div>
            <p class="text-base font-medium text-foreground">
                No matching permissions found
            </p>
            <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                No permissions match "{{ permissionSearchQuery }}". Try
                searching for another keyword like "users", "content", or
                "settings".
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
</template>
