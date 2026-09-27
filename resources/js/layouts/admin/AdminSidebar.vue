<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Activity,
    ChevronsUpDown,
    KeyRound,
    LayoutGrid,
    LogOut,
    Settings,
    ShieldCheck,
    Users,
    Wallet,
    FolderOpen,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import SidebarFinanceTracker from '@/components/SidebarFinanceTracker.vue';
import UserInfo from '@/components/UserInfo.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard, logout } from '@/routes/admin';
import authorization from '@/routes/admin/authorization';
const roles = authorization.roles;
import { index as users } from '@/routes/admin/users';
import { edit as editProfile } from '@/routes/admin/profile';
import { edit as editSecurity } from '@/routes/admin/security';
import { edit as editAppearance } from '@/routes/admin/appearance';
import { edit as editFinanceSettings } from '@/routes/admin/finance/settings';
import { index as indexMcpUsage } from '@/routes/admin/mcp/usage';
import { index as indexFinance } from '@/routes/admin/finance';
import { index as indexCategories } from '@/routes/admin/categories';
import type { Admin, NavItem } from '@/types';

import { usePermissions } from '@/composables/usePermissions';

const { admin, can } = usePermissions();
const { isMobile, state } = useSidebar();

const firstAvailableSettingsUrl = computed(() => {
    if (can('settings.profile.view')) return editProfile();
    if (can('settings.security.view')) return editSecurity();
    if (can('settings.finance.view')) return editFinanceSettings();
    if (can('settings.appearance.view')) return editAppearance();
    return null;
});

const mainNavItems = computed<NavItem[]>(() => {
    const children: NavItem[] = [];

    if (can('users.view')) {
        children.push({
            title: 'Users',
            href: users(),
            icon: Users,
        });
    }

    if (can('roles.view')) {
        children.push({
            title: 'Roles',
            href: roles.index.url(),
            icon: KeyRound,
        });
    }

    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    if (children.length > 0) {
        items.push({
            title: 'Authorization',
            href: children[0].href,
            icon: ShieldCheck,
            children,
        });
    }

    if (can('settings.mcp.usage.view')) {
        items.push({
            title: 'MCP Usage',
            href: indexMcpUsage(),
            icon: Activity,
        });
    }

    if (can('finance.view')) {
        items.push({
            title: 'Finance Tracker',
            href: indexFinance(),
            icon: Wallet,
        });
    }

    if (can('categories.view')) {
        items.push({
            title: 'Categories',
            href: indexCategories(),
            icon: FolderOpen,
        });
    }

    return items;
});

const handleLogout = () => {
    router.flushAll();
};
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems">
                <template #action="{ item }">
                    <SidebarFinanceTracker v-if="item.title === 'Finance Tracker'" />
                </template>
            </NavMain>
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <SidebarMenuButton
                                size="lg"
                                class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                            >
                                <UserInfo :user="admin" :blobatar="true" />
                                <ChevronsUpDown class="ml-auto size-4" />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                            :side="
                                isMobile
                                    ? 'bottom'
                                    : state === 'collapsed'
                                      ? 'left'
                                      : 'bottom'
                            "
                            align="end"
                            :side-offset="4"
                        >
                            <DropdownMenuLabel class="p-0 font-normal">
                                <div
                                    class="flex items-center gap-2 px-1 py-1.5 text-left text-sm"
                                >
                                    <UserInfo
                                        :user="admin"
                                        :show-email="true"
                                        :blobatar="true"
                                    />
                                </div>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem v-if="firstAvailableSettingsUrl" :as-child="true">
                                <Link
                                    class="block w-full cursor-pointer"
                                    :href="firstAvailableSettingsUrl"
                                    prefetch
                                >
                                    <Settings class="mr-2 h-4 w-4" />
                                    Settings
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator v-if="firstAvailableSettingsUrl" />
                            <DropdownMenuItem :as-child="true">
                                <Link
                                    class="block w-full cursor-pointer"
                                    :href="logout.url()"
                                    method="delete"
                                    @click="handleLogout"
                                    as="button"
                                    data-test="admin-logout-button"
                                >
                                    <LogOut class="mr-2 h-4 w-4" />
                                    Log out
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
