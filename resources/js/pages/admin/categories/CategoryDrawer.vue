<script setup lang="ts">
import { computed, ref, toRef, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Folder, FolderOpen, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/CategoryController';
import { getCategoryIcon } from '@/lib/iconRegistry';
import InputError from '@/components/InputError.vue';
import IconPicker from '@/components/IconPicker.vue';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import type { AcceptableValue } from 'reka-ui';

const props = defineProps<{
    isOpen: boolean;
    category: any | null;
    module: string;
    parentCategories: Array<{
        id: number;
        name: string;
        module: string;
        type: string;
        is_active?: boolean;
    }>;
}>();

const emit = defineEmits<{
    'update:isOpen': [value: boolean];
    'request-delete': [category: any];
}>();

const { canDeleteCategory } = usePermissions();

const form = useForm({
    module: props.module && props.module !== 'all' ? props.module : 'finance',
    type: null as string | null,
    parent_id: null as number | null,
    name: '',
    icon: 'Folder',
    color: '#6366f1',
    is_active: true,
});

const isParentCategoryInactive = computed(() => {
    if (!form.parent_id) return false;
    const parent = props.parentCategories.find((c) => c.id === form.parent_id);
    return parent ? !parent.is_active : false;
});

watch(
    () => form.parent_id,
    (newParentId) => {
        if (newParentId) {
            const parent = props.parentCategories.find(
                (c) => c.id === newParentId,
            );
            if (parent && !parent.is_active) {
                form.is_active = false;
            }
        }
    },
);

watch(
    () => props.isOpen,
    (isOpen) => {
        if (isOpen) {
            if (props.category) {
                form.module = props.category.module;
                form.type = props.category.type || null;
                form.parent_id = props.category.parent_id || null;
                form.name = props.category.name;
                form.icon = props.category.icon || 'Folder';
                form.color = props.category.color || '#6366f1';
                form.is_active =
                    props.category.is_active !== undefined
                        ? Boolean(props.category.is_active)
                        : true;
            } else {
                form.reset();
                form.module =
                    props.module && props.module !== 'all'
                        ? props.module
                        : 'finance';
                form.type = null;
                form.parent_id = null;
                form.icon = 'Folder';
                form.color = '#6366f1';
                form.is_active = true;
            }
            form.clearErrors();
        }
    },
);

const selectedModuleName = computed(() => {
    return form.module === 'product' ? 'Product Catalog' : 'Finance Tracker';
});

const handleModuleSelect = (val: AcceptableValue) => {
    form.module = val === 'Product Catalog' ? 'product' : 'finance';
};

const selectedTypeName = computed(() => {
    if (!form.type) return 'None / General';
    return form.type === 'income'
        ? 'Income'
        : form.type === 'expense'
          ? 'Expense'
          : 'None / General';
});

const handleTypeSelect = (val: AcceptableValue) => {
    if (val === 'Income') {
        form.type = 'income';
    } else if (val === 'Expense') {
        form.type = 'expense';
    } else {
        form.type = null;
    }
};

const availableTypeItems = computed(() => {
    if (form.module === 'finance') {
        return ['None / General', 'Income', 'Expense'];
    }
    return ['None / General'];
});

const selectedParentName = computed(() => {
    if (!form.parent_id) return 'None (Top Level)';
    const found = props.parentCategories.find((c) => c.id === form.parent_id);
    return found ? found.name : 'None (Top Level)';
});

const handleParentSelect = (val: AcceptableValue) => {
    if (!val || val === 'None (Top Level)') {
        form.parent_id = null;
    } else {
        const found =
            filteredParentCategories.value.find((c) => c.name === val) ||
            props.parentCategories.find((c) => c.name === val);
        form.parent_id = found ? found.id : null;
    }
};

watch(
    () => form.module,
    (newMod, oldMod) => {
        if (newMod !== oldMod) {
            if (newMod !== 'finance') {
                form.type = null;
            }
            if (
                form.parent_id &&
                !filteredParentCategories.value.some(
                    (c) => c.id === form.parent_id,
                )
            ) {
                form.parent_id = null;
            }
        }
    },
);

watch(
    () => form.type,
    () => {
        if (
            form.parent_id &&
            !filteredParentCategories.value.some((c) => c.id === form.parent_id)
        ) {
            form.parent_id = null;
        }
    },
);

const getLucideIcon = (name: string) => {
    return getCategoryIcon(name);
};

const isEditing = computed(() => !!props.category);
const isSystem = computed(() => !!props.category?.is_system);

const filteredParentCategories = computed(() => {
    return props.parentCategories.filter((cat) => {
        const matchesModule = !form.module || cat.module === form.module;
        const matchesType = !cat.type || !form.type || cat.type === form.type;
        const notSelf = isEditing.value ? cat.id !== props.category?.id : true;
        return matchesModule && matchesType && notSelf;
    });
});

const submit = () => {
    if (isEditing.value) {
        form.patch(update.url(props.category.id), {
            preserveScroll: true,
            onSuccess: () => emit('update:isOpen', false),
        });
    } else {
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => emit('update:isOpen', false),
        });
    }
};

