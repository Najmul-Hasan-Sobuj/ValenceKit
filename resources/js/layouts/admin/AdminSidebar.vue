<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Activity,
    ClipboardCheck,
    ChevronsUpDown,
    KeyRound,
    LayoutGrid,
    LogOut,
    Settings,
    ShieldCheck,
    Users,
    Wallet,
    FolderOpen,
    FileSignature,
    CalendarDays,
    CalendarCheck,
    ChartColumn,
    Contact,
    Mail,
    UserRound,
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
import { edit as editTrackingSettings } from '@/routes/admin/tracking/settings';
import { index as indexMcpUsage } from '@/routes/admin/mcp/usage';
import { index as indexAuditLogs } from '@/routes/admin/audit-logs';
import { index as indexFinance } from '@/routes/admin/finance';
import { index as indexCategories } from '@/routes/admin/categories';
import { index as indexLeads } from '@/routes/admin/leads';
import { index as indexContact } from '@/routes/admin/contact';
import { index as indexHr } from '@/routes/admin/hr';
import { index as indexInvoices } from '@/routes/admin/invoices';
import { index as indexPayroll } from '@/routes/admin/payroll';
import { index as indexBackups } from '@/routes/drivesnap';
import { index as indexExports } from '@/routes/admin/exports';
import { index as indexProposals } from '@/routes/admin/proposals';
import type { Admin, NavItem } from '@/types';

import { usePermissions } from '@/composables/usePermissions';

const { admin, can } = usePermissions();
const { isMobile, state } = useSidebar();

const firstAvailableSettingsUrl = computed(() => {
    if (can('settings.profile.view')) return editProfile();
    if (can('settings.security.view')) return editSecurity();
    if (can('settings.finance.view')) return editFinanceSettings();
    if (can('settings.tracking.view')) return editTrackingSettings();
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

    if (can('backups.view')) {
        items.push({
            title: 'Backups',
            href: indexBackups(),
            icon: FolderOpen,
        });
    }
    if (can('finance.export') || can('leads.export') || can('hr.export')) {
        items.push({
            title: 'Exports',
            href: indexExports(),
            icon: FolderOpen,
        });
    }
    if (can('leads.view')) {
        items.push({ title: 'Leads', href: indexLeads(), icon: Users });
    }

    if (can('contact.view')) {
        items.push({ title: 'Contact', href: indexContact(), icon: Mail });
    }
    const hrChildren: NavItem[] = [];
    if (can('employees.view') || can('hr.team')) {
        hrChildren.push(
            {
                title: 'Overview',
                href: indexHr.url({ query: { section: 'overview' } }),
                icon: LayoutGrid,
            },
            {
                title: 'Employees',
                href: indexHr.url({ query: { section: 'employees' } }),
                icon: Contact,
            },
            {
                title: 'Attendance',
                href: indexHr.url({ query: { section: 'attendance' } }),
                icon: CalendarCheck,
            },
            {
                title: 'Leave',
                href: indexHr.url({ query: { section: 'leave' } }),
                icon: CalendarDays,
            },
            {
                title: 'Reports',
                href: indexHr.url({ query: { section: 'reports' } }),
                icon: ChartColumn,
            },
        );
    }
    if (can('hr.self')) {
        hrChildren.push({
            title: 'My workspace',
            href: indexHr.url({ query: { section: 'workspace' } }),
            icon: UserRound,
        });
    }
    if (can('payroll.view') || can('hr.self')) {
        hrChildren.push({
            title: 'Payroll',
            href: indexPayroll(),
            icon: Wallet,
        });
    }
    if (can('hr.settings.edit')) {
        hrChildren.push({
            title: 'Settings',
            href: indexHr.url({ query: { section: 'settings' } }),
            icon: Settings,
        });
    }
    if (hrChildren.length) {
        items.push({
            title: 'People & HR',
            href: hrChildren[0].href,
            icon: Users,
            children: hrChildren,
        });
    }

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

    if (can('audit.view')) {
        items.push({
            title: 'Audit Logs',
            href: indexAuditLogs(),
            icon: ClipboardCheck,
        });
    }

    if (can('invoices.view')) {
        items.push({ title: 'Invoices', href: indexInvoices(), icon: Wallet });
    }

    if (can('proposals.view')) {
        items.push({
            title: 'Proposals',
            href: indexProposals(),
            icon: FileSignature,
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
                    <SidebarFinanceTracker
                        v-if="item.title === 'Finance Tracker'"
                    />
                </template>
            </NavMain>
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem>
                    <DropdownMenu v-if="admin">
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
                            <DropdownMenuItem
                                v-if="firstAvailableSettingsUrl"
                                :as-child="true"
                            >
                                <Link
                                    class="block w-full cursor-pointer"
                                    :href="firstAvailableSettingsUrl"
                                    prefetch
                                >
                                    <Settings class="mr-2 h-4 w-4" />
                                    Settings
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator
                                v-if="firstAvailableSettingsUrl"
                            />
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
