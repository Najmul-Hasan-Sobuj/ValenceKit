<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
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
    Images,
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
import { index as indexMedia } from '@/routes/admin/media';
import type { Admin, NavItem } from '@/types';

import { usePermissions } from '@/composables/usePermissions';

const { admin, can } = usePermissions();
const { isMobile, state } = useSidebar();
const page = usePage();
const moduleEnabled = (module: string): boolean =>
    page.props.moduleStates[module] ?? true;

const firstAvailableSettingsUrl = computed(() => {
    if (can('settings.profile.view')) return editProfile();
    if (can('settings.security.view')) return editSecurity();
    if (can('settings.finance.view')) return editFinanceSettings();
    if (can('settings.tracking.view')) return editTrackingSettings();
    if (can('settings.appearance.view')) return editAppearance();
    return null;
});

interface NavGroup {
    label: string;
    items: NavItem[];
}

const mainNavGroups = computed<NavGroup[]>(() => {
    const overview: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    ];
    const business: NavItem[] = [];
    const operations: NavItem[] = [];
    const administration: NavItem[] = [];
    const authorizationChildren: NavItem[] = [];

    if (can('users.view')) {
        authorizationChildren.push({
            title: 'Users',
            href: users(),
            icon: Users,
        });
    }

    if (can('roles.view')) {
        authorizationChildren.push({
            title: 'Roles',
            href: roles.index.url(),
            icon: KeyRound,
        });
    }

    if (moduleEnabled('leads') && can('leads.view')) {
        business.push({ title: 'Leads', href: indexLeads(), icon: Users });
    }

    if (moduleEnabled('contact') && can('contact.view')) {
        business.push({ title: 'Contact', href: indexContact(), icon: Mail });
    }

    if (moduleEnabled('proposals') && can('proposals.view')) {
        business.push({
            title: 'Proposals',
            href: indexProposals(),
            icon: FileSignature,
        });
    }

    if (moduleEnabled('billing') && can('invoices.view')) {
        business.push({
            title: 'Invoices',
            href: indexInvoices(),
            icon: Wallet,
        });
    }

    const hrChildren: NavItem[] = [];
    if (moduleEnabled('people') && (can('employees.view') || can('hr.team'))) {
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
    if (moduleEnabled('people') && can('hr.self')) {
        hrChildren.push({
            title: 'My workspace',
            href: indexHr.url({ query: { section: 'workspace' } }),
            icon: UserRound,
        });
    }
    if (moduleEnabled('people') && (can('payroll.view') || can('hr.self'))) {
        hrChildren.push({
            title: 'Payroll',
            href: indexPayroll(),
            icon: Wallet,
        });
    }
    if (moduleEnabled('people') && can('hr.settings.edit')) {
        hrChildren.push({
            title: 'Settings',
            href: indexHr.url({ query: { section: 'settings' } }),
            icon: Settings,
        });
    }
    if (hrChildren.length) {
        operations.push({
            title: 'People & HR',
            href: hrChildren[0].href,
            icon: Users,
            children: hrChildren,
        });
    }

    if (moduleEnabled('finance') && can('finance.view')) {
        operations.push({
            title: 'Finance Tracker',
            href: indexFinance(),
            icon: Wallet,
        });
    }

    if (moduleEnabled('categories') && can('categories.view')) {
        operations.push({
            title: 'Categories',
            href: indexCategories(),
            icon: FolderOpen,
        });
    }

    if (moduleEnabled('media') && can('media.view')) {
        operations.push({
            title: 'Media Library',
            href: indexMedia(),
            icon: Images,
        });
    }

    if (
        moduleEnabled('exports') &&
        (can('finance.export') || can('leads.export') || can('hr.export'))
    ) {
        operations.push({
            title: 'Exports',
            href: indexExports(),
            icon: FolderOpen,
        });
    }

    if (moduleEnabled('backups') && can('backups.view')) {
        administration.push({
            title: 'Backups',
            href: indexBackups(),
            icon: FolderOpen,
        });
    }

    if (authorizationChildren.length > 0) {
        administration.push({
            title: 'Authorization',
            href: authorizationChildren[0].href,
            icon: ShieldCheck,
            children: authorizationChildren,
        });
    }

    if (moduleEnabled('mcp') && can('settings.mcp.usage.view')) {
        administration.push({
            title: 'MCP Usage',
            href: indexMcpUsage(),
            icon: Activity,
        });
    }

    if (moduleEnabled('audit') && can('audit.view')) {
        administration.push({
            title: 'Audit Logs',
            href: indexAuditLogs(),
            icon: ClipboardCheck,
        });
    }

    return [
        { label: 'Overview', items: overview },
        { label: 'Business', items: business },
        { label: 'Operations', items: operations },
        { label: 'Administration', items: administration },
    ].filter((group) => group.items.length > 0);
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
            <NavMain
                v-for="group in mainNavGroups"
                :key="group.label"
                :label="group.label"
                :items="group.items"
            >
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
