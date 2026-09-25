<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Blobatar } from '@blobatar/vue';
import { useGaze } from '@blobatar/vue/gaze';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, update } from '@/routes/admin/profile';

const admin = computed(() => usePage().props.auth.admin!);
const avatarRef = ref();

useGaze(avatarRef, { travel: 3, target: 'pointer' });

defineOptions({
    layout: { breadcrumbs: [{ title: 'Profile settings', href: edit() }] },
});
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>
    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile"
            description="Update your admin name and email address"
        />

        <div
            class="flex items-center gap-4 rounded-xl border border-sidebar-border/80 bg-card p-4"
        >
            <div
                class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-muted/40 ring-1 ring-border/50"
            >
                <Blobatar
                    ref="avatarRef"
                    :name="admin.email || admin.name"
                    :size="64"
                    animate="always"
                    class="size-14"
                />
            </div>
            <div class="space-y-0.5">
                <p class="text-sm leading-none font-medium">{{ admin.name }}</p>
                <p class="text-xs text-muted-foreground">{{ admin.email }}</p>
            </div>
        </div>

        <Form
            v-bind="update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="admin.name"
                    required
                    autocomplete="name"
                />
                <InputError :message="errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    :default-value="admin.email"
                    required
                    autocomplete="email"
                />
                <InputError :message="errors.email" />
            </div>
            <Button :disabled="processing">Save</Button>
        </Form>
    </div>
</template>
