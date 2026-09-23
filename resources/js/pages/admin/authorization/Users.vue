<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { DateRange } from '@internationalized/date';
import { computed, ref, watch } from 'vue';
import { MoreHorizontal, Plus, Search, ShieldCheck } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
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
};

const adminUsers = ref<AdminUser[]>([
    { id: 1, name: 'Alex Morgan', email: 'alex@valencekit.test', role: 'Super Admin', status: 'Active', lastLoginAt: 'Just now', lastLoginIp: '192.168.1.10', lastLoginDate: '2026-09-24' },
    { id: 2, name: 'Maya Chen', email: 'maya@valencekit.test', role: 'Content Manager', status: 'Active', lastLoginAt: '12 min ago', lastLoginIp: '192.168.1.24', lastLoginDate: '2026-09-23' },
    { id: 3, name: 'Jon Bell', email: 'jon@valencekit.test', role: 'Support', status: 'Inactive', lastLoginAt: 'Never', lastLoginIp: 'Not available', lastLoginDate: null },
    { id: 4, name: 'Priya Shah', email: 'priya@valencekit.test', role: 'Content Manager', status: 'Active', lastLoginAt: 'Yesterday', lastLoginIp: '192.168.1.31', lastLoginDate: '2026-09-22' },
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

watch([search, selectedRole, selectedStatus, loginDateRange], () => {
    currentPage.value = 1;
});

watch(totalPages, (value) => {
    currentPage.value = Math.min(currentPage.value, value);
});

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

defineOptions({
    layout: { breadcrumbs: [{ title: 'Users', href: usersRoute() }] },
});
</script>

<template>
    <Head title="Admin Users" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
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

        <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-5">User</TableHead>
                        <TableHead>Role</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Last login</TableHead>
                        <TableHead class="w-12 pr-5"><span class="sr-only">Actions</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="user in paginatedUsers" :key="user.id" class="cursor-pointer" @click="openUserDrawer(user)">
                        <TableCell class="pl-5">
                            <div class="flex items-center gap-3">
                                <div class="bg-primary/10 text-primary flex size-9 items-center justify-center rounded-full text-xs font-semibold">
                                    {{ user.name.split(' ').map((part) => part[0]).join('') }}
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
                        <TableCell class="text-muted-foreground">{{ user.lastLoginAt }}</TableCell>
                        <TableCell class="pr-5 text-right">
                            <Button variant="ghost" size="icon-sm" aria-label="Open user actions" @click.stop="openUserDrawer(user)">
                                <MoreHorizontal class="size-4" />
                            </Button>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="paginatedUsers.length === 0">
                        <TableCell colspan="5" class="text-muted-foreground h-24 text-center">No users found.</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div class="flex flex-col justify-between gap-3 text-sm sm:flex-row sm:items-center">
            <p class="text-muted-foreground">Showing {{ paginatedUsers.length }} of {{ filteredUsers.length }} users</p>
            <div class="flex items-center gap-1">
                <Button variant="outline" size="sm" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">Previous</Button>
                <Button v-for="page in pageNumbers" :key="page" :variant="page === currentPage ? 'default' : 'outline'" size="sm" class="min-w-9" @click="goToPage(page)">{{ page }}</Button>
                <Button variant="outline" size="sm" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">Next</Button>
            </div>
        </div>
    </div>

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
                </div>

                <DrawerFooter class="px-4">
                    <Button @click="drawerOpen = false">{{ selectedUser ? 'Save changes' : 'Send invite' }}</Button>
                    <Button variant="outline" @click="drawerOpen = false">Cancel</Button>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>