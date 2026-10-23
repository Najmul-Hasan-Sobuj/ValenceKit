<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { decodePaginationLabel } from '@/lib/decodePaginationLabel';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Blobatar } from '@blobatar/vue';
import RelativeTime from '@/components/RelativeTime.vue';
import { Button } from '@/components/ui/button';

import { computed, ref, watch } from 'vue';
import { index as indexMcpUsage } from '@/routes/admin/mcp/usage';
import { BarChart3, Activity, Users, Radio } from '@lucide/vue';
import DateRangePicker from '@/components/ui/date-range-picker/DateRangePicker.vue';
import { parseDate } from '@internationalized/date';
import type { DateRange } from '@/types';
import { Bar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
} from 'chart.js';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
    Legend,
);

const props = defineProps<{
    logs: {
        data: Array<{
            id: number;
            token_used: string;
            ip_address: string;
            user_agent: string;
            endpoint: string;
            created_at: string;
            user: {
                id: number;
                name: string;
                email: string;
                avatar?: string;
            } | null;
            admin: {
                id: number;
                name: string;
                email: string;
                avatar?: string;
            } | null;
        }>;
        links: any[];
        from?: number;
        to?: number;
        total?: number;
    };
    activityChart?: Array<{
        day: string;
        date: string;
        requests: number;
    }>;
    stats?: {
        totalRequests: number;
        uniqueClients: number;
        todayRequests: number;
    };
    chartInterval?: 'day' | 'month';
    filters?: {
        from?: string;
        to?: string;
    };
}>();

const chartData = computed(() => {
    return {
        labels: props.activityChart?.map((item) => item.date) || [],
        datasets: [
            {
                label: 'Requests',
                backgroundColor: '#6366f1',
                borderRadius: 4,
                maxBarThickness: 32,
                data: props.activityChart?.map((item) => item.requests) || [],
            },
        ],
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
    },
    scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } },
        x: { grid: { display: false } },
    },
};

const formattedLogs = computed(() => {
    return props.logs.data;
});

const dateRange = ref<DateRange | undefined>(undefined);

watch(
    () => props.filters,
    (newFilters) => {
        if (newFilters?.from && newFilters?.to) {
            const s = parseDate(newFilters.from);
            const e = parseDate(newFilters.to);
            if (
                !dateRange.value?.start ||
                dateRange.value.start.toString() !== newFilters.from ||
                !dateRange.value?.end ||
                dateRange.value.end.toString() !== newFilters.to
            ) {
                dateRange.value = { start: s, end: e };
            }
        } else {
            dateRange.value = undefined;
        }
    },
    { immediate: true, deep: true },
);

watch(dateRange, (newRange) => {
    if (newRange?.start && newRange?.end) {
        const newFrom = newRange.start.toString();
        const newTo = newRange.end.toString();
        if (newFrom !== props.filters?.from || newTo !== props.filters?.to) {
            router.get(
                indexMcpUsage(),
                {
                    from: newFrom,
                    to: newTo,
                },
                { preserveState: true, replace: true },
            );
        }
    } else if (!newRange || (!newRange.start && !newRange.end)) {
        if (props.filters?.from || props.filters?.to) {
            router.get(
                indexMcpUsage(),
                {},
                { preserveState: true, replace: true },
            );
        }
    }
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'MCP Usage Logs', href: indexMcpUsage() }],
    },
});
</script>

