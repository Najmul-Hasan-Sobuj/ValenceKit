<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Activity, ArrowRight, Users, Wallet } from '@lucide/vue';
import FinanceSummaryCard from '@/components/FinanceSummaryCard.vue';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { dashboard } from '@/routes/admin';
import { index as indexMcpUsage } from '@/routes/admin/mcp/usage';
import { index as indexUsers } from '@/routes/admin/users';

const props = defineProps<{
    financeSummary?: any;
    mcpStats?: {
        total_calls: number;
        calls_today: number;
    } | null;
    platformStats?: {
        total_users: number;
        total_admins: number;
        verified_users: number;
        passkey_count: number;
    } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

function formatNumber(num: number): string {
    return new Intl.NumberFormat('en-US').format(num);
}
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4 md:p-6">
        <!-- Top Cards Grid: 3 Clean, Simple Cards -->
        <div class="grid gap-4 md:grid-cols-3">
            <!-- Card 1: Finance (1 big number + 2 simple metrics) -->
            <FinanceSummaryCard
                v-if="financeSummary"
                :summary="financeSummary"
                class="h-full"
            />
            <div
                v-else
                class="flex flex-col justify-between rounded-xl border border-border/80 bg-card p-6 shadow-xs"
            >
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-muted-foreground"
                            >Net Cash Flow</span
                        >
                        <Wallet class="size-4 text-muted-foreground" />
                    </div>
                    <div
                        class="mt-3 text-2xl font-bold tracking-tight text-muted-foreground"
                    >
                        --
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        No financial data available
                    </p>
                </div>
            </div>

            <!-- Card 2: MCP Tool Calls (1 big number + 2 simple metrics) -->
            <div
                class="flex flex-col justify-between rounded-xl border border-border/80 bg-card p-6 shadow-xs transition-colors hover:border-border"
            >
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-muted-foreground"
                            >MCP Tool Calls</span
                        >
                        <div
                            class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Activity class="size-4" />
                        </div>
                    </div>

                    <div
                        class="mt-3 text-3xl font-bold tracking-tight text-foreground"
                    >
                        {{ formatNumber(mcpStats?.total_calls ?? 0) }}
                    </div>

                    <div
                        class="mt-2 flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <span
                            class="inline-flex items-center font-medium text-emerald-500"
                        >
                            <span
                                class="mr-1.5 size-1.5 rounded-full bg-emerald-500"
                            ></span>
                            Server online
                        </span>
                        <span>•</span>
                        <span
                            >{{ mcpStats?.calls_today ?? 0 }} calls today</span
                        >
                    </div>
                </div>

                <div class="mt-5 border-t border-border/50 pt-3">
                    <Link
                        :href="indexMcpUsage()"
                        class="group inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
                    >
                        <span>View Usage Logs</span>
                        <ArrowRight
                            class="size-3.5 transition-transform group-hover:translate-x-0.5"
                        />
                    </Link>
                </div>
            </div>

            <!-- Card 3: Registered Users (1 big number + 2 simple metrics) -->
            <div
                class="flex flex-col justify-between rounded-xl border border-border/80 bg-card p-6 shadow-xs transition-colors hover:border-border"
            >
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-muted-foreground"
                            >Registered Users</span
                        >
                        <div
                            class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Users class="size-4" />
                        </div>
                    </div>

                    <div
                        class="mt-3 text-3xl font-bold tracking-tight text-foreground"
                    >
                        {{ formatNumber(platformStats?.total_users ?? 0) }}
                    </div>

                    <div
                        class="mt-2 flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <span class="font-medium text-foreground">
                            {{ platformStats?.total_admins ?? 0 }} admin
                        </span>
                        <span>•</span>
                        <span
                            >{{
                                platformStats?.verified_users ?? 0
                            }}
                            verified</span
                        >
                    </div>
                </div>

                <div class="mt-5 border-t border-border/50 pt-3">
                    <Link
                        :href="indexUsers()"
                        class="group inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
                    >
                        <span>Manage Users</span>
                        <ArrowRight
                            class="size-3.5 transition-transform group-hover:translate-x-0.5"
                        />
                    </Link>
                </div>
            </div>
        </div>

        <!-- Lower Section: Clean, Minimal Workspace Area -->
        <div
            class="relative min-h-[360px] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <PlaceholderPattern />
        </div>
    </div>
</template>
