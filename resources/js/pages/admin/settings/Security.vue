<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ManagePasskeys from '@/components/ManagePasskeys.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit, update } from '@/routes/admin/security';
import { destroy, register } from '@/routes/admin/passkeys';
import { options } from '@/routes/admin/passkeys/register';
import type { Passkey } from '@/types';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();

defineProps<{ passwordRules: string; passkeys: Passkey[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Security settings', href: edit() }] },
});
</script>

<template>
    <Head title="Security settings" />
    <h1 class="sr-only">Security settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Update password"
            description="Ensure your admin account uses a long, random password"
        />
        <Form
            v-bind="update.form()"
            :options="{ preserveScroll: true }"
            reset-on-success
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="current_password">Current password</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    autocomplete="current-password"
                />
                <InputError :message="errors.current_password" />
            </div>
            <div class="grid gap-2">
                <Label for="password">New password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>
            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>
            <Button :disabled="processing">Save</Button>
        </Form>
    </div>

    <ManagePasskeys
        :can-manage-passkeys="can('settings.security.edit')"
        :passkeys="passkeys"
        :routes="{
            options: options(),
            submit: register(),
            destroy: (id) => destroy(id),
        }"
    />
</template>
