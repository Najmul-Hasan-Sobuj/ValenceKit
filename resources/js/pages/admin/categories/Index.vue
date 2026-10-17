<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { watchDebounced } from '@vueuse/core';
import { decodePaginationLabel } from '@/utils/decodePaginationLabel';
import {
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
    Shield,
    FolderOpen,
    CornerDownRight,
    FolderTree,
    Folder,
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
import { Switch } from '@/components/ui/switch';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { index as categoriesRoute } from '@/routes/admin/categories';
import {
    destroy,
    update,
} from '@/actions/App/Http/Controllers/Admin/CategoryController';
import CategoryDrawer from './CategoryDrawer.vue';
import { getCategoryIcon } from '@/utils/iconRegistry';
import { usePermissions } from '@/composables/usePermissions';
import type { AcceptableValue } from 'reka-ui';

const props = defineProps<{
    categories: any;
    parentCategories: Array<{
        id: number;
        name: string;
        module: string;
        type: string;
        is_active?: boolean;
    }>;
    filters: {
        module?: string;
        type?: string;
        search?: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Categories', href: categoriesRoute.url() }],
    },
});

const { can, canEditCategory, canDeleteCategory } = usePermissions();

const getLucideIcon = (name: string) => {
    return getCategoryIcon(name);
};

const filterModule = ref(props.filters.module || 'all');
const filterType = ref(props.filters.type || '');
const searchQuery = ref(props.filters.search || '');

const applyFilters = () => {
    router.visit(categoriesRoute.url(), {
        data: {
            module: filterModule.value === 'all' ? '' : filterModule.value,
            type: filterType.value === 'all' ? '' : filterType.value,
            search: searchQuery.value,
        },
        preserveState: true,
        preserveScroll: true,
    });
};

watchDebounced(searchQuery, applyFilters, { debounce: 500, maxWait: 1000 });

const handleModuleChange = (val: AcceptableValue) => {
    filterModule.value = typeof val === 'string' ? val : 'all';
    applyFilters();
};

const handleTypeChange = (val: AcceptableValue) => {
    filterType.value = typeof val === 'string' && val !== 'all' ? val : '';
    applyFilters();
};

const fromItem = computed(
    () => props.categories.meta?.from ?? props.categories.from ?? 0,
);
const toItem = computed(
    () => props.categories.meta?.to ?? props.categories.to ?? 0,
);
const totalItems = computed(
    () => props.categories.meta?.total ?? props.categories.total ?? 0,
);

const paginationLinks = computed(() => {
    return (
        props.categories.meta?.links ||
        (Array.isArray(props.categories.links) ? props.categories.links : [])
    );
});

const isDrawerOpen = ref(false);
const editingCategory = ref<any>(null);

const openCreateDrawer = () => {
    editingCategory.value = null;
    isDrawerOpen.value = true;
};

const openEditDrawer = (category: any) => {
    editingCategory.value = category;
    isDrawerOpen.value = true;
};

const categoryToDelete = ref<any>(null);
const deleteDialogOpen = ref(false);
const isDeleting = ref(false);

function promptDeleteCategory(category: any): void {
    categoryToDelete.value = category;
    isDrawerOpen.value = false;
    deleteDialogOpen.value = true;
}

function confirmDeleteCategory(event?: Event): void {
    event?.preventDefault();
    if (categoryToDelete.value && !isDeleting.value) {
        isDeleting.value = true;
        router.delete(destroy.url(categoryToDelete.value.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                deleteDialogOpen.value = false;
                categoryToDelete.value = null;
            },
            onFinish: () => {
                isDeleting.value = false;
            },
        });
    }
}

const updatingStatusId = ref<number | null>(null);

const isParentInactive = (category: any) => {
    if (!category.parent_id) return false;
    if (category.parent) {
        return !category.parent.is_active;
    }
    const parent = props.parentCategories.find(
        (c) => c.id === category.parent_id,
    );
    return parent ? !parent.is_active : false;
};

