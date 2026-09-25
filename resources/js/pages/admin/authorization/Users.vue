<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { DateRange } from '@internationalized/date';
import { computed, ref, watch } from 'vue';
import { MoreHorizontal, Plus, Search, Shield, ShieldCheck, ShieldOff, Trash2, UserCheck, UserX, Users } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
import { DateRangePicker } from '@/components/ui/date-range-picker';
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
import { users as usersRoute } from '@/routes/admin/authorization';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    status: 'Active' | 'Inactive';
    lastLoginAt: string;
    lastLoginIp: string;
    lastLoginDate: string | null;
    hasMfa: boolean;
};

const adminUsers = ref<AdminUser[]>([
    { id: 1, name: 'Alex Morgan', email: 'alex@valencekit.test', role: 'Super Admin', status: 'Active', lastLoginAt: 'Just now', lastLoginIp: '192.168.1.10', lastLoginDate: '2026-09-24', hasMfa: true },
    { id: 2, name: 'Maya Chen', email: 'maya@valencekit.test', role: 'Content Manager', status: 'Active', lastLoginAt: '12 min ago', lastLoginIp: '192.168.1.24', lastLoginDate: '2026-09-23', hasMfa: true },
    { id: 3, name: 'Jon Bell', email: 'jon@valencekit.test', role: 'Support', status: 'Inactive', lastLoginAt: 'Never', lastLoginIp: 'Not available', lastLoginDate: null, hasMfa: false },
    { id: 4, name: 'Priya Shah', email: 'priya@valencekit.test', role: 'Content Manager', status: 'Active', lastLoginAt: 'Yesterday', lastLoginIp: '192.168.1.31', lastLoginDate: '2026-09-22', hasMfa: false },
]);

const roles = ['All roles', 'Super Admin', 'Content Manager', 'Support'];
const statuses = ['All statuses', 'Active', 'Inactive'];
const search = ref('');
const selectedRole = ref('All roles');
const selectedStatus = ref('All statuses');
const loginDateRange = ref<DateRange | undefined>(undefined);
const selectedUser = ref<AdminUser | null>(null);
const formStatus = ref<'Active' | 'Inactive'>('Active');
const drawerOpen = ref(false);
const currentPage = ref(1);
const pageSize = 3;

// Bulk selection
const selectedIds = ref<Set<number>>(new Set());

// KPI computed
const activeCount = computed(() => adminUsers.value.filter((u) => u.status === 'Active').length);
const mfaEnabledCount = computed(() => adminUsers.value.filter((u) => u.hasMfa).length);
const mfaMissingCount = computed(() => adminUsers.value.filter((u) => !u.hasMfa).length);

const filteredUsers = computed(() => adminUsers.value.filter((user) => {
    const matchesSearch = `${user.name} ${user.email}`.toLowerCase().includes(search.value.toLowerCase());
    const matchesRole = !selectedRole.value || selectedRole.value === 'All roles' || user.role === selectedRole.value;
    const matchesStatus = !selectedStatus.value || selectedStatus.value === 'All statuses' || user.status === selectedStatus.value;
    const loginTimestamp = user.lastLoginDate ? new Date(`${user.lastLoginDate}T00:00:00`).getTime() : null;
    const rangeStart = loginDateRange.value?.start ? new Date(`${loginDateRange.value.start.toString()}T00:00:00`).getTime() : null;
    const rangeEnd = loginDateRange.value?.end ? new Date(`${loginDateRange.value.end.toString()}T23:59:59`).getTime() : null;
    const matchesDateRange = !rangeStart || (loginTimestamp !== null && loginTimestamp >= rangeStart && (!rangeEnd || loginTimestamp <= rangeEnd));

    return matchesSearch && matchesRole && matchesStatus && matchesDateRange;
}));

const totalPages = computed(() => Math.max(1, Math.ceil(filteredUsers.value.length / pageSize)));
const paginatedUsers = computed(() => filteredUsers.value.slice((currentPage.value - 1) * pageSize, currentPage.value * pageSize));
const pageNumbers = computed(() => Array.from({ length: totalPages.value }, (_, index) => index + 1));

// Bulk helpers
const allPageSelected = computed(() => paginatedUsers.value.length > 0 && paginatedUsers.value.every((u) => selectedIds.value.has(u.id)));
const somePageSelected = computed(() => paginatedUsers.value.some((u) => selectedIds.value.has(u.id)) && !allPageSelected.value);
const selectedCount = computed(() => selectedIds.value.size);

watch([search, selectedRole, selectedStatus, loginDateRange], () => {
    currentPage.value = 1;
    selectedIds.value = new Set();
});

watch(totalPages, (value) => {
    currentPage.value = Math.min(currentPage.value, value);
});

function getInitials(name: string): string {
    return name.split(' ').map((p) => p[0]).join('').toUpperCase();
}

function openUserDrawer(user: AdminUser): void {
    selectedUser.value = user;
    formStatus.value = user.status;
    drawerOpen.value = true;
}

function openInviteDrawer(): void {
    selectedUser.value = null;
    formStatus.value = 'Active';
    drawerOpen.value = true;
}

function goToPage(page: number): void {
    currentPage.value = Math.min(Math.max(page, 1), totalPages.value);
}

function toggleSelectAll(checked: boolean | 'indeterminate'): void {
    if (checked === true) {
        paginatedUsers.value.forEach((u) => selectedIds.value.add(u.id));
    } else {
        paginatedUsers.value.forEach((u) => selectedIds.value.delete(u.id));
    }
    // trigger reactivity
    selectedIds.value = new Set(selectedIds.value);
}

function toggleSelectUser(id: number, checked: boolean | 'indeterminate'): void {
    const next = new Set(selectedIds.value);
    if (checked === true) { next.add(id); } else { next.delete(id); }
    selectedIds.value = next;
}

function bulkDeactivate(): void {
    adminUsers.value = adminUsers.value.map((u) =>
        selectedIds.value.has(u.id) ? { ...u, status: 'Inactive' } : u,
    );
    selectedIds.value = new Set();
}

function bulkRemove(): void {
    adminUsers.value = adminUsers.value.filter((u) => !selectedIds.value.has(u.id));
    selectedIds.value = new Set();
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Users', href: usersRoute() }] },
});
</script>

<template>
    <Head title="Admin Users" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <!-- Page Header -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <Heading
                variant="small"
                title="Users"
                description="Manage administrator access across your workspace"
            />
            <Button class="w-fit" @click="openInviteDrawer">
                <Plus class="size-4" />
                Add user
            </Button>
        </div>

        <!-- KPI Metric Cards -->
        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Total admins</div>
                    <Users class="text-muted-foreground size-4" />
                </div>
                <div class="mt-2 text-2xl font-semibold">{{ adminUsers.length }}</div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Active</div>
                    <UserCheck class="size-4 text-emerald-500" />
                </div>
                <div class="mt-2 text-2xl font-semibold text-emerald-500">{{ activeCount }}</div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">2FA enabled</div>
                    <ShieldCheck class="size-4 text-blue-500" />
                </div>
                <div class="mt-2 text-2xl font-semibold text-blue-500">{{ mfaEnabledCount }}</div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">2FA missing</div>
                    <ShieldOff class="size-4 text-amber-500" />
                </div>
                <div class="mt-2 text-2xl font-semibold text-amber-500">{{ mfaMissingCount }}</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="grid gap-3 rounded-xl border bg-card p-3 shadow-sm md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_14rem_14rem_minmax(17rem,auto)] xl:items-center">
            <div class="relative min-w-0 md:col-span-2 xl:col-span-1">
                <Search class="text-muted-foreground absolute top-2.5 left-3 size-4" />
                <Input v-model="search" class="pl-9" placeholder="Search users" aria-label="Search users" />
            </div>
            <Combobox v-model="selectedRole">
                <ComboboxAnchor class="w-full">
                    <ComboboxInput aria-label="Filter by role" :display-value="(value) => String(value || 'All roles')" placeholder="Filter by role" />
                </ComboboxAnchor>
                <ComboboxList>
                    <ComboboxEmpty>No matching roles.</ComboboxEmpty>
                    <ComboboxItem v-for="role in roles" :key="role" :value="role">
                        {{ role }}
                    </ComboboxItem>
                </ComboboxList>
            </Combobox>
            <Combobox v-model="selectedStatus">
                <ComboboxAnchor class="w-full">
                    <ComboboxInput aria-label="Filter by status" :display-value="(value) => String(value || 'All statuses')" placeholder="Filter by status" />
                </ComboboxAnchor>
                <ComboboxList>
                    <ComboboxEmpty>No matching statuses.</ComboboxEmpty>
                    <ComboboxItem v-for="status in statuses" :key="status" :value="status">
                        {{ status }}
                    </ComboboxItem>
                </ComboboxList>
            </Combobox>
            <div class="flex min-w-0 items-center gap-2 md:col-span-2 xl:col-span-1">
                <DateRangePicker v-model="loginDateRange" />
                <Button v-if="loginDateRange?.start" variant="ghost" size="sm" class="shrink-0" @click="loginDateRange = undefined">Clear dates</Button>
            </div>
        </div>

        <!-- Bulk Action Bar -->
        <div v-if="selectedCount > 0" class="flex items-center gap-3 rounded-xl border border-primary/30 bg-primary/5 px-4 py-2.5">
            <span class="text-sm font-medium">{{ selectedCount }} selected</span>
            <div class="ml-auto flex items-center gap-2">
                <Button variant="outline" size="sm" class="gap-1.5 text-xs" @click="bulkDeactivate">
                    <UserX class="size-3.5" />
                    Deactivate
                </Button>
                <Button variant="outline" size="sm" class="gap-1.5 text-xs text-destructive hover:text-destructive hover:bg-destructive/10 border-destructive/30" @click="bulkRemove">
                    <Trash2 class="size-3.5" />
                    Remove
                </Button>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-10 pl-4">
                            <Checkbox
                                :model-value="allPageSelected ? true : somePageSelected ? 'indeterminate' : false"
                                aria-label="Select all on page"
                                @update:model-value="toggleSelectAll"
                            />
                        </TableHead>
                        <TableHead>User</TableHead>
                        <TableHead>Role</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>2FA</TableHead>
                        <TableHead>Last login</TableHead>
                        <TableHead class="w-12 pr-5"><span class="sr-only">Actions</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="user in paginatedUsers" :key="user.id" class="cursor-pointer" :class="selectedIds.has(user.id) ? 'bg-primary/5' : ''" @click="openUserDrawer(user)">
                        <TableCell class="pl-4" @click.stop>
                            <Checkbox
                                :model-value="selectedIds.has(user.id)"
                                :aria-label="`Select ${user.name}`"
                                @update:model-value="(v) => toggleSelectUser(user.id, v)"
                            />
                        </TableCell>
                        <TableCell>
                            <div class="flex items-center gap-3">
                                <div class="relative shrink-0">
                                    <Avatar class="size-9">
                                        <AvatarFallback class="bg-primary/10 text-primary text-xs font-semibold">
                                            {{ getInitials(user.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span
                                        :class="user.status === 'Active' ? 'bg-emerald-500' : 'bg-muted-foreground'"
                                        class="absolute -bottom-0.5 -right-0.5 size-2.5 rounded-full ring-2 ring-background"
                                    />
                                </div>
                                <div>
                                    <div class="font-medium">{{ user.name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ user.email }}</div>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell><Badge variant="secondary">{{ user.role }}</Badge></TableCell>
                        <TableCell>
                            <span class="inline-flex items-center gap-2 text-sm">
                                <span :class="user.status === 'Active' ? 'bg-emerald-500' : 'bg-muted-foreground'" class="size-2 rounded-full" />
                                {{ user.status }}
                            </span>
                        </TableCell>
                        <TableCell>
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <span class="inline-flex">
                                            <ShieldCheck v-if="user.hasMfa" class="size-4 text-blue-500" />
                                            <Shield v-else class="size-4 text-amber-400" />
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {{ user.hasMfa ? '2FA enabled' : '2FA not configured' }}
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </TableCell>
                        <TableCell class="text-muted-foreground">{{ user.lastLoginAt }}</TableCell>
                        <TableCell class="pr-5 text-right">
                            <Button variant="ghost" size="icon-sm" aria-label="Open user actions" @click.stop="openUserDrawer(user)">
                                <MoreHorizontal class="size-4" />
                            </Button>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="paginatedUsers.length === 0">
                        <TableCell colspan="7" class="text-muted-foreground h-24 text-center">No users found.</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination -->
        <div class="flex flex-col justify-between gap-3 text-sm sm:flex-row sm:items-center">
            <p class="text-muted-foreground">Showing {{ paginatedUsers.length }} of {{ filteredUsers.length }} users</p>
            <div class="flex items-center gap-1">
                <Button variant="outline" size="sm" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">Previous</Button>
                <Button v-for="page in pageNumbers" :key="page" :variant="page === currentPage ? 'default' : 'outline'" size="sm" class="min-w-9" @click="goToPage(page)">{{ page }}</Button>
                <Button variant="outline" size="sm" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">Next</Button>
            </div>
        </div>
    </div>

    <!-- User Drawer -->
    <Drawer v-model:open="drawerOpen">
        <DrawerContent class="md:inset-x-auto md:right-2 md:left-64">
            <div class="w-full">
                <DrawerHeader>
                    <DrawerTitle>{{ selectedUser ? 'Edit user access' : 'Invite an admin' }}</DrawerTitle>
                    <DrawerDescription>
                        {{ selectedUser ? `Update ${selectedUser.name}'s role and access level.` : 'Create an administrator account and assign a role.' }}
                    </DrawerDescription>
                </DrawerHeader>

                <div class="grid gap-5 px-4 pb-2 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="admin-name">Name</Label>
                        <Input id="admin-name" :default-value="selectedUser?.name" placeholder="Full name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="admin-email">Email</Label>
                        <Input id="admin-email" type="email" :default-value="selectedUser?.email" placeholder="name@company.com" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label class="flex items-center gap-2"><ShieldCheck class="text-muted-foreground size-4" />Role</Label>
                        <div class="rounded-md border bg-muted/30 px-3 py-2 text-sm">{{ selectedUser?.role ?? 'Choose a role from the Roles section' }}</div>
                    </div>
                    <div class="grid gap-2">
                        <Label>Status</Label>
                        <Combobox v-model="formStatus">
                            <ComboboxAnchor>
                                <ComboboxInput aria-label="Select status" :display-value="(value) => String(value || 'Active')" placeholder="Select status" />
                            </ComboboxAnchor>
                            <ComboboxList>
                                <ComboboxItem v-for="status in statuses.slice(1)" :key="status" :value="status">
                                    {{ status }}
                                </ComboboxItem>
                            </ComboboxList>
                        </Combobox>
                    </div>
                    <div class="grid gap-2">
                        <Label>Last login</Label>
                        <div class="rounded-md border bg-muted/30 px-3 py-2 text-sm">{{ selectedUser?.lastLoginAt ?? 'Not available' }}</div>
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label>Last login IP</Label>
                        <div class="rounded-md border bg-muted/30 px-3 py-2 text-sm">{{ selectedUser?.lastLoginIp ?? 'Not available' }}</div>
                    </div>
                    <!-- 2FA status in drawer -->
                    <div v-if="selectedUser" class="grid gap-2 sm:col-span-2">
                        <Label class="flex items-center gap-2">
                            <Shield class="text-muted-foreground size-4" />
                            Two-Factor Authentication
                        </Label>
                        <div class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm" :class="selectedUser.hasMfa ? 'bg-blue-500/5 border-blue-500/20' : 'bg-amber-500/5 border-amber-500/20'">
                            <ShieldCheck v-if="selectedUser.hasMfa" class="size-4 text-blue-500 shrink-0" />
                            <ShieldOff v-else class="size-4 text-amber-500 shrink-0" />
                            <span>{{ selectedUser.hasMfa ? 'Passkey / 2FA is active' : 'No 2FA configured — security risk' }}</span>
                        </div>
                    </div>
                </div>

                <DrawerFooter class="px-4">
                    <Button @click="drawerOpen = false">{{ selectedUser ? 'Save changes' : 'Send invite' }}</Button>
                    <Button variant="outline" @click="drawerOpen = false">Cancel</Button>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>