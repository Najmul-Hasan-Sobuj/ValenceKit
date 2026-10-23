<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { watchDebounced } from '@vueuse/core';
import { decodePaginationLabel } from '@/lib/decodePaginationLabel';
import { getInitials } from '@/lib/utils';
import {
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Shield,
    ShieldCheck,
    ShieldOff,
    Trash2,
    UserCheck,
    Users as UsersIcon,
} from '@lucide/vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import Heading from '@/components/Heading.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Blobatar } from '@blobatar/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { index as usersRoute } from '@/routes/admin/users';
import UserDrawer from './UserDrawer.vue';
import { destroy } from '@/actions/App/Http/Controllers/Admin/Authorization/UserController';
import RelativeTime from '@/components/RelativeTime.vue';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { parseDate } from '@internationalized/date';
import type { DateRange } from '@/types';

const props = defineProps<{
    admins: any;
    roles: Array<{ id: number; name: string }>;
    metrics: {
        total: number;
        active: number;
        mfaEnabled: number;
        mfaMissing: number;
    };
    filters: { search?: string; role?: string; from?: string; to?: string };
}>();

import { usePermissions } from '@/composables/usePermissions';

const { can, canEditUser, canDeleteUser } = usePermissions();
const canInviteUsers = computed(() => can('users.invite'));

const search = ref(
    Array.isArray(props.filters) ? '' : (props.filters?.search ?? ''),
);
const selectedRole = ref(
    Array.isArray(props.filters)
        ? 'All roles'
        : (props.filters?.role ?? 'All roles'),
);

function initDateRange(): DateRange | undefined {
    if (!props.filters || Array.isArray(props.filters) || !props.filters.from)
        return undefined;
    try {
        const start = parseDate(props.filters.from);
        return {
            start,
            end: props.filters.to ? parseDate(props.filters.to) : start,
        };
    } catch {
        return undefined;
    }
}

const dateRange = ref<DateRange | undefined>(initDateRange());

watch(
    () => props.filters,
    (newFilters) => {
        if (!newFilters || Array.isArray(newFilters)) {
            search.value = '';
            selectedRole.value = 'All roles';
            if (dateRange.value) {
                dateRange.value = undefined;
            }
            return;
        }
        search.value = newFilters.search ?? '';
        selectedRole.value = newFilters.role ?? 'All roles';
        if (!newFilters.from) {
            if (dateRange.value) {
                dateRange.value = undefined;
            }
        } else {
            try {
                const s = parseDate(newFilters.from);
                const e = newFilters.to ? parseDate(newFilters.to) : s;
                dateRange.value = { start: s, end: e };
            } catch {
                dateRange.value = undefined;
            }
        }
    },
    { deep: true },
);

const drawerOpen = ref(false);
const selectedUser = ref<any | null>(null);

const deleteDialogOpen = ref(false);
const userToDelete = ref<any | null>(null);

// Debounce filtering
watchDebounced(
    [search, selectedRole, dateRange],
    () => {
        // Wait until full range is selected (or cleared)
        if (dateRange.value?.start && !dateRange.value?.end) {
            return;
        }

        const fromStr = dateRange.value?.start
            ? dateRange.value.start.toString()
            : '';
        const toStr = dateRange.value?.end
            ? dateRange.value.end.toString()
            : '';

        const currentSearch = Array.isArray(props.filters)
            ? ''
            : (props.filters?.search ?? '');
        const currentRole = Array.isArray(props.filters)
            ? ''
            : (props.filters?.role ?? '');
        const currentFrom = Array.isArray(props.filters)
            ? ''
            : (props.filters?.from ?? '');
        const currentTo = Array.isArray(props.filters)
            ? ''
            : (props.filters?.to ?? '');

        // Avoid duplicate requests if filters didn't change
        if (
            search.value === currentSearch &&
            (selectedRole.value === 'All roles' ? '' : selectedRole.value) ===
                currentRole &&
            fromStr === currentFrom &&
            toStr === currentTo
        ) {
            return;
        }

        router.get(
            usersRoute.url(),
            {
                search: search.value,
                role:
                    selectedRole.value === 'All roles'
                        ? ''
                        : selectedRole.value,
                from: fromStr,
                to: toStr,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    },
    { debounce: 300 },
);


function openUserDrawer(user: any): void {
    selectedUser.value = user;
    drawerOpen.value = true;
}

function openInviteDrawer(): void {
    selectedUser.value = null;
    drawerOpen.value = true;
}

const isDeleting = ref(false);

function promptDeleteUser(user: any): void {
    userToDelete.value = user;
    drawerOpen.value = false;
    deleteDialogOpen.value = true;
}

function confirmDeleteUser(event?: Event): void {
    event?.preventDefault();
    if (userToDelete.value && !isDeleting.value) {
        isDeleting.value = true;
        router.delete(destroy.url(userToDelete.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                deleteDialogOpen.value = false;
                userToDelete.value = null;
            },
            onFinish: () => {
                isDeleting.value = false;
            },
        });
    }
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Users', href: usersRoute.url() }] },
});
</script>

