<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    Copy,
    KeyRound,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
} from '@lucide/vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
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
import { roles as rolesRoute } from '@/routes/admin/authorization';
import { allPermissionKeys, roles, type Role } from './mockData';

const roleToDelete = ref<Role | null>(null);
const deleteDialogOpen = ref(false);

const search = ref('');
const selectedType = ref('All types');
const memberFilterRole = ref<string | null>(null);
const currentPage = ref(1);
const pageSize = 3;
const roleTypes = ['All types', 'System', 'Custom'];

const filteredRoles = computed(() =>
    roles.value.filter((role) => {
        const matchesSearch = `${role.name} ${role.description}`.toLowerCase().includes(search.value.toLowerCase());
        const roleType = role.system ? 'System' : 'Custom';
        const matchesType = !selectedType.value || selectedType.value === 'All types' || selectedType.value === roleType;
        const matchesMemberFilter = !memberFilterRole.value || role.name === memberFilterRole.value;

        return matchesSearch && matchesType && matchesMemberFilter;
    }),
);

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRoles.value.length / pageSize)));
const paginatedRoles = computed(() =>
    filteredRoles.value.slice((currentPage.value - 1) * pageSize, currentPage.value * pageSize),
);
const pageNumbers = computed(() => Array.from({ length: totalPages.value }, (_, index) => index + 1));

watch([search, selectedType, memberFilterRole], () => {
    currentPage.value = 1;
});

watch(totalPages, (value) => {
    currentPage.value = Math.min(currentPage.value, value);
});

function promptDeleteRole(role: Role): void {
    roleToDelete.value = role;
    deleteDialogOpen.value = true;
}

function confirmDeleteRole(): void {
    if (roleToDelete.value) {
        roles.value = roles.value.filter((role) => role.id !== roleToDelete.value!.id);
        roleToDelete.value = null;
    }
    deleteDialogOpen.value = false;
}

function duplicateRole(role: Role): void {
    const nextId = Math.max(0, ...roles.value.map((r) => r.id)) + 1;
    const newRole: Role = {
        id: nextId,
        name: `${role.name} (Copy)`,
        description: role.description ? `Copy of ${role.description}` : `Copy of ${role.name}`,
        members: 0,
        permissionKeys: [...role.permissionKeys],
        system: false,
    };
    const index = roles.value.findIndex((r) => r.id === role.id);
    if (index !== -1) {
        roles.value.splice(index + 1, 0, newRole);
    } else {
        roles.value.push(newRole);
    }
}

function filterByRole(roleName: string): void {
    memberFilterRole.value = memberFilterRole.value === roleName ? null : roleName;
}

function goToPage(page: number): void {
    currentPage.value = Math.min(Math.max(page, 1), totalPages.value);
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Roles', href: rolesRoute.url() }] },
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
            <Button class="w-fit" as-child>
                <Link :href="rolesRoute.create.url()">
                    <Plus class="size-4" />
                    Create role
                </Link>
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
                        <TableHead v-if="memberFilterRole" class="text-xs text-muted-foreground font-normal">
                            Filtered by: <button class="underline" @click="memberFilterRole = null">{{ memberFilterRole }} ✕</button>
                        </TableHead>
                        <TableHead class="w-12 pr-5"><span class="sr-only">Actions</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="role in paginatedRoles"
                        :key="role.id"
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
                        <TableCell>
                            <button
                                class="text-sm font-medium underline decoration-dotted underline-offset-4 hover:text-primary transition-colors"
                                @click.stop="filterByRole(role.name)"
                            >
                                {{ role.members }}
                            </button>
                        </TableCell>
                        <TableCell>
                            <Badge variant="secondary">{{ role.permissionKeys.length }} permissions</Badge>
                        </TableCell>
                        <TableCell>
                            <Badge :variant="role.system ? 'outline' : 'default'">
                                {{ role.system ? 'System' : 'Custom' }}
                            </Badge>
                        </TableCell>
                        <TableCell class="pr-5 text-right" @click.stop>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Open role actions"
                                    >
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-40">
                                    <DropdownMenuItem class="cursor-pointer gap-2" as-child>
                                        <Link :href="rolesRoute.edit.url(role.id)" class="flex w-full items-center">
                                            <Pencil class="size-4 text-muted-foreground mr-2" />
                                            <span>Edit</span>
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem class="cursor-pointer gap-2" @click="duplicateRole(role)">
                                        <Copy class="size-4 text-muted-foreground mr-2" />
                                        <span>Duplicate</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        variant="destructive"
                                        class="cursor-pointer gap-2"
                                        @click="promptDeleteRole(role)"
                                    >
                                        <Trash2 class="size-4" />
                                        <span>Delete</span>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
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

    <ConfirmDeleteDialog
        v-model:open="deleteDialogOpen"
        :title="`Delete ${roleToDelete?.name}?`"
        :item-name="roleToDelete?.name"
        item-type="Role"
        description="This removes the role from your authorization setup. Admin users will keep their accounts but lose this role's specific permissions."
        confirm-text="Delete role"
        @confirm="confirmDeleteRole"
    />
</template>