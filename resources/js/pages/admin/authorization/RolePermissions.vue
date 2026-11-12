<script setup lang="ts">
import { computed, ref } from 'vue';
import { ChevronDown, Search, X } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';

interface Permission {
    id: number;
    name: string;
    guard_name: string;
    group_name: string;
}

const props = defineProps<{
    permissions: Record<string, Permission[]>;
    disabled?: boolean;
    error?: string;
}>();
const selectedPermissions = defineModel<string[]>({ required: true });
const search = ref('');
const selectedOnly = ref(false);
const selected = computed(() => new Set(selectedPermissions.value));
const allKeys = computed(() =>
    Object.values(props.permissions).flatMap((group) =>
        group.map((permission) => permission.name),
    ),
);
const groups = computed(() => {
    const query = search.value.trim().toLowerCase();
    return Object.entries(props.permissions)
        .map(([name, permissions]) => ({
            name,
            total: permissions.length,
            selectedCount: permissions.filter((permission) =>
                selected.value.has(permission.name),
            ).length,
            visible: permissions.filter(
                (permission) =>
                    (!selectedOnly.value ||
                        selected.value.has(permission.name)) &&
                    (!query ||
                        name.toLowerCase().includes(query) ||
                        permission.name.toLowerCase().includes(query)),
            ),
        }))
        .filter((group) => group.visible.length > 0);
});
const visibleCount = computed(() =>
    groups.value.reduce((total, group) => total + group.visible.length, 0),
);
const filtering = computed(() => !!search.value.trim() || selectedOnly.value);

function togglePermission(
    name: string,
    checked: boolean | 'indeterminate',
): void {
    if (props.disabled) return;
    selectedPermissions.value =
        checked === true
            ? [...new Set([...selectedPermissions.value, name])]
            : selectedPermissions.value.filter(
                  (permission) => permission !== name,
              );
}

function toggleGroup(permissions: Permission[]): void {
    if (props.disabled) return;
    const names = permissions.map((permission) => permission.name);
    const remove = names.every((name) => selected.value.has(name));
    selectedPermissions.value = remove
        ? selectedPermissions.value.filter((name) => !names.includes(name))
        : [...new Set([...selectedPermissions.value, ...names])];
}
</script>

<template>
    <section
        class="space-y-4 rounded-xl border bg-card p-4 shadow-xs sm:p-5"
        aria-labelledby="permissions-heading"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2
                        id="permissions-heading"
                        class="text-base font-semibold"
                    >
                        Permissions
                    </h2>
                    <Badge variant="secondary"
                        >{{ selectedPermissions.length }} of
                        {{ allKeys.length }} selected</Badge
                    >
                </div>
                <p class="text-sm text-muted-foreground">
                    Choose what people with this role can access. Expand a group
                    to manage its permissions.
                </p>
                <p
                    v-if="props.error"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ props.error }}
                </p>
            </div>
            <div v-if="!props.disabled" class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    type="button"
                    @click="selectedPermissions = [...allKeys]"
                    >Select all permissions</Button
                >
                <Button
                    variant="outline"
                    size="sm"
                    type="button"
                    :disabled="!selectedPermissions.length"
                    @click="selectedPermissions = []"
                    >Clear all</Button
                >
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 border-y py-3">
            <div class="relative min-w-0 flex-1 basis-64">
                <Search
                    class="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Search by group or permission"
                    aria-label="Search permissions"
                    class="pr-9 pl-9"
                />
                <button
                    v-if="search"
                    type="button"
                    class="absolute top-2.5 right-3 text-muted-foreground hover:text-foreground"
                    @click="search = ''"
                >
                    <X class="size-4" /><span class="sr-only"
                        >Clear search</span
                    >
                </button>
            </div>
            <Button
                type="button"
                size="sm"
                :variant="selectedOnly ? 'secondary' : 'outline'"
                :aria-pressed="selectedOnly"
                @click="selectedOnly = !selectedOnly"
                >Selected only</Button
            >
            <span class="text-sm text-muted-foreground" aria-live="polite"
                >{{ visibleCount }} shown</span
            >
        </div>

        <div v-if="groups.length" class="columns-1 gap-3 xl:columns-2">
            <details
                v-for="group in groups"
                :key="group.name"
                :open="filtering || undefined"
                class="group mb-3 break-inside-avoid rounded-lg border bg-background"
            >
                <summary
                    class="flex cursor-pointer list-none items-center gap-3 px-4 py-3 focus-visible:outline-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden"
                >
                    <ChevronDown
                        class="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180"
                    />
                    <span class="min-w-0 flex-1 font-medium break-words">{{
                        group.name
                    }}</span>
                    <Badge variant="outline" class="shrink-0 font-normal"
                        >{{ group.selectedCount }} /
                        {{ group.total }} selected</Badge
                    >
                </summary>
                <div class="space-y-3 border-t p-3">
                    <div
                        v-if="!props.disabled"
                        class="flex items-center justify-between gap-2"
                    >
                        <span class="text-xs text-muted-foreground">{{
                            filtering
                                ? 'Selection applies to matching permissions'
                                : 'Manage this group'
                        }}</span>
                        <Button
                            variant="ghost"
                            size="sm"
                            type="button"
                            @click="toggleGroup(group.visible)"
                        >
                            {{
                                group.visible.every((permission) =>
                                    selected.has(permission.name),
                                )
                                    ? 'Deselect'
                                    : 'Select'
                            }}
                            {{ filtering ? 'shown' : 'group' }}
                        </Button>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-2">
                        <label
                            v-for="permission in group.visible"
                            :key="permission.id"
                            class="flex min-w-0 items-start gap-3 rounded-md px-3 py-2 text-sm hover:bg-muted/60"
                            :class="
                                props.disabled
                                    ? 'cursor-not-allowed opacity-70'
                                    : 'cursor-pointer'
                            "
                        >
                            <Checkbox
                                :model-value="selected.has(permission.name)"
                                :disabled="props.disabled"
                                class="mt-0.5 shrink-0"
                                @update:model-value="
                                    togglePermission(permission.name, $event)
                                "
                            />
                            <span class="min-w-0 break-all">{{
                                permission.name
                            }}</span>
                        </label>
                    </div>
                </div>
            </details>
        </div>
        <div
            v-else
            class="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground"
        >
            No permissions match your filters.
            <button
                type="button"
                class="ml-1 font-medium text-primary underline"
                @click="
                    search = '';
                    selectedOnly = false;
                "
            >
                Clear filters
            </button>
        </div>
    </section>
</template>
