<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { watchDebounced } from '@vueuse/core';
import { Download, Minus, Plus, Search } from '@lucide/vue';
import { parseDate } from '@internationalized/date';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import type { DateRange } from '@/types';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import auditLogs from '@/routes/admin/audit-logs';

type AuditEntry = {
    id: number;
    action: string;
    description: string | null;
    actor: { name: string; email: string } | null;
    actor_type: string | null;
    actor_id: number | null;
    subject_type: string | null;
    subject_id: number | null;
    metadata: Record<string, unknown> | null;
    before_values: Record<string, unknown> | null;
    after_values: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
};

const props = defineProps<{
    logs: {
        data: AuditEntry[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    actions: string[];
    filters: {
        search: string | null;
        action: string | null;
        from: string | null;
        to: string | null;
    };
}>();

const search = ref(props.filters.search ?? '');
const action = ref(props.filters.action ?? '');
function initDateRange(): DateRange | undefined {
    if (!props.filters.from) {
        return undefined;
    }

    try {
        const start = parseDate(props.filters.from);
        const end = props.filters.to ? parseDate(props.filters.to) : start;

        return { start, end };
    } catch {
        return undefined;
    }
}

const dateRange = ref<DateRange | undefined>(initDateRange());
const expandedId = ref<number | null>(null);

function applyFilters(): void {
    const from = dateRange.value?.start?.toString() ?? '';
    const to = dateRange.value?.end?.toString() ?? '';

    router.get(
        auditLogs.index.url(),
        {
            search: search.value || undefined,
            action: action.value || undefined,
            from: from || undefined,
            to: to || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watchDebounced([search, action, dateRange], applyFilters, { debounce: 300 });

function exportLogs(): void {
    window.location.href = auditLogs.export.url({
        query: {
            search: search.value || undefined,
            action: action.value || undefined,
            from: dateRange.value?.start?.toString() || undefined,
            to: dateRange.value?.end?.toString() || undefined,
        },
    });
}

function toggleDetails(id: number): void {
    expandedId.value = expandedId.value === id ? null : id;
}

function subjectLabel(log: AuditEntry): string {
    const subjectType =
        log.subject_type ??
        (typeof log.metadata?.subject_type === 'string'
            ? log.metadata.subject_type.split('\\').pop()
            : null);
    const subjectId =
        log.subject_id ??
        (typeof log.metadata?.subject_id === 'number'
            ? log.metadata.subject_id
            : null);

    return subjectType
        ? subjectType + (subjectId ? ' #' + subjectId : '')
        : 'System';
}

function actionLabel(action: string): string {
    return action
        .replace(/[._]+/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString();
}

function humanizeKey(key: string): string {
    return key
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function formatValue(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    if (Array.isArray(value)) {
        return value.length ? value.join(', ') : 'None';
    }

    if (typeof value === 'object') {
        return Object.entries(value as Record<string, unknown>)
            .map(
                ([key, nestedValue]) =>
                    humanizeKey(key) + ': ' + formatValue(nestedValue),
            )
            .join('; ');
    }

    return String(value);
}

function valueEntries(
    values: Record<string, unknown> | null,
): Array<{ label: string; value: string }> {
    return Object.entries(values ?? {}).map(([key, value]) => ({
        label: humanizeKey(key),
        value: formatValue(value),
    }));
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Audit Logs', href: auditLogs.index.url() }],
    },
});
</script>

<template>
    <Head title="Audit Logs" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
        >
            <Heading
                variant="small"
                title="Audit Logs"
                description="Review security and business activity across the workspace."
            />
            <Button variant="secondary" @click="exportLogs">
                <Download class="size-4" />
                Export CSV
            </Button>
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <div class="relative md:col-span-2">
                <Search
                    class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-9"
                    placeholder="Search actions or descriptions"
                />
            </div>
            <Combobox v-model="action">
                <ComboboxAnchor class="w-full">
                    <ComboboxInput
                        aria-label="Filter by action"
                        :display-value="
                            (value) =>
                                actionLabel(String(value || 'All actions'))
                        "
                        placeholder="Filter by action"
                    />
                </ComboboxAnchor>
                <ComboboxList>
                    <ComboboxEmpty>No matching actions.</ComboboxEmpty>
                    <ComboboxItem value="">All actions</ComboboxItem>
                    <ComboboxItem
                        v-for="item in actions"
                        :key="item"
                        :value="item"
                    >
                        {{ actionLabel(item) }}
                    </ComboboxItem>
                </ComboboxList>
            </Combobox>
            <DateRangePicker v-model="dateRange" class="w-full" />
        </div>

        <div class="overflow-hidden rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead>Action</TableHead>
                        <TableHead>Actor</TableHead>
                        <TableHead>Subject</TableHead>
                        <TableHead>IP Address</TableHead>
                        <TableHead class="text-right">Details</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-for="log in logs.data" :key="log.id">
                        <TableRow>
                            <TableCell class="text-sm whitespace-nowrap">{{
                                formatDate(log.created_at)
                            }}</TableCell>
                            <TableCell>
                                <Badge variant="outline">{{
                                    actionLabel(log.action)
                                }}</Badge>
                                <p
                                    v-if="log.description"
                                    class="mt-1 max-w-xs text-xs text-muted-foreground"
                                >
                                    {{ log.description }}
                                </p>
                            </TableCell>
                            <TableCell>
                                <div class="text-sm">
                                    {{ log.actor?.name ?? 'System' }}
                                </div>
                                <div
                                    v-if="log.actor?.email"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ log.actor.email }}
                                </div>
                            </TableCell>
                            <TableCell class="text-sm">
                                {{ subjectLabel(log) }}
                            </TableCell>
                            <TableCell class="text-sm text-muted-foreground">{{
                                log.ip_address ?? '—'
                            }}</TableCell>
                            <TableCell class="text-right">
                                <TooltipProvider :delay-duration="0">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                :aria-label="
                                                    expandedId === log.id
                                                        ? 'Hide audit details'
                                                        : 'Show audit details'
                                                "
                                                @click="toggleDetails(log.id)"
                                            >
                                                <Minus
                                                    v-if="expandedId === log.id"
                                                    class="size-4"
                                                />
                                                <Plus v-else class="size-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            {{
                                                expandedId === log.id
                                                    ? 'Hide details'
                                                    : 'Show details'
                                            }}
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="expandedId === log.id">
                            <TableCell colspan="6" class="bg-muted/30">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div
                                        class="rounded-lg border bg-background p-3"
                                    >
                                        <div
                                            class="mb-3 flex items-center justify-between"
                                        >
                                            <div
                                                class="text-xs font-semibold text-muted-foreground uppercase"
                                            >
                                                Before
                                            </div>
                                            <Badge variant="secondary"
                                                >Previous values</Badge
                                            >
                                        </div>
                                        <dl
                                            v-if="
                                                valueEntries(log.before_values)
                                                    .length
                                            "
                                            class="divide-y text-sm"
                                        >
                                            <div
                                                v-for="entry in valueEntries(
                                                    log.before_values,
                                                )"
                                                :key="entry.label"
                                                class="flex items-start justify-between gap-4 py-2 first:pt-0 last:pb-0"
                                            >
                                                <dt
                                                    class="text-muted-foreground"
                                                >
                                                    {{ entry.label }}
                                                </dt>
                                                <dd
                                                    class="text-right font-medium text-foreground"
                                                >
                                                    {{ entry.value }}
                                                </dd>
                                            </div>
                                        </dl>
                                        <p
                                            v-else
                                            class="text-sm text-muted-foreground"
                                        >
                                            No previous values recorded.
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-lg border bg-background p-3"
                                    >
                                        <div
                                            class="mb-3 flex items-center justify-between"
                                        >
                                            <div
                                                class="text-xs font-semibold text-muted-foreground uppercase"
                                            >
                                                After
                                            </div>
                                            <Badge variant="secondary"
                                                >New values</Badge
                                            >
                                        </div>
                                        <dl
                                            v-if="
                                                valueEntries(log.after_values)
                                                    .length
                                            "
                                            class="divide-y text-sm"
                                        >
                                            <div
                                                v-for="entry in valueEntries(
                                                    log.after_values,
                                                )"
                                                :key="entry.label"
                                                class="flex items-start justify-between gap-4 py-2 first:pt-0 last:pb-0"
                                            >
                                                <dt
                                                    class="text-muted-foreground"
                                                >
                                                    {{ entry.label }}
                                                </dt>
                                                <dd
                                                    class="text-right font-medium text-foreground"
                                                >
                                                    {{ entry.value }}
                                                </dd>
                                            </div>
                                        </dl>
                                        <p
                                            v-else
                                            class="text-sm text-muted-foreground"
                                        >
                                            No new values recorded.
                                        </p>
                                    </div>
                                </div>
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-if="logs.data.length === 0">
                        <TableCell
                            colspan="6"
                            class="py-12 text-center text-muted-foreground"
                            >No audit records found.</TableCell
                        >
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span v-if="logs.total">
                Showing
                <span class="font-medium text-foreground">{{ logs.from }}</span>
                to
                <span class="font-medium text-foreground">{{ logs.to }}</span>
                of
                <span class="font-medium text-foreground">{{
                    logs.total
                }}</span>
                audit records
            </span>
            <span v-else>No audit records found</span>

            <div v-if="logs.last_page > 1" class="flex flex-wrap gap-1">
                <Button
                    v-for="(link, index) in logs.links"
                    :key="index"
                    variant="outline"
                    size="sm"
                    :disabled="!link.url"
                    @click="
                        link.url &&
                        router.get(
                            link.url,
                            {},
                            { preserveState: true, preserveScroll: true },
                        )
                    "
                >
                    <span v-html="link.label" />
                </Button>
            </div>
        </div>
    </div>
</template>
