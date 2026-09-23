<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { KeyRound, MoreHorizontal, Plus, Search } from '@lucide/vue';
import { watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Drawer, DrawerContent, DrawerDescription, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxInput, ComboboxItem, ComboboxList } from '@/components/ui/combobox';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { roles as rolesRoute } from '@/routes/admin/authorization';

type Permission = { name: string; enabled: boolean };
type PermissionGroup = { name: string; permissions: Permission[] };
type Role = { id: number; name: string; description: string; members: number; permissions: number; system?: boolean };

const roles = ref<Role[]>([
    { id: 1, name: 'Super Admin', description: 'Complete access to every admin area', members: 1, permissions: 18, system: true },
    { id: 2, name: 'Content Manager', description: 'Manage content and media workflows', members: 2, permissions: 9 },
    { id: 3, name: 'Support', description: 'View users and resolve support requests', members: 1, permissions: 5 },
]);

const permissionGroups = ref<PermissionGroup[]>([
    { name: 'User Management', permissions: [{ name: 'View users', enabled: true }, { name: 'Invite users', enabled: true }, { name: 'Edit users', enabled: false }, { name: 'Delete users', enabled: false }] },
    { name: 'Content', permissions: [{ name: 'View content', enabled: true }, { name: 'Create content', enabled: true }, { name: 'Edit content', enabled: true }, { name: 'Delete content', enabled: false }] },
    { name: 'Settings', permissions: [{ name: 'View settings', enabled: false }, { name: 'Edit settings', enabled: false }, { name: 'Manage roles', enabled: false }, { name: 'View audit log', enabled: false }] },
]);

const selectedRole = ref<Role | null>(null);
const drawerOpen = ref(false);
const deleteDialogOpen = ref(false);
const search = ref('');
const selectedType = ref('All types');
const currentPage = ref(1);
const pageSize = 3;
const roleTypes = ['All types', 'System', 'Custom'];
const enabledPermissionCount = computed(() => permissionGroups.value.flatMap((group) => group.permissions).filter((permission) => permission.enabled).length);

const filteredRoles = computed(() => roles.value.filter((role) => {
    const matchesSearch = `${role.name} ${role.description}`.toLowerCase().includes(search.value.toLowerCase());
    const roleType = role.system ? 'System' : 'Custom';
    const matchesType = !selectedType.value || selectedType.value === 'All types' || selectedType.value === roleType;

    return matchesSearch && matchesType;
}));

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRoles.value.length / pageSize)));
const paginatedRoles = computed(() => filteredRoles.value.slice((currentPage.value - 1) * pageSize, currentPage.value * pageSize));
const pageNumbers = computed(() => Array.from({ length: totalPages.value }, (_, index) => index + 1));

watch([search, selectedType], () => {
    currentPage.value = 1;
});

watch(totalPages, (value) => {
    currentPage.value = Math.min(currentPage.value, value);
});

function openRoleDrawer(role?: Role): void {
    selectedRole.value = role ?? null;
    drawerOpen.value = true;
}

function togglePermission(permission: Permission, value: boolean | 'indeterminate'): void {
    permission.enabled = value === true;
}

function removeRole(): void {
    if (selectedRole.value) {
        roles.value = roles.value.filter((role) => role.id !== selectedRole.value?.id);
    }
    deleteDialogOpen.value = false;
    drawerOpen.value = false;
}

