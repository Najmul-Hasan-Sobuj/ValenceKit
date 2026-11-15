<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AdminNotifications from '@/components/AppNotifications.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import AdminSidebar from '@/layouts/admin/AdminSidebar.vue';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <AppShell variant="sidebar">
        <AdminSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="breadcrumbs"
                ><AdminNotifications
            /></AppSidebarHeader>
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
