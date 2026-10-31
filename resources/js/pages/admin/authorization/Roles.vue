<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    Copy,
    Eye,
    KeyRound,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
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
import authorization from '@/routes/admin/authorization';
const rolesRoute = authorization.roles;
import { useForm, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps<{
    roles: { data: Role[] };
    allPermissionKeys: string[];
}>();

const { can, canEditRole, canDeleteRole, canDuplicateRole } = usePermissions();

const canCreateRoles = computed(() => can('roles.create'));
const canEditRoles = computed(() => can('roles.edit'));
const canDeleteRoles = computed(() => can('roles.delete'));

interface Role {
    id: number;
    name: string;
    description: string | null;
    system: boolean;
    members: number;
    permissionKeys: string[];
}

const roleToDelete = ref<Role | null>(null);
const deleteDialogOpen = ref(false);

const search = ref('');
const selectedType = ref('All types');
const memberFilterRole = ref<string | null>(null);
const currentPage = ref(1);
const pageSize = 3;
const roleTypes = ['All types', 'System', 'Custom'];

const filteredRoles = computed(() =>
    props.roles.data.filter((role) => {
        const matchesSearch = `${role.name} ${role.description ?? ''}`
            .toLowerCase()
            .includes(search.value.toLowerCase());
        const roleType = role.system ? 'System' : 'Custom';
        const matchesType =
            !selectedType.value ||
            selectedType.value === 'All types' ||
            selectedType.value === roleType;
        const matchesMemberFilter =
            !memberFilterRole.value || role.name === memberFilterRole.value;

        return matchesSearch && matchesType && matchesMemberFilter;
    }),
);

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredRoles.value.length / pageSize)),
);
const paginatedRoles = computed(() =>
    filteredRoles.value.slice(
        (currentPage.value - 1) * pageSize,
        currentPage.value * pageSize,
    ),
);
const pageNumbers = computed(() =>
    Array.from({ length: totalPages.value }, (_, index) => index + 1),
);

watch([search, selectedType, memberFilterRole], () => {
    currentPage.value = 1;
});

watch(totalPages, (value) => {
    currentPage.value = Math.min(currentPage.value, value);
});

const isDeleting = ref(false);

function promptDeleteRole(role: Role): void {
    roleToDelete.value = role;
    deleteDialogOpen.value = true;
}

function confirmDeleteRole(event?: Event): void {
    event?.preventDefault();
    if (roleToDelete.value && !isDeleting.value) {
        isDeleting.value = true;
        router.delete(rolesRoute.destroy.url(roleToDelete.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                deleteDialogOpen.value = false;
                roleToDelete.value = null;
            },
            onFinish: () => {
                isDeleting.value = false;
            },
        });
    }
}

function duplicateRole(role: Role): void {
    router.get(rolesRoute.create.url({ query: { duplicate: role.id } }));
}

function filterByRole(roleName: string): void {
    memberFilterRole.value =
        memberFilterRole.value === roleName ? null : roleName;
}

function goToPage(page: number): void {
    currentPage.value = Math.min(Math.max(page, 1), totalPages.value);
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Roles', href: authorization.roles.index.url() },
        ],
    },
});
</script>

