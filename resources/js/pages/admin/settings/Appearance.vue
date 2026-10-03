<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/admin/appearance';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Appearance settings', href: edit() }] },
});
</script>

<template>
    <Head title="Appearance settings" />
    <h1 class="sr-only">Appearance settings</h1>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="Appearance settings"
            description="Update the appearance of your admin panel"
        />
        <div v-if="can('settings.appearance.edit')">
            <AppearanceTabs />
        </div>
        <div
            v-else
            class="rounded-xl border border-muted bg-card/60 p-4 text-sm text-muted-foreground"
        >
            You have view-only access to appearance settings. Modifying the
            theme requires edit permissions.
        </div>
    </div>
</template>