<template>
    <Head title="Admin Users" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <!-- Page Header -->
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
        >
            <Heading
                variant="small"
                title="Users"
                description="Manage administrator access across your workspace"
            />
            <Button
                v-if="canInviteUsers"
                class="w-fit"
                @click="openInviteDrawer"
            >
                <Plus class="size-4" />
                Add user
            </Button>
        </div>

        <!-- KPI Metric Cards -->
        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <div
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Total admins
                    </div>
                    <UsersIcon class="size-4 text-muted-foreground" />
                </div>
                <div class="mt-2 text-2xl font-semibold">
                    {{ metrics.total }}
                </div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <div
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Active
                    </div>
                    <UserCheck class="size-4 text-emerald-500" />
                </div>
                <div class="mt-2 text-2xl font-semibold text-emerald-500">
                    {{ metrics.active }}
                </div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <div
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        2FA enabled
                    </div>
                    <ShieldCheck class="size-4 text-blue-500" />
                </div>
                <div class="mt-2 text-2xl font-semibold text-blue-500">
                    {{ metrics.mfaEnabled }}
                </div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <div
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        2FA missing
                    </div>
                    <ShieldOff class="size-4 text-amber-500" />
                </div>
                <div class="mt-2 text-2xl font-semibold text-amber-500">
                    {{ metrics.mfaMissing }}
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div
            class="flex flex-col gap-3 rounded-xl border bg-card p-3 shadow-xs lg:flex-row lg:items-center"
        >
            <div class="relative min-w-0 flex-1">
                <Search
                    class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-9"
                    placeholder="Search users"
                    aria-label="Search users"
                />
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <Combobox v-model="selectedRole">
                    <ComboboxAnchor class="w-full sm:w-48">
                        <ComboboxInput
                            aria-label="Filter by role"
                            :display-value="
                                (value) => String(value || 'All roles')
                            "
                            placeholder="Filter by role"
                        />
                    </ComboboxAnchor>
                    <ComboboxList>
                        <ComboboxEmpty>No matching roles.</ComboboxEmpty>
                        <ComboboxItem value="All roles">All roles</ComboboxItem>
                        <ComboboxItem
                            v-for="role in roles"
                            :key="role.id"
                            :value="role.name"
                        >
                            {{ role.name }}
                        </ComboboxItem>
                    </ComboboxList>
                </Combobox>

                <DateRangePicker
                    v-model="dateRange"
                    placeholder="Last login date"
                    class="w-full sm:w-72"
                />
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">User</TableHead>
                        <TableHead>Role</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>2FA</TableHead>
                        <TableHead>Last login</TableHead>
                        <TableHead class="w-12 pr-5"
                            ><span class="sr-only">Actions</span></TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="user in admins.data"
                        :key="user.id"
                        :class="{ 'cursor-pointer': canEditUser(user) }"
                        @click="canEditUser(user) ? openUserDrawer(user) : null"
                    >
                        <TableCell class="pl-4">
                            <div class="flex items-center gap-3">
                                <div class="relative shrink-0">
                                    <Avatar
                                        class="size-9 overflow-hidden rounded-lg bg-muted/40 ring-1 ring-border/50"
                                    >
                                        <AvatarImage
                                            v-if="user.avatar"
                                            :src="user.avatar"
                                            :alt="user.name"
                                        />
                                        <Blobatar
                                            v-else
                                            :name="user.email || user.name"
                                            :size="36"
                                            animate="hover"
                                            class="size-full object-cover"
                                        />
                                    </Avatar>
                                    <span
                                        :class="
                                            user.is_active
                                                ? 'bg-emerald-500'
                                                : 'bg-muted-foreground'
                                        "
                                        class="absolute -right-0.5 -bottom-0.5 size-2.5 rounded-full ring-2 ring-background"
                                    />
                                </div>
                                <div>
                                    <div class="font-medium">
                                        {{ user.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ user.email }}
                                    </div>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <Badge
                                variant="secondary"
                                v-for="r in user.roles"
                                :key="r.id"
                                class="mr-1"
                                >{{ r.name }}</Badge
                            >
                            <span
                                v-if="!user.roles.length"
                                class="text-sm text-muted-foreground"
                                >No role</span
                            >
                        </TableCell>
                        <TableCell>
                            <span
                                class="inline-flex items-center gap-2 text-sm"
                            >
                                <span
                                    :class="
                                        user.is_active
                                            ? 'bg-emerald-500'
                                            : 'bg-muted-foreground'
                                    "
                                    class="size-2 rounded-full"
                                />
                                {{ user.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </TableCell>
                        <TableCell>
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <span class="inline-flex">
                                            <ShieldCheck
                                                v-if="user.passkeys?.length"
                                                class="size-4 text-blue-500"
                                            />
                                            <Shield
                                                v-else
                                                class="size-4 text-amber-400"
                                            />
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {{
                                            user.passkeys?.length
                                                ? '2FA enabled'
                                                : '2FA not configured'
                                        }}
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </TableCell>
                        <TableCell class="text-sm text-muted-foreground">
                            <RelativeTime
                                :date="user.last_login_at"
                                fallback="Never"
                            />
                        </TableCell>
                        <TableCell class="pr-5 text-right" @click.stop>
                            <DropdownMenu
                                v-if="canEditUser(user) || canDeleteUser(user)"
                            >
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Open user actions"
                                    >
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-36">
                                    <DropdownMenuItem
                                        v-if="canEditUser(user)"
                                        class="cursor-pointer gap-2"
                                        @click="openUserDrawer(user)"
                                    >
                                        <Pencil
                                            class="size-4 text-muted-foreground"
                                        />
                                        <span>Edit</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator
                                        v-if="
                                            canEditUser(user) &&
                                            canDeleteUser(user)
                                        "
                                    />
                                    <DropdownMenuItem
                                        v-if="canDeleteUser(user)"
                                        variant="destructive"
                                        class="cursor-pointer gap-2"
                                        @click="promptDeleteUser(user)"
                                    >
                                        <Trash2 class="size-4" />
                                        <span>Delete</span>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="admins.data.length === 0">
                        <TableCell
                            colspan="6"
                            class="h-24 text-center text-muted-foreground"
                            >No users found.</TableCell
                        >
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination -->
        <div
            class="flex flex-col justify-between gap-3 text-sm sm:flex-row sm:items-center"
        >
            <p class="text-muted-foreground">
                Showing {{ admins.from || 0 }} to {{ admins.to || 0 }} of
                {{ admins.total }} users
            </p>
            <div class="flex items-center gap-1">
                <Button
                    v-for="link in admins.links"
                    :key="link.label"
                    variant="outline"
                    size="sm"
                    :disabled="!link.url"
                    :class="{
                        'border-primary/20 bg-primary/10 text-primary':
                            link.active,
                    }"
                    @click="link.url ? router.get(link.url) : null"
                >
                    <span>{{ decodePaginationLabel(link.label) }}</span>
                </Button>
            </div>
        </div>
    </div>

    <!-- User Drawer -->
    <UserDrawer
        v-model:open="drawerOpen"
        :user="selectedUser"
        :roles="roles"
        @request-delete="promptDeleteUser"
    />

    <!-- Delete User Confirmation Dialog -->
    <AlertDialog v-model:open="deleteDialogOpen">
        <AlertDialogContent class="sm:max-w-md">
            <AlertDialogHeader>
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive"
                    >
                        <Trash2 class="size-5" />
                    </div>
                    <div>
                        <AlertDialogTitle>Delete User</AlertDialogTitle>
                        <AlertDialogDescription class="mt-1">
                            Are you sure you want to delete
                            <span class="font-medium text-foreground">{{
                                userToDelete?.name
                            }}</span
                            >?
                        </AlertDialogDescription>
                    </div>
                </div>
            </AlertDialogHeader>
            <div class="text-sm text-muted-foreground">
                This action cannot be undone. This administrator account will be
                permanently deleted and immediately lose access to the
                administration dashboard and all permissions.
            </div>
            <AlertDialogFooter class="gap-2 sm:gap-0">
                <AlertDialogCancel as-child>
                    <Button variant="outline" :disabled="isDeleting"
                        >Cancel</Button
                    >
                </AlertDialogCancel>
                <AlertDialogAction as-child>
                    <Button
                        variant="destructive"
                        :disabled="isDeleting"
                        @click="confirmDeleteUser"
                    >
                        <Trash2 class="size-4" />
                        <span>{{
                            isDeleting ? 'Deleting...' : 'Delete User'
                        }}</span>
                    </Button>
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