const handleOpenChange = (open: boolean) => {
    if (!open && form.isDirty) {
        if (
            window.confirm(
                'You have unsaved changes. Are you sure you want to close?',
            )
        ) {
            form.reset();
            emit('update:isOpen', false);
        }
        return;
    }
    emit('update:isOpen', open);
};
</script>

<template>
    <Drawer :open="isOpen" @update:open="handleOpenChange">
        <DrawerContent class="md:inset-x-auto md:right-2 md:left-64">
            <div class="w-full">
                <DrawerHeader class="pb-6">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-muted/40 ring-1 ring-border/50"
                        >
                            <component
                                :is="getLucideIcon(form.icon || 'Folder')"
                                class="h-5 w-5"
                                :style="{ color: form.color || '#64748b' }"
                            />
                        </div>
                        <div>
                            <DrawerTitle>{{
                                isEditing
                                    ? 'Edit category'
                                    : 'Create new category'
                            }}</DrawerTitle>
                            <DrawerDescription>
                                {{
                                    isEditing
                                        ? 'Update the details of this category.'
                                        : 'Add a new category to the system.'
                                }}
                            </DrawerDescription>
                        </div>
                    </div>
                </DrawerHeader>

                <div class="grid gap-5 px-4 pb-2 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>Module</Label>
                        <Combobox
                            :model-value="selectedModuleName"
                            @update:model-value="handleModuleSelect"
                            :disabled="isSystem"
                        >
                            <ComboboxAnchor>
                                <ComboboxInput
                                    aria-label="Select module"
                                    :display-value="
                                        (value) =>
                                            String(value || 'Finance Tracker')
                                    "
                                    placeholder="Select module"
                                    :disabled="isSystem"
                                />
                            </ComboboxAnchor>
                            <ComboboxList side="bottom" align="start">
                                <ComboboxEmpty>No modules found.</ComboboxEmpty>
                                <ComboboxItem value="Finance Tracker"
                                    >Finance Tracker</ComboboxItem
                                >
                                <ComboboxItem value="Product Catalog"
                                    >Product Catalog</ComboboxItem
                                >
                            </ComboboxList>
                        </Combobox>
                        <InputError :message="form.errors.module" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Type (Optional)</Label>
                        <Combobox
                            :model-value="selectedTypeName"
                            @update:model-value="handleTypeSelect"
                            :disabled="isSystem || form.module !== 'finance'"
                        >
                            <ComboboxAnchor>
                                <ComboboxInput
                                    aria-label="Select type"
                                    :display-value="
                                        (value) =>
                                            String(value || 'None / General')
                                    "
                                    placeholder="Select type"
                                    :disabled="
                                        isSystem || form.module !== 'finance'
                                    "
                                />
                            </ComboboxAnchor>
                            <ComboboxList side="bottom" align="start">
                                <ComboboxEmpty>No types found.</ComboboxEmpty>
                                <ComboboxItem
                                    v-for="item in availableTypeItems"
                                    :key="item"
                                    :value="item"
                                >
                                    {{ item }}
                                </ComboboxItem>
                            </ComboboxList>
                        </Combobox>
                        <InputError :message="form.errors.type" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="e.g. Cloud Infrastructure"
                            required
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Parent Category (Optional)</Label>
                        <Combobox
                            :model-value="selectedParentName"
                            @update:model-value="handleParentSelect"
                        >
                            <ComboboxAnchor>
                                <ComboboxInput
                                    aria-label="Select parent category"
                                    :display-value="
                                        (value) =>
                                            String(value || 'None (Top Level)')
                                    "
                                    placeholder="Select parent category"
                                />
                            </ComboboxAnchor>
                            <ComboboxList side="bottom" align="start">
                                <ComboboxEmpty
                                    >No parent categories
                                    available.</ComboboxEmpty
                                >
                                <ComboboxItem value="None (Top Level)"
                                    >None (Top Level)</ComboboxItem
                                >
                                <ComboboxItem
                                    v-for="cat in filteredParentCategories"
                                    :key="cat.id"
                                    :value="cat.name"
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span>{{ cat.name }}</span>
                                    <div
                                        class="ml-auto flex items-center gap-1.5"
                                    >
                                        <Badge
                                            variant="outline"
                                            class="px-1 py-0 text-[10px] font-normal uppercase"
                                        >
                                            {{ cat.module }}
                                        </Badge>
                                        <Badge
                                            v-if="cat.type"
                                            variant="secondary"
                                            class="px-1 py-0 text-[10px] font-normal capitalize"
                                        >
                                            {{ cat.type }}
                                        </Badge>
                                    </div>
                                </ComboboxItem>
                            </ComboboxList>
                        </Combobox>
                        <InputError :message="form.errors.parent_id" />
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <Label for="icon">Category Icon</Label>
                            <span class="text-[11px] text-muted-foreground"
                                >Select from 2,000+ icons</span
                            >
                        </div>
                        <IconPicker
                            id="icon"
                            v-model="form.icon"
                            :color="form.color"
                        />
                        <InputError :message="form.errors.icon" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="color">Color</Label>
                        <div class="flex gap-2">
                            <Input
                                type="color"
                                id="color_picker"
                                v-model="form.color"
                                class="h-9 w-12 cursor-pointer p-1"
                            />
                            <Input
                                type="text"
                                v-model="form.color"
                                placeholder="#000000"
                                class="flex-1 font-mono uppercase"
                            />
                        </div>
                        <InputError :message="form.errors.color" />
                    </div>

                    <div
                        class="flex items-center justify-between gap-3 rounded-lg border bg-muted/20 p-3.5 sm:col-span-2"
                    >
                        <div class="space-y-1">
                            <Label
                                for="category_active"
                                class="text-sm font-medium"
                                >Active Status</Label
                            >
                            <p
                                class="text-xs leading-relaxed text-muted-foreground"
                            >
                                <span
                                    v-if="isParentCategoryInactive"
                                    class="font-medium text-amber-600 dark:text-amber-400"
                                >
                                    Disabled because parent category is
                                    currently inactive.
                                </span>
                                <span v-else-if="form.parent_id">
                                    Control whether this subcategory is active
                                    for transaction entries and selection.
                                </span>
                                <span v-else>
                                    Turning this parent category inactive will
                                    automatically turn all its subcategories
                                    inactive.
                                </span>
                            </p>
                        </div>
                        <Switch
                            id="category_active"
                            :model-value="form.is_active"
                            :disabled="isParentCategoryInactive"
                            @update:model-value="form.is_active = $event"
                        />
                    </div>
                </div>

                <DrawerFooter
                    class="flex flex-row items-center justify-between gap-2 px-4"
                >
                    <Button
                        v-if="category && canDeleteCategory(category)"
                        variant="ghost"
                        size="sm"
                        class="cursor-pointer gap-1.5 text-destructive hover:bg-destructive/10 hover:text-destructive"
                        @click="emit('request-delete', category)"
                    >
                        <Trash2 class="size-4" />
                        Delete category
                    </Button>
                    <span v-else />
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="emit('update:isOpen', false)"
                            >Cancel</Button
                        >
                        <Button
                            size="sm"
                            @click="submit"
                            :disabled="form.processing"
                        >
                            {{ isEditing ? 'Save changes' : 'Create category' }}
                        </Button>
                    </div>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>
