<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Check, ArrowLeft } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import RolePermissions from './RolePermissions.vue';
import authorization from '@/routes/admin/authorization';
const rolesRoute = authorization.roles;

const props = defineProps<{
    permissions: Record<
        string,
        { id: number; name: string; guard_name: string; group_name: string }[]
    >;
    initialRole?: {
        source_id?: number;
        source_name?: string;
        name: string;
        description: string | null;
        permissions: string[];
    } | null;
}>();

const isDuplicating = computed(() => !!props.initialRole);

const form = useForm({
    name: props.initialRole?.name ?? '',
    description: props.initialRole?.description ?? '',
    permissions: props.initialRole?.permissions
        ? [...props.initialRole.permissions]
        : [],
});

function saveRole(): void {
    form.post(rolesRoute.store.url());
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Roles', href: authorization.roles.index.url() },
            { title: 'Create Role', href: '' },
        ],
    },
});
</script>

<template>
    <Head
        :title="
            isDuplicating
                ? `Duplicate ${props.initialRole?.source_name ?? 'Role'}`
                : 'Create Role'
        "
    />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-4">
                <Button variant="outline" size="icon" as-child class="shrink-0">
                    <Link :href="rolesRoute.index.url()">
                        <ArrowLeft class="size-4" />
                        <span class="sr-only">Back</span>
                    </Link>
                </Button>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">
                        {{
                            isDuplicating
                                ? `Duplicate Role: ${props.initialRole?.source_name}`
                                : 'Create New Role'
                        }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            isDuplicating
                                ? 'Review and adjust operational boundaries for this copied role.'
                                : 'Define a new role and configure its operational access boundaries.'
                        }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3 self-end sm:self-auto">
                <Button variant="outline" as-child>
                    <Link :href="rolesRoute.index.url()">Cancel</Link>
                </Button>
                <Button
                    :disabled="!form.name.trim() || form.processing"
                    @click="saveRole"
                >
                    <Check class="mr-1.5 size-4" />
                    {{ isDuplicating ? 'Duplicate role' : 'Create role' }}
                </Button>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Role Details Form -->
            <div class="rounded-xl border bg-card p-5 shadow-xs">
                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="role-name" class="font-medium">
                            Role name
                            <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            id="role-name"
                            v-model="form.name"
                            placeholder="e.g. Support Specialist"
                            :class="
                                form.errors.name
                                    ? 'border-destructive focus-visible:ring-destructive'
                                    : ''
                            "
                        />
                        <p
                            v-if="form.errors.name"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="role-description" class="font-medium"
                            >Description</Label
                        >
                        <Input
                            id="role-description"
                            v-model="form.description"
                            placeholder="e.g. Manage user accounts and view system settings"
                        />
                        <p
                            v-if="form.errors.description"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>
                </div>
            </div>

            <RolePermissions
                v-model="form.permissions"
                :permissions="props.permissions"
                :error="form.errors.permissions"
            />
        </div>
    </div>
</template>