<template>
    <Head title="Admin Roles" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
        >
            <Heading
                variant="small"
                title="Roles"
                description="Create reusable access profiles for your administrators"
            />
            <Button v-if="canCreateRoles" class="w-fit" as-child>
                <Link :href="rolesRoute.create.url()">
                    <Plus class="size-4" />
                    Create role
                </Link>
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Roles
                </div>
                <div class="mt-2 text-2xl font-semibold">
                    {{ props.roles.data.length }}
                </div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Total permissions
                </div>
                <div class="mt-2 text-2xl font-semibold">
                    {{ props.allPermissionKeys.length }}
                </div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Assigned admins
                </div>
                <div class="mt-2 text-2xl font-semibold">
                    {{
                        props.roles.data.reduce(
                            (total, role) => total + role.members,
                            0,
                        )
                    }}
                </div>
            </div>
        </div>

        <div
            class="grid gap-3 rounded-xl border bg-card p-3 shadow-xs md:grid-cols-[minmax(0,1fr)_14rem] md:items-center"
        >
            <div class="relative min-w-0">
                <Search
                    class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-9"
                    placeholder="Search roles"
                    aria-label="Search roles"
                />
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
                    <ComboboxItem
                        v-for="type in roleTypes"
                        :key="type"
                        :value="type"
                        >{{ type }}</ComboboxItem
                    >
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
                        <TableHead
                            v-if="memberFilterRole"
                            class="text-xs font-normal text-muted-foreground"
                        >
                            Filtered by:
                            <button
                                class="underline"
                                @click="memberFilterRole = null"
                            >
                                {{ memberFilterRole }} ✕
                            </button>
                        </TableHead>
                        <TableHead class="w-12 pr-5"
                            ><span class="sr-only">Actions</span></TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="role in paginatedRoles" :key="role.id">
                        <TableCell class="pl-5">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                >
                                    <KeyRound class="size-4" />
                                </div>
                                <div>
                                    <div class="font-medium">
                                        {{ role.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ role.description }}
                                    </div>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <button
                                class="text-sm font-medium underline decoration-dotted underline-offset-4 transition-colors hover:text-primary"
                                @click.stop="filterByRole(role.name)"
                            >
                                {{ role.members }}
                            </button>
                        </TableCell>
                        <TableCell>
                            <Badge variant="secondary"
                                >{{
                                    role.permissionKeys.length
                                }}
                                permissions</Badge
                            >
                        </TableCell>
                        <TableCell>
                            <Badge
                                :variant="role.system ? 'outline' : 'default'"
                            >
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
                                    <DropdownMenuItem
                                        v-if="canEditRole(role)"
                                        class="cursor-pointer gap-2"
                                        as-child
                                    >
                                        <Link
                                            :href="rolesRoute.edit.url(role.id)"
                                            class="flex w-full items-center"
                                        >
                                            <component
                                                :is="role.system ? Eye : Pencil"
                                                class="mr-2 size-4 text-muted-foreground"
                                            />
                                            <span>{{
                                                role.system ? 'View' : 'Edit'
                                            }}</span>
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="canDuplicateRole(role)"
                                        class="cursor-pointer gap-2"
                                        @click="duplicateRole(role)"
                                    >
                                        <Copy
                                            class="mr-2 size-4 text-muted-foreground"
                                        />
                                        <span>Duplicate</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator
                                        v-if="
                                            (canEditRole(role) ||
                                                canDuplicateRole(role)) &&
                                            canDeleteRole(role)
                                        "
                                    />
                                    <DropdownMenuItem
                                        v-if="canDeleteRole(role)"
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
                        <TableCell
                            colspan="5"
                            class="h-24 text-center text-muted-foreground"
                        >
                            No roles found matching your criteria.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div
            class="flex flex-col justify-between gap-3 text-sm sm:flex-row sm:items-center"
        >
            <p class="text-muted-foreground">
                Showing {{ paginatedRoles.length }} of
                {{ filteredRoles.length }} roles
            </p>
            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="currentPage === 1"
                    @click="goToPage(currentPage - 1)"
                >
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

    <!-- Delete Role Confirmation Dialog -->
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
                        <AlertDialogTitle>Delete Role</AlertDialogTitle>
                        <AlertDialogDescription class="mt-1">
                            Are you sure you want to delete the
                            <span class="font-medium text-foreground">{{
                                roleToDelete?.name
                            }}</span>
                            role?
                        </AlertDialogDescription>
                    </div>
                </div>
            </AlertDialogHeader>
            <div class="text-sm text-muted-foreground">
                This action cannot be undone. This removes the role from your
                authorization setup. Admin users with this role will keep their
                accounts but lose this role's specific permissions.
            </div>
            <AlertDialogFooter class="gap-2">
                <AlertDialogCancel as-child>
                    <Button variant="outline" :disabled="isDeleting"
                        >Cancel</Button
                    >
                </AlertDialogCancel>
                <AlertDialogAction as-child>
                    <Button
                        variant="destructive"
                        :disabled="isDeleting"
                        @click="confirmDeleteRole"
                    >
                        <Trash2 class="size-4" />
                        <span>{{
                            isDeleting ? 'Deleting...' : 'Delete Role'
                        }}</span>
                    </Button>
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
