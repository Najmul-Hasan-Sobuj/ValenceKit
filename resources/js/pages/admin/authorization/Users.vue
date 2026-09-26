<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { watchDebounced } from '@vueuse/core';
import { MoreHorizontal, Pencil, Plus, Search, Shield, ShieldCheck, ShieldOff, Trash2, UserCheck, Users as UsersIcon } from '@lucide/vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import Heading from '@/components/Heading.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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

const props = defineProps<{
    admins: any;
    roles: Array<{ id: number, name: string }>;
    metrics: {
        total: number;
        active: number;
        mfaEnabled: number;
        mfaMissing: number;
    };
    filters: { search?: string; role?: string };
}>();

const search = ref(props.filters.search ?? '');
const selectedRole = ref(props.filters.role ?? 'All roles');

const drawerOpen = ref(false);
const selectedUser = ref<any | null>(null);

const deleteDialogOpen = ref(false);
const userToDelete = ref<any | null>(null);

// Debounce filtering
watchDebounced(
    [search, selectedRole],
    () => {
        router.get(
            usersRoute.url(),
            {
                search: search.value,
                role: selectedRole.value === 'All roles' ? '' : selectedRole.value,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    },
    { debounce: 300 }
);

function getInitials(name: string): string {
    return name.split(' ').map((p) => p[0]).join('').toUpperCase();
}

function openUserDrawer(user: any): void {
    selectedUser.value = user;
    drawerOpen.value = true;
}

function openInviteDrawer(): void {
    selectedUser.value = null;
    drawerOpen.value = true;
}

function promptDeleteUser(user: any): void {
    userToDelete.value = user;
    drawerOpen.value = false;
    deleteDialogOpen.value = true;
}

function confirmDeleteUser(): void {
    if (userToDelete.value) {
        router.delete(destroy.url(userToDelete.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                deleteDialogOpen.value = false;
                userToDelete.value = null;
            }
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
                    <UsersIcon class="text-muted-foreground size-4" />
                </div>
                <div class="mt-2 text-2xl font-semibold">{{ metrics.total }}</div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Active</div>
                    <UserCheck class="size-4 text-emerald-500" />
                </div>
                <div class="mt-2 text-2xl font-semibold text-emerald-500">{{ metrics.active }}</div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">2FA enabled</div>
                    <ShieldCheck class="size-4 text-blue-500" />
                </div>
                <div class="mt-2 text-2xl font-semibold text-blue-500">{{ metrics.mfaEnabled }}</div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">2FA missing</div>
                    <ShieldOff class="size-4 text-amber-500" />
                </div>
                <div class="mt-2 text-2xl font-semibold text-amber-500">{{ metrics.mfaMissing }}</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="grid gap-3 rounded-xl border bg-card p-3 shadow-sm md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_14rem] xl:items-center">
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
                    <ComboboxItem value="All roles">All roles</ComboboxItem>
                    <ComboboxItem v-for="role in roles" :key="role.id" :value="role.name">
                        {{ role.name }}
                    </ComboboxItem>
                </ComboboxList>
            </Combobox>
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
                        <TableHead class="w-12 pr-5"><span class="sr-only">Actions</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="user in admins.data" :key="user.id" class="cursor-pointer" @click="openUserDrawer(user)">
                        <TableCell class="pl-4">
                            <div class="flex items-center gap-3">
                                <div class="relative shrink-0">
                                    <Avatar class="size-9">
                                        <AvatarFallback class="bg-primary/10 text-primary text-xs font-semibold">
                                            {{ getInitials(user.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span
                                        :class="user.is_active ? 'bg-emerald-500' : 'bg-muted-foreground'"
                                        class="absolute -bottom-0.5 -right-0.5 size-2.5 rounded-full ring-2 ring-background"
                                    />
                                </div>
                                <div>
                                    <div class="font-medium">{{ user.name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ user.email }}</div>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <Badge variant="secondary" v-for="r in user.roles" :key="r.id" class="mr-1">{{ r.name }}</Badge>
                            <span v-if="!user.roles.length" class="text-muted-foreground text-sm">No role</span>
                        </TableCell>
                        <TableCell>
                            <span class="inline-flex items-center gap-2 text-sm">
                                <span :class="user.is_active ? 'bg-emerald-500' : 'bg-muted-foreground'" class="size-2 rounded-full" />
                                {{ user.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </TableCell>
                        <TableCell>
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <span class="inline-flex">
                                            <ShieldCheck v-if="user.passkeys?.length" class="size-4 text-blue-500" />
                                            <Shield v-else class="size-4 text-amber-400" />
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {{ user.passkeys?.length ? '2FA enabled' : '2FA not configured' }}
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </TableCell>
                        <TableCell class="text-muted-foreground text-sm">
                            <RelativeTime :date="user.last_login_at" fallback="Never" />
                        </TableCell>
                        <TableCell class="pr-5 text-right" @click.stop>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon-sm" aria-label="Open user actions">
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-36">
                                    <DropdownMenuItem class="cursor-pointer gap-2" @click="openUserDrawer(user)">
                                        <Pencil class="size-4 text-muted-foreground" />
                                        <span>Edit</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
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
                        <TableCell colspan="6" class="text-muted-foreground h-24 text-center">No users found.</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination -->
        <div class="flex flex-col justify-between gap-3 text-sm sm:flex-row sm:items-center">
            <p class="text-muted-foreground">Showing {{ admins.from || 0 }} to {{ admins.to || 0 }} of {{ admins.total }} users</p>
            <div class="flex items-center gap-1">
                <Button v-for="link in admins.links" :key="link.label" variant="outline" size="sm" :disabled="!link.url" :class="{ 'bg-primary/10 text-primary border-primary/20': link.active }" @click="link.url ? router.get(link.url) : null">
                    <span v-html="link.label" />
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

    <!-- Reusable Delete Confirmation Dialog -->
    <ConfirmDeleteDialog
        v-model:open="deleteDialogOpen"
        :title="`Delete ${userToDelete?.name}?`"
        :item-name="userToDelete?.name"
        item-type="User"
        description="This will permanently delete this administrator account. They will immediately lose access to the administration dashboard and all associated privileges."
        confirm-text="Delete user"
        @confirm="confirmDeleteUser"
    />
</template>