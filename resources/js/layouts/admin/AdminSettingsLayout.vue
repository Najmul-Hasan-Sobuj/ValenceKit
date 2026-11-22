<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/admin/appearance';
import { edit as editMcp } from '@/routes/admin/mcp';
import { edit as editProfile } from '@/routes/admin/profile';
import { edit as editSecurity } from '@/routes/admin/security';
import { edit as editFinanceSettings } from '@/routes/admin/finance/settings';
import { edit as editTrackingSettings } from '@/routes/admin/tracking/settings';
import { edit as editModules } from '@/routes/admin/modules';
import type { NavItem } from '@/types';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();
const page = usePage();
const moduleEnabled = (module: string): boolean =>
    page.props.moduleStates[module] ?? true;

const sidebarNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];

    if (can('settings.profile.view')) {
        items.push({ title: 'Profile', href: editProfile() });
    }

    items.push({ title: 'Security', href: editSecurity() });

    if (moduleEnabled('mcp') && can('settings.mcp.view')) {
        items.push({ title: 'MCP (AI Tools)', href: editMcp() });
    }

    if (can('settings.modules.view')) {
        items.push({ title: 'Modules & Features', href: editModules() });
    }

    if (moduleEnabled('finance') && can('settings.finance.view')) {
        items.push({ title: 'Finance', href: editFinanceSettings() });
    }

    if (can('settings.tracking.view')) {
        items.push({
            title: 'Tracking & Pixels',
            href: editTrackingSettings(),
        });
    }

    if (can('settings.appearance.view')) {
        items.push({ title: 'Appearance', href: editAppearance() });
    }

    return items;
});

const { isCurrentOrParentUrl } = useCurrentUrl();
const isFullWidthPage = computed(
    () =>
        isCurrentOrParentUrl(editMcp()) ||
        isCurrentOrParentUrl(editTrackingSettings()) ||
        isCurrentOrParentUrl(editModules()),
);
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="Settings"
            description="Manage your admin profile and account settings"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside
                class="w-full max-w-xl lg:sticky lg:top-6 lg:w-48 lg:self-start"
            >
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    aria-label="Admin settings"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">{{ item.title }}</Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div :class="['flex-1', { 'md:max-w-2xl': !isFullWidthPage }]">
                <section
                    :class="['space-y-12', { 'max-w-xl': !isFullWidthPage }]"
                >
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
