<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, update } from '@/routes/admin/profile';

const admin = computed(() => usePage().props.auth.admin!);

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
