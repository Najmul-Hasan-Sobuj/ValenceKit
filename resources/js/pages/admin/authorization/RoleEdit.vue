<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, ArrowLeft, Lock } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import RolePermissions from './RolePermissions.vue';
import authorization from '@/routes/admin/authorization';
const rolesRoute = authorization.roles;

interface Role {
    id: number;
    name: string;
    description: string | null;
    system: boolean;
    members: number;
    permissionKeys: string[];
}

const props = defineProps<{
    role: { data: Role };
    permissions: Record<
        string,
        { id: number; name: string; guard_name: string; group_name: string }[]
    >;
}>();

const form = useForm({
    name: props.role.data.name,
    description: props.role.data.description ?? '',
    permissions: props.role.data.permissionKeys,
});

function saveRole(): void {
    if (props.role.data.system) {
        router.get(rolesRoute.index.url());
        return;
    }

    form.put(rolesRoute.update.url(props.role.data.id.toString()));
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Roles', href: authorization.roles.index.url() },
            { title: 'Edit Role', href: '' },
        ],
    },
});
</script>

<template>
    <Head
        :title="
            props.role.data.name ? `Edit ${props.role.data.name}` : 'Edit Role'
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
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-bold tracking-tight">
                            {{
                                props.role.data.system
                                    ? 'System Role Details'
                                    : 'Edit Role'
                            }}
                        </h1>
                        <Badge
                            v-if="props.role.data.system"
                            variant="outline"
                            class="gap-1 border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400"
                        >
                            <Lock class="size-3" />
                            Protected
                        </Badge>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Configure permissions and attributes for this role
                        profile.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3 self-end sm:self-auto">
                <Button variant="outline" as-child>
                    <Link :href="rolesRoute.index.url()">Cancel</Link>
                </Button>
                <Button
                    :disabled="
                        props.role.data.system ||
                        !form.name.trim() ||
                        form.processing
                    "
                    @click="saveRole"
                >
                    <Check class="mr-1.5 size-4" />
                    Save changes
                </Button>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Left Column: Role Details -->
            <div class="space-y-4">
                <!-- System Role Notice -->
                <div
                    v-if="props.role.data.system"
                    class="flex items-start gap-3 rounded-xl border border-amber-500/25 bg-amber-500/5 p-4 text-sm text-muted-foreground"
                >
                    <Lock class="mt-0.5 size-5 shrink-0 text-amber-500" />
                    <div class="space-y-1">
                        <p class="font-medium text-foreground">
                            Core System Role
                        </p>
                        <p>
                            This role is foundational to platform
                            administration. Its permissions and identity are
                            protected to prevent accidental lockouts.
                        </p>
                    </div>
                </div>

                <!-- Role Details Form -->
                <div class="rounded-xl border bg-card p-5 shadow-xs">
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="role-name" class="font-medium">
                                Role name
                                <span
                                    v-if="!props.role.data.system"
                                    class="text-destructive"
                                    >*</span
                                >
                            </Label>
                            <Input
                                id="role-name"
                                v-model="form.name"
                                :disabled="props.role.data.system"
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
                                :disabled="props.role.data.system"
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
            </div>

            <RolePermissions
                v-model="form.permissions"
                :permissions="props.permissions"
                :error="form.errors.permissions"
                :disabled="props.role.data.system"
            />
        </div>
    </div>
</template>