<template>
    <Head title="MCP Usage Logs" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
        >
            <Heading
                variant="small"
                title="MCP Usage Logs"
                description="Monitor who is connecting to your MCP server and using AI tools."
            />
            <div class="flex items-center gap-3">
                <DateRangePicker
                    v-model="dateRange"
                    class="w-64 bg-background"
                />
                <Badge
                    variant="outline"
                    class="gap-1.5 border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-xs text-emerald-500"
                >
                    <Radio class="size-3 animate-pulse text-emerald-500" />
                    Live Logging Active
                </Badge>
            </div>
        </div>

        <!-- Usage Activity Chart & Stats -->
        <Card class="overflow-hidden border-border/80">
            <CardHeader class="pb-3">
                <div class="flex items-center justify-between">
                    <div class="space-y-1">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <BarChart3 class="size-4 text-primary" />
                            Invocations & Traffic
                        </CardTitle>
                        <CardDescription>
                            Volume of MCP requests received from AI agents
                            (Cursor, Claude Desktop).
                        </CardDescription>
                    </div>
                    <div class="hidden text-right sm:block">
                        <div class="text-xs text-muted-foreground">
                            Total Invocations
                        </div>
                        <div class="text-lg font-bold">
                            {{ stats?.totalRequests ?? logs.data.length }}
                        </div>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Quick Stats -->
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-lg border bg-muted/20 p-3">
                        <div class="text-xs text-muted-foreground">
                            Total Requests
                        </div>
                        <div class="mt-1 text-xl font-bold">
                            {{ stats?.totalRequests ?? logs.data.length }}
                        </div>
                    </div>
                    <div class="rounded-lg border bg-muted/20 p-3">
                        <div class="text-xs text-muted-foreground">
                            Unique Clients
                        </div>
                        <div class="mt-1 text-xl font-bold">
                            {{ stats?.uniqueClients ?? 1 }}
                        </div>
                    </div>
                    <div class="rounded-lg border bg-muted/20 p-3">
                        <div class="text-xs text-muted-foreground">
                            Today's Volume
                        </div>
                        <div class="mt-1 text-xl font-bold text-primary">
                            {{ stats?.todayRequests ?? 0 }}
                        </div>
                    </div>
                </div>

                <!-- Activity Bar Chart -->
                <div class="rounded-xl border bg-muted/20 p-4">
                    <div class="mb-4 flex items-center justify-between">
                        <span
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >{{
                                chartInterval === 'month'
                                    ? 'Monthly Request Distribution'
                                    : 'Daily Request Distribution'
                            }}</span
                        >
                    </div>

                    <div class="h-64">
                        <Bar :data="chartData" :options="chartOptions" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">User / Token</TableHead>
                        <TableHead>Endpoint</TableHead>
                        <TableHead>IP Address</TableHead>
                        <TableHead>Time</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="log in formattedLogs" :key="log.id">
                        <TableCell class="pl-4">
                            <div class="flex items-center gap-3">
                                <div class="relative shrink-0">
                                    <Avatar
                                        class="size-9 overflow-hidden rounded-lg bg-muted/40 ring-1 ring-border/50"
                                    >
                                        <AvatarImage
                                            v-if="
                                                (log.admin || log.user)?.avatar
                                            "
                                            :src="
                                                (log.admin || log.user)
                                                    ?.avatar || ''
                                            "
                                            :alt="
                                                (log.admin || log.user)?.name ||
                                                ''
                                            "
                                        />
                                        <Blobatar
                                            v-else
                                            :name="
                                                log.admin || log.user
                                                    ? (log.admin || log.user)
                                                          ?.email || ''
                                                    : log.token_used
                                            "
                                            :size="36"
                                            animate="hover"
                                            class="size-full object-cover"
                                        />
                                    </Avatar>
                                </div>
                                <div>
                                    <div class="font-medium">
                                        {{
                                            log.admin || log.user
                                                ? (log.admin || log.user)?.name
                                                : log.token_used ===
                                                    'default_env_token'
                                                  ? 'System Application'
                                                  : 'Unauthenticated'
                                        }}
                                    </div>
                                    <div
                                        class="text-xs break-all text-muted-foreground"
                                        v-if="
                                            !(log.admin || log.user) ||
                                            log.token_used
                                        "
                                    >
                                        <span
                                            v-if="
                                                log.token_used ===
                                                'default_env_token'
                                            "
                                            >Environment Configuration
                                            (.env)</span
                                        >
                                        <span v-else>{{ log.token_used }}</span>
                                    </div>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <Badge
                                variant="secondary"
                                class="font-mono text-xs"
                                >{{ log.endpoint }}</Badge
                            >
                        </TableCell>
                        <TableCell>
                            <span
                                class="font-mono text-sm text-muted-foreground"
                                >{{ log.ip_address }}</span
                            >
                        </TableCell>
                        <TableCell class="text-sm text-muted-foreground">
                            <RelativeTime
                                :date="log.created_at"
                                fallback="Unknown"
                            />
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="formattedLogs.length === 0">
                        <TableCell
                            colspan="4"
                            class="h-24 text-center text-muted-foreground"
                        >
                            No usage logs found.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination -->
        <div
            class="flex flex-col justify-between gap-3 text-sm sm:flex-row sm:items-center"
        >
            <p class="text-muted-foreground">
                Showing {{ logs.from || 0 }} to {{ logs.to || 0 }} of
                {{ logs.total || 0 }} requests
            </p>
            <div class="flex items-center gap-1">
                <Button
                    v-for="link in logs.links"
                    :key="link.label"
                    variant="outline"
                    size="sm"
                    :disabled="!link.url"
                    :class="{
                        'border-primary/20 bg-primary/10 text-primary':
                            link.active,
                    }"
                    @click="link.url ? router.get(link.url) : null"
                >
                    <span>{{ decodePaginationLabel(link.label) }}</span>
                </Button>
            </div>
        </div>
    </div>
</template>