function goToPage(page: number): void {
    currentPage.value = Math.min(Math.max(page, 1), totalPages.value);
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Roles', href: rolesRoute() }] },
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
            <Button class="w-fit" @click="openRoleDrawer()">
                <Plus class="size-4" />
                Create role
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Roles</div>
                <div class="mt-2 text-2xl font-semibold">{{ roles.length }}</div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Permissions</div>
                <div class="mt-2 text-2xl font-semibold">{{ enabledPermissionCount }}</div>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Assigned admins</div>
                <div class="mt-2 text-2xl font-semibold">{{ roles.reduce((total, role) => total + role.members, 0) }}</div>
            </div>
        </div>

        <div class="grid gap-3 rounded-xl border bg-card p-3 shadow-sm md:grid-cols-[minmax(0,1fr)_14rem] md:items-center">
            <div class="relative min-w-0">
                <Search class="text-muted-foreground absolute top-2.5 left-3 size-4" />
                <Input v-model="search" class="pl-9" placeholder="Search roles" aria-label="Search roles" />
            </div>
            <Combobox v-model="selectedType">
                <ComboboxAnchor class="w-full">
                    <ComboboxInput aria-label="Filter by type" :display-value="(value) => String(value || 'All types')" placeholder="Filter by type" />
                </ComboboxAnchor>
                <ComboboxList>
                    <ComboboxEmpty>No matching types.</ComboboxEmpty>
                    <ComboboxItem v-for="type in roleTypes" :key="type" :value="type">{{ type }}</ComboboxItem>
                </ComboboxList>
            </Combobox>
        </div>

        <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-5">Role</TableHead>
                        <TableHead>Members</TableHead>
                        <TableHead>Permissions</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead class="w-12 pr-5"><span class="sr-only">Actions</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="role in paginatedRoles" :key="role.id" class="cursor-pointer" @click="openRoleDrawer(role)">
                        <TableCell class="pl-5">
                            <div class="flex items-center gap-3">
                                <div class="bg-primary/10 text-primary flex size-9 items-center justify-center rounded-lg"><KeyRound class="size-4" /></div>
                                <div>
                                    <div class="font-medium">{{ role.name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ role.description }}</div>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>{{ role.members }}</TableCell>
                        <TableCell><Badge variant="secondary">{{ role.permissions }} permissions</Badge></TableCell>
                        <TableCell><Badge :variant="role.system ? 'outline' : 'default'">{{ role.system ? 'System' : 'Custom' }}</Badge></TableCell>
                        <TableCell class="pr-5 text-right">
                            <Button variant="ghost" size="icon-sm" aria-label="Open role actions" @click.stop="openRoleDrawer(role)"><MoreHorizontal class="size-4" /></Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div class="flex flex-col justify-between gap-3 text-sm sm:flex-row sm:items-center">
            <p class="text-muted-foreground">Showing {{ paginatedRoles.length }} of {{ filteredRoles.length }} roles</p>
            <div class="flex items-center gap-1">
                <Button variant="outline" size="sm" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">Previous</Button>
                <Button v-for="page in pageNumbers" :key="page" :variant="page === currentPage ? 'default' : 'outline'" size="sm" class="min-w-9" @click="goToPage(page)">{{ page }}</Button>
                <Button variant="outline" size="sm" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">Next</Button>
            </div>
        </div>
    </div>

    <Drawer v-model:open="drawerOpen">
        <DrawerContent class="md:inset-x-auto md:right-2 md:left-64">
            <div class="w-full overflow-y-auto">
                <DrawerHeader>
                    <DrawerTitle>{{ selectedRole ? 'Edit role' : 'Create role' }}</DrawerTitle>
                    <DrawerDescription>Assign access by grouping permissions into a clear administrator profile.</DrawerDescription>
                </DrawerHeader>

                <div class="grid gap-5 px-4 pb-2">
                    <div class="grid gap-2 sm:max-w-md">
                        <Label for="role-name">Role name</Label>
                        <Input id="role-name" :default-value="selectedRole?.name" placeholder="e.g. Content Manager" />
                    </div>

                    <div class="rounded-xl border bg-muted/20 p-4">
                        <div class="mb-4 flex items-end justify-between gap-3">
                            <div>
                                <h3 class="font-medium">Permissions</h3>
                                <p class="text-muted-foreground mt-1 text-sm">Choose what this role can access.</p>
                            </div>
                            <Badge variant="outline">{{ enabledPermissionCount }} selected</Badge>
                        </div>

                        <div class="grid gap-4">
                            <section v-for="group in permissionGroups" :key="group.name" class="rounded-lg border bg-card p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-sm font-semibold">{{ group.name }}</h4>
                                    <span class="text-muted-foreground text-xs">{{ group.permissions.filter((permission) => permission.enabled).length }}/{{ group.permissions.length }}</span>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label v-for="permission in group.permissions" :key="permission.name" class="hover:bg-muted/50 flex cursor-pointer items-center gap-3 rounded-md border border-transparent p-2 text-sm transition-colors">
                                        <Checkbox :model-value="permission.enabled" @update:model-value="togglePermission(permission, $event)" />
                                        <span>{{ permission.name }}</span>
                                    </label>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>

                <DrawerFooter class="px-4">
                    <div class="flex w-full items-center justify-between gap-2">
                        <AlertDialog v-if="selectedRole && !selectedRole.system" v-model:open="deleteDialogOpen">
                            <AlertDialogTrigger as-child><Button variant="destructive" size="sm">Delete role</Button></AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>Delete {{ selectedRole.name }}?</AlertDialogTitle>
                                    <AlertDialogDescription>This removes the role from your authorization setup. Admin users will keep their accounts but lose this role.</AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                                    <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="removeRole">Delete role</AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                        <span v-else />
                        <div class="flex gap-2">
                            <Button variant="outline" @click="drawerOpen = false">Cancel</Button>
                            <Button @click="drawerOpen = false">Save role</Button>
                        </div>
                    </div>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>