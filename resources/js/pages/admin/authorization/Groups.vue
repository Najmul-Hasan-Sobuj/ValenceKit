<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { MoreHorizontal, Plus, Search, Tags } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxInput, ComboboxItem, ComboboxList } from '@/components/ui/combobox';
import { Drawer, DrawerContent, DrawerDescription, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { groups as groupsRoute } from '@/routes/admin/authorization';

type GroupStatus = 'Active' | 'Draft';
type PermissionGroup = { id: number; name: string; slug: string; description: string; permissions: number; roles: number; status: GroupStatus };

const groups = ref<PermissionGroup[]>([
    { id: 1, name: 'User Management', slug: 'user-management', description: 'Administrator account access.', permissions: 6, roles: 3, status: 'Active' },
    { id: 2, name: 'Content', slug: 'content', description: 'Publishing and content workflows.', permissions: 8, roles: 2, status: 'Active' },
    { id: 3, name: 'Settings', slug: 'settings', description: 'Platform configuration access.', permissions: 5, roles: 2, status: 'Active' },
    { id: 4, name: 'Billing', slug: 'billing', description: 'Subscription and payment access.', permissions: 4, roles: 1, status: 'Draft' },
    { id: 5, name: 'Reports', slug: 'reports', description: 'Analytics and reporting access.', permissions: 7, roles: 2, status: 'Active' },
    { id: 6, name: 'Support', slug: 'support', description: 'Support and issue resolution access.', permissions: 5, roles: 1, status: 'Draft' },
]);

const statuses = ['All statuses', 'Active', 'Draft'];
const sortOptions = ['Name A-Z', 'Name Z-A', 'Most permissions'];
const search = ref('');
const selectedStatus = ref('All statuses');
const selectedSort = ref('Name A-Z');
const currentPage = ref(1);
const pageSize = 4;
const selectedGroup = ref<PermissionGroup | null>(null);
const drawerOpen = ref(false);
const groupName = ref('');
const groupSlug = ref('');
const groupDescription = ref('');

const filteredGroups = computed(() => {
    const query = search.value.trim().toLowerCase();
    const result = groups.value.filter((group) => {
        const matchesSearch = `${group.name} ${group.slug} ${group.description}`.toLowerCase().includes(query);
        const matchesStatus = !selectedStatus.value || selectedStatus.value === 'All statuses' || group.status === selectedStatus.value;
        return matchesSearch && matchesStatus;
    });

    return [...result].sort((first, second) => {
        if (selectedSort.value === 'Name Z-A') return second.name.localeCompare(first.name);
        if (selectedSort.value === 'Most permissions') return second.permissions - first.permissions;
        return first.name.localeCompare(second.name);
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredGroups.value.length / pageSize)));
const paginatedGroups = computed(() => filteredGroups.value.slice((currentPage.value - 1) * pageSize, currentPage.value * pageSize));
const pageNumbers = computed(() => Array.from({ length: totalPages.value }, (_, index) => index + 1));

watch([search, selectedStatus, selectedSort], () => { currentPage.value = 1; });
watch(totalPages, (value) => { currentPage.value = Math.min(currentPage.value, value); });

function openGroupDrawer(group?: PermissionGroup): void {
    selectedGroup.value = group ?? null;
    groupName.value = group?.name ?? '';
    groupSlug.value = group?.slug ?? '';
    groupDescription.value = group?.description ?? '';
    drawerOpen.value = true;
}

function saveGroup(): void {
    if (!groupName.value.trim() || !groupSlug.value.trim()) return;
    if (selectedGroup.value) {
        const group = groups.value.find((item) => item.id === selectedGroup.value?.id);
        if (group) {
            group.name = groupName.value;
            group.slug = groupSlug.value;
            group.description = groupDescription.value;
        }
    } else {
        groups.value.push({ id: Math.max(...groups.value.map((group) => group.id)) + 1, name: groupName.value, slug: groupSlug.value, description: groupDescription.value, permissions: 0, roles: 0, status: 'Draft' });
    }
    drawerOpen.value = false;
}

function removeGroup(): void {
    if (selectedGroup.value) groups.value = groups.value.filter((group) => group.id !== selectedGroup.value?.id);
    drawerOpen.value = false;
}

function goToPage(page: number): void { currentPage.value = Math.min(Math.max(page, 1), totalPages.value); }

defineOptions({ layout: { breadcrumbs: [{ title: 'Groups', href: groupsRoute() }] } });
</script>

<template>
    <Head title="Admin Groups" />
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <Heading variant="small" title="Groups" description="Organize permissions into manageable access areas" />
            <Button class="w-fit" @click="openGroupDrawer()"><Plus class="size-4" />Create group</Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border bg-card p-4 shadow-sm"><div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Groups</div><div class="mt-2 text-2xl font-semibold">{{ groups.length }}</div></div>
            <div class="rounded-xl border bg-card p-4 shadow-sm"><div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Permissions</div><div class="mt-2 text-2xl font-semibold">{{ groups.reduce((total, group) => total + group.permissions, 0) }}</div></div>
            <div class="rounded-xl border bg-card p-4 shadow-sm"><div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Assigned roles</div><div class="mt-2 text-2xl font-semibold">{{ groups.reduce((total, group) => total + group.roles, 0) }}</div></div>
        </div>

        <div class="grid gap-3 rounded-xl border bg-card p-3 shadow-sm md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_14rem_14rem] xl:items-center">
            <div class="relative min-w-0 md:col-span-2 xl:col-span-1"><Search class="text-muted-foreground absolute top-2.5 left-3 size-4" /><Input v-model="search" class="pl-9" placeholder="Search groups" aria-label="Search groups" /></div>
            <Combobox v-model="selectedStatus"><ComboboxAnchor class="w-full"><ComboboxInput aria-label="Filter by status" :display-value="(value) => String(value || 'All statuses')" placeholder="Filter by status" /></ComboboxAnchor><ComboboxList><ComboboxEmpty>No matching statuses.</ComboboxEmpty><ComboboxItem v-for="status in statuses" :key="status" :value="status">{{ status }}</ComboboxItem></ComboboxList></Combobox>
            <Combobox v-model="selectedSort"><ComboboxAnchor class="w-full"><ComboboxInput aria-label="Sort groups" :display-value="(value) => String(value || 'Name A-Z')" placeholder="Sort groups" /></ComboboxAnchor><ComboboxList><ComboboxEmpty>No matching sort options.</ComboboxEmpty><ComboboxItem v-for="option in sortOptions" :key="option" :value="option">{{ option }}</ComboboxItem></ComboboxList></Combobox>
        </div>

        <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <Table><TableHeader><TableRow><TableHead class="pl-5">Group</TableHead><TableHead>Permissions</TableHead><TableHead>Roles</TableHead><TableHead>Status</TableHead><TableHead class="w-12 pr-5"><span class="sr-only">Actions</span></TableHead></TableRow></TableHeader><TableBody>
                <TableRow v-for="group in paginatedGroups" :key="group.id" class="cursor-pointer" @click="openGroupDrawer(group)"><TableCell class="pl-5"><div class="flex items-center gap-3"><div class="bg-primary/10 text-primary flex size-9 items-center justify-center rounded-lg"><Tags class="size-4" /></div><div><div class="font-medium">{{ group.name }}</div><div class="text-muted-foreground text-xs">{{ group.slug }}</div></div></div></TableCell><TableCell>{{ group.permissions }}</TableCell><TableCell>{{ group.roles }}</TableCell><TableCell><Badge :variant="group.status === 'Active' ? 'default' : 'outline'">{{ group.status }}</Badge></TableCell><TableCell class="pr-5 text-right"><Button variant="ghost" size="icon-sm" aria-label="Open group actions" @click.stop="openGroupDrawer(group)"><MoreHorizontal class="size-4" /></Button></TableCell></TableRow>
                <TableRow v-if="paginatedGroups.length === 0"><TableCell colspan="5" class="text-muted-foreground h-24 text-center">No groups found.</TableCell></TableRow>
            </TableBody></Table>
        </div>

        <div class="flex flex-col justify-between gap-3 text-sm sm:flex-row sm:items-center"><p class="text-muted-foreground">Showing {{ paginatedGroups.length }} of {{ filteredGroups.length }} groups</p><div class="flex items-center gap-1"><Button variant="outline" size="sm" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">Previous</Button><Button v-for="page in pageNumbers" :key="page" :variant="page === currentPage ? 'default' : 'outline'" size="sm" class="min-w-9" @click="goToPage(page)">{{ page }}</Button><Button variant="outline" size="sm" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">Next</Button></div></div>
    </div>

    <Drawer v-model:open="drawerOpen"><DrawerContent class="md:inset-x-auto md:right-2 md:left-64"><div class="w-full overflow-y-auto"><DrawerHeader><DrawerTitle>{{ selectedGroup ? 'Edit group' : 'Create group' }}</DrawerTitle><DrawerDescription>Create a named area for related permissions and access rules.</DrawerDescription></DrawerHeader><div class="grid gap-5 px-4 pb-2 sm:grid-cols-2"><div class="grid gap-2"><Label for="group-name">Group name</Label><Input id="group-name" v-model="groupName" placeholder="e.g. User Management" /></div><div class="grid gap-2"><Label for="group-slug">Slug</Label><Input id="group-slug" v-model="groupSlug" placeholder="e.g. user-management" /></div><div class="grid gap-2 sm:col-span-2"><Label for="group-description">Description</Label><Input id="group-description" v-model="groupDescription" placeholder="Describe what this group controls" /></div></div><DrawerFooter class="px-4"><div class="flex w-full justify-end gap-2"><Button variant="outline" @click="drawerOpen = false">Cancel</Button><Button :disabled="!groupName.trim() || !groupSlug.trim()" @click="saveGroup">Save group</Button></div></DrawerFooter></div></DrawerContent></Drawer>
</template>