const toggleStatus = (category: any, newStatus: boolean) => {
    updatingStatusId.value = category.id;
    router.patch(
        update.url(category.id),
        {
            is_active: newStatus,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                updatingStatusId.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="Categories" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
        >
            <Heading
                variant="small"
                title="Categories"
                description="Manage categories and groupings for all modules."
            />

            <Button
                @click="openCreateDrawer"
                v-if="can('categories.create')"
                class="w-fit"
            >
                <Plus class="mr-2 h-4 w-4" />
                New category
            </Button>
        </div>

        <div
            class="flex flex-col gap-3 rounded-xl border bg-card p-3 shadow-xs lg:flex-row lg:items-center"
        >
            <div class="relative min-w-0 flex-1">
                <Search
                    class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                />
                <Input
                    v-model="searchQuery"
                    type="search"
                    placeholder="Search categories..."
                    class="pl-9"
                    aria-label="Search categories"
                />
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <Select
                    :model-value="filterModule"
                    @update:model-value="handleModuleChange"
                >
                    <SelectTrigger class="w-full sm:w-48">
                        <SelectValue placeholder="Module" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Modules</SelectItem>
                        <SelectItem value="finance">Finance Tracker</SelectItem>
                        <SelectItem value="product">Product Catalog</SelectItem>
                    </SelectContent>
                </Select>

                <Select
                    :model-value="filterType || 'all'"
                    @update:model-value="handleTypeChange"
                >
                    <SelectTrigger class="w-full sm:w-48">
                        <SelectValue placeholder="Type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Types</SelectItem>
                        <SelectItem value="income">Income</SelectItem>
                        <SelectItem value="expense">Expense</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-[75px] pl-4"></TableHead>
                        <TableHead>Category Name</TableHead>
                        <TableHead>Module</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Hierarchy / Parent</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>System</TableHead>
                        <TableHead class="w-12 pr-5"
                            ><span class="sr-only">Actions</span></TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="category in categories.data"
                        :key="category.id"
                        :class="[
                            can('categories.edit') ? 'cursor-pointer' : '',
                            category.parent_id
                                ? 'border-l-2 border-l-primary/40 bg-muted/15 hover:bg-muted/30'
                                : 'hover:bg-muted/10',
                            !category.is_active ? 'opacity-75' : '',
                        ]"
                        @click="
                            can('categories.edit')
                                ? openEditDrawer(category)
                                : null
                        "
                    >
                        <TableCell class="pl-4">
                            <!-- Parent Category Icon -->
                            <div
                                v-if="!category.parent_id"
                                class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted/40 ring-1 ring-border/50"
                            >
                                <component
                                    :is="
                                        getLucideIcon(
                                            category.icon || 'FolderOpen',
                                        )
                                    "
                                    class="size-4"
                                    :style="{
                                        color: category.color || '#64748b',
                                    }"
                                />
                            </div>

                            <!-- Child Category Thread Branch & Icon -->
                            <div
                                v-else
                                class="flex items-center gap-1.5 pl-1.5"
                            >
                                <CornerDownRight
                                    class="size-4 shrink-0 text-muted-foreground/60"
                                />
                                <div
                                    class="flex size-7 shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted/30 ring-1 ring-border/40"
                                >
                                    <component
                                        :is="
                                            getLucideIcon(
                                                category.icon || 'FolderOpen',
                                            )
                                        "
                                        class="size-3.5"
                                        :style="{
                                            color: category.color || '#64748b',
                                        }"
                                    />
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <!-- Parent Category Details -->
                            <div v-if="!category.parent_id">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-sm font-semibold text-foreground"
                                        >{{ category.name }}</span
                                    >
                                    <Badge
                                        variant="secondary"
                                        class="h-5 bg-muted px-1.5 text-[11px] font-medium text-muted-foreground"
                                    >
                                        {{
                                            category.children_count > 0
                                                ? `${category.children_count} ${category.children_count === 1 ? 'sub-category' : 'sub-categories'}`
                                                : 'Parent'
                                        }}
                                    </Badge>
                                </div>
                                <div
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ category.slug }}
                                </div>
                            </div>

                            <!-- Child Category Details -->
                            <div v-else>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-sm font-medium text-foreground"
                                        >{{ category.name }}</span
                                    >
                                    <span
                                        class="text-xs text-muted-foreground/60"
                                        >under</span
                                    >
                                    <span
                                        class="text-sm font-medium text-foreground/80"
                                        >{{
                                            category.parent?.name ||
                                            parentCategories.find(
                                                (c) =>
                                                    c.id === category.parent_id,
                                            )?.name
                                        }}</span
                                    >
                                    <Badge
                                        variant="outline"
                                        class="h-4.5 border-dashed border-border/70 px-1.5 text-[10px] font-normal text-muted-foreground"
                                    >
                                        Subcategory
                                    </Badge>
                                </div>
                                <div
                                    class="mt-0.5 text-xs text-muted-foreground"
                                >
                                    <span class="font-mono">{{
                                        category.slug
                                    }}</span>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <Badge variant="outline" class="capitalize">{{
                                category.module
                            }}</Badge>
                        </TableCell>
                        <TableCell>
                            <span
                                v-if="category.type"
                                class="text-xs text-muted-foreground capitalize"
                            >
                                {{ category.type }}
                            </span>
                            <span v-else class="text-xs text-muted-foreground"
                                >-</span
                            >
                        </TableCell>
                        <TableCell>
                            <div
                                v-if="category.parent_id"
                                class="flex items-center gap-1.5 text-xs text-foreground/90"
                            >
                                <FolderTree
                                    class="size-3.5 shrink-0 text-muted-foreground"
                                />
                                <span>{{
                                    category.parent?.name ||
                                    parentCategories.find(
                                        (c) => c.id === category.parent_id,
                                    )?.name ||
                                    'Parent'
                                }}</span>
                            </div>
                            <span
                                v-else
                                class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                            >
                                <Folder
                                    class="size-3.5 text-muted-foreground/70"
                                />
                                {{
                                    category.children_count > 0
                                        ? 'Top-level Parent'
                                        : 'Top-level'
                                }}
                            </span>
                        </TableCell>
                        <TableCell @click.stop>
                            <div class="flex items-center gap-2">
                                <Switch
                                    :model-value="Boolean(category.is_active)"
                                    :disabled="
                                        isParentInactive(category) ||
                                        updatingStatusId === category.id
                                    "
                                    @update:model-value="
                                        toggleStatus(category, $event)
                                    "
                                />
                                <div class="flex flex-col">
                                    <span
                                        class="text-xs font-medium"
                                        :class="
                                            category.is_active
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{
                                            category.is_active
                                                ? 'Active'
                                                : 'Inactive'
                                        }}
                                    </span>
                                    <span
                                        v-if="isParentInactive(category)"
                                        class="text-[10px] leading-tight font-medium text-amber-600 dark:text-amber-400"
                                    >
                                        Parent inactive
                                    </span>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <Badge
                                v-if="category.is_system"
                                variant="secondary"
                                class="bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"
                            >
                                <Shield class="mr-1 h-3 w-3" />
                                System
                            </Badge>
                        </TableCell>
                        <TableCell class="pr-5 text-right" @click.stop>
                            <DropdownMenu
                                v-if="
                                    canEditCategory(category) ||
                                    canDeleteCategory(category)
                                "
                            >
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Open category actions"
                                    >
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-36">
                                    <DropdownMenuItem
                                        v-if="canEditCategory(category)"
                                        class="cursor-pointer gap-2"
                                        @click="openEditDrawer(category)"
                                    >
                                        <Pencil
                                            class="size-4 text-muted-foreground"
                                        />
                                        <span>Edit</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator
                                        v-if="
                                            canEditCategory(category) &&
                                            canDeleteCategory(category)
                                        "
                                    />
                                    <DropdownMenuItem
                                        v-if="canDeleteCategory(category)"
                                        variant="destructive"
                                        class="cursor-pointer gap-2"
                                        @click="promptDeleteCategory(category)"
                                    >
                                        <Trash2 class="size-4" />
                                        <span>Delete</span>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>

                    <TableRow v-if="categories.data.length === 0">
                        <TableCell colspan="8" class="h-24 text-center">
                            <div
                                class="flex flex-col items-center justify-center text-muted-foreground"
                            >
                                <FolderOpen class="mb-2 size-8 opacity-50" />
                                <p>No categories found.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

        </div>

        <!-- Pagination -->
        <div
            class="flex flex-col justify-between gap-3 text-sm sm:flex-row sm:items-center"
        >
            <p class="text-muted-foreground">
                Showing {{ fromItem }} to {{ toItem }} of
                {{ totalItems }} categories
            </p>
            <div class="flex items-center gap-1">
                <Button
                    v-for="link in paginationLinks"
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

    <!-- Delete Category Confirmation Dialog -->
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
                        <AlertDialogTitle>Delete Category</AlertDialogTitle>
                        <AlertDialogDescription class="mt-1">
                            Are you sure you want to delete
                            <span class="font-medium text-foreground">{{
                                categoryToDelete?.name
                            }}</span
                            >?
                        </AlertDialogDescription>
                    </div>
                </div>
            </AlertDialogHeader>
            <div class="text-sm text-muted-foreground">
                This action cannot be undone. This category will be permanently
                deleted. If this category has existing records attached to it,
                they may lose their categorization depending on the module.
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
                        @click="confirmDeleteCategory"
                    >
                        <Trash2 class="size-4" />
                        <span>{{
                            isDeleting ? 'Deleting...' : 'Delete Category'
                        }}</span>
                    </Button>
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <CategoryDrawer
        :is-open="isDrawerOpen"
        :category="editingCategory"
        :module="filterModule"
        :parent-categories="parentCategories"
        @update:is-open="isDrawerOpen = $event"
        @request-delete="promptDeleteCategory"
    />
</template>
