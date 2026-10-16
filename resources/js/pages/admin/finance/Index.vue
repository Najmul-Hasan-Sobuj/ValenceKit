<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { watchDebounced } from '@vueuse/core';
import { decodePaginationLabel } from '@/utils/decodePaginationLabel';
import {
    Activity,
    ArrowDownLeft,
    ArrowUpRight,
    Download,
    FileSpreadsheet,
    MoreHorizontal,
    Pencil,
    PieChart,
    Plus,
    Search,
    Trash2,
    Wallet,
    X,
} from '@lucide/vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { parseDate } from '@internationalized/date';
import type { DateRange } from '@/types';
import {
    index as financeIndex,
    bulkDestroy as financeBulkDestroy,
    exportMethod as financeExport,
    destroy as transactionDestroy,
} from '@/actions/App/Http/Controllers/Admin/FinanceController';
import TransactionDrawer from './TransactionDrawer.vue';
import { usePermissions } from '@/composables/usePermissions';

// Chart.js imports
import { Bar, Doughnut } from 'vue-chartjs';
import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Title as ChartTitle,
    Tooltip as ChartTooltip,
} from 'chart.js';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    ArcElement,
    ChartTitle,
    ChartTooltip,
    Legend,
);

const props = defineProps<{
    transactions: {
        data: Array<{
            id: number;
            type: 'income' | 'expense';
            amount: string;
            currency: string;
            category_key: string;
            category_name: string;
            description: string;
            reference: string | null;
            transaction_date: string;
            payment_method: string;
            is_recurring: boolean;
            notes: string | null;
            admin: {
                id: number;
                name: string;
                email: string;
            };
        }>;
        links: any[];
        from?: number;
        to?: number;
        total?: number;
    };
    monthlySummary: {
        total_income: number;
        total_expense: number;
        net_balance: number;
        budget_total: number;
        budget_spent_percentage: number;
        remaining_budget: number;
        burn_rate: number;
        days_passed: number;
        days_remaining: number;
    };
    periodMetrics: {
        income: number;
        expense: number;
        net: number;
        count: number;
    };
    timelineChart: {
        labels: string[];
        income: number[];
        expense: number[];
        interval: string;
    };
    categoryChart: {
        labels: string[];
        data: number[];
        colors: string[];
        icons: string[];
    };
    categories: {
        income: Array<{
            id: string;
            name: string;
            icon: string;
            color: string;
        }>;
        expense: Array<{
            id: string;
            name: string;
            icon: string;
            color: string;
        }>;
    };
    paymentMethods: Record<string, string>;
    currency: {
        code: string;
        symbol: string;
        name: string;
    };
    filters: {
        search?: string;
        type?: string;
        category?: string;
        payment_method?: string;
        from?: string;
        to?: string;
    };
}>();

const { can } = usePermissions();

// Filters state
const search = ref(props.filters?.search ?? '');

const allCategoriesList = computed(() => {
    return [
        ...(props.categories?.income ?? []),
        ...(props.categories?.expense ?? []),
    ];
});

const selectedType = ref(
    props.filters?.type === 'income'
        ? 'Income Only'
        : props.filters?.type === 'expense'
          ? 'Expense Only'
          : 'All Types',
);

const selectedCategory = ref(
    props.filters?.category
        ? (allCategoriesList.value.find((c) => c.id === props.filters.category)
              ?.name ?? 'All Categories')
        : 'All Categories',
);

const selectedPaymentMethod = ref(props.filters?.payment_method ?? '');

function initDateRange(): DateRange | undefined {
    if (!props.filters?.from) return undefined;
    try {
        const start = parseDate(props.filters.from);
        const end = props.filters.to ? parseDate(props.filters.to) : start;
        return { start, end };
    } catch {
        return undefined;
    }
}

const dateRange = ref<DateRange | undefined>(initDateRange());

// Debounced filtering
watchDebounced(
    [search, selectedType, selectedCategory, selectedPaymentMethod, dateRange],
    () => {
        if (dateRange.value?.start && !dateRange.value?.end) {
            return;
        }

        const fromStr = dateRange.value?.start
            ? dateRange.value.start.toString()
            : '';
        const toStr = dateRange.value?.end
            ? dateRange.value.end.toString()
            : '';

        const typeParam =
            selectedType.value === 'Income Only'
                ? 'income'
                : selectedType.value === 'Expense Only'
                  ? 'expense'
                  : '';

        const catParam =
            selectedCategory.value === 'All Categories'
                ? ''
                : (allCategoriesList.value.find(
                      (c) => c.name === selectedCategory.value,
                  )?.id ?? '');

        router.get(
            financeIndex.url(),
            {
                search: search.value,
                type: typeParam,
                category: catParam,
                payment_method: selectedPaymentMethod.value,
                from: fromStr,
                to: toStr,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    },
    { debounce: 300 },
);

// Drawer state
const drawerOpen = ref(false);
const editingTransaction = ref<any | null>(null);

function openCreateDrawer() {
    editingTransaction.value = null;
    drawerOpen.value = true;
}

function openEditDrawer(tx: any) {
    editingTransaction.value = tx;
    drawerOpen.value = true;
}

// Single delete
const deleteDialogOpen = ref(false);
const transactionToDelete = ref<any | null>(null);
const isDeleting = ref(false);

function promptDelete(tx: any) {
    transactionToDelete.value = tx;
    deleteDialogOpen.value = true;
}

function confirmDelete() {
    if (transactionToDelete.value && !isDeleting.value) {
        isDeleting.value = true;
        router.delete(transactionDestroy.url(transactionToDelete.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                deleteDialogOpen.value = false;
                transactionToDelete.value = null;
            },
            onFinish: () => {
                isDeleting.value = false;
            },
        });
    }
}

// Bulk Selection
const selectedIds = ref<number[]>([]);
const isAllSelected = computed(() => {
    return (
        props.transactions.data.length > 0 &&
        props.transactions.data.every((tx) => selectedIds.value.includes(tx.id))
    );
});

function toggleSelectAll() {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.transactions.data.map((tx) => tx.id);
    }
}

function toggleSelectRow(id: number) {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((i) => i !== id);
    } else {
        selectedIds.value.push(id);
    }
}

// Bulk Delete
const bulkDeleteDialogOpen = ref(false);
const isBulkDeleting = ref(false);

function confirmBulkDelete() {
    if (selectedIds.value.length === 0 || isBulkDeleting.value) return;

    isBulkDeleting.value = true;
    router.post(
        financeBulkDestroy.url(),
        { ids: selectedIds.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
                bulkDeleteDialogOpen.value = false;
            },
            onFinish: () => {
                isBulkDeleting.value = false;
            },
        },
    );
}

// Export handler
function handleExport(idsOnly = false) {
    const params = new URLSearchParams();
    if (idsOnly && selectedIds.value.length > 0) {
        params.set('ids', selectedIds.value.join(','));
    } else {
        const typeParam =
            selectedType.value === 'Income Only'
                ? 'income'
                : selectedType.value === 'Expense Only'
                  ? 'expense'
                  : '';
        const catParam =
            selectedCategory.value === 'All Categories'
                ? ''
                : (allCategoriesList.value.find(
                      (c) => c.name === selectedCategory.value,
                  )?.id ?? '');
        if (search.value) params.set('search', search.value);
        if (typeParam) params.set('type', typeParam);
        if (catParam) params.set('category', catParam);
        if (selectedPaymentMethod.value)
            params.set('payment_method', selectedPaymentMethod.value);
        if (dateRange.value?.start)
            params.set('from', dateRange.value.start.toString());
        if (dateRange.value?.end)
            params.set('to', dateRange.value.end.toString());
    }

    window.location.href = `${financeExport.url()}?${params.toString()}`;
}

// Currency formatting helper
function formatCurrency(amount: number | string): string {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
}

// Charts options
const timelineChartData = computed(() => {
    return {
        labels: props.timelineChart.labels,
        datasets: [
            {
                label: 'Income',
                backgroundColor: '#10b981',
                borderRadius: 6,
                data: props.timelineChart.income,
            },
            {
                label: 'Expense',
                backgroundColor: '#ef4444',
                borderRadius: 6,
                data: props.timelineChart.expense,
            },
        ],
    };
});

const timelineChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top' as const,
            labels: {
                boxWidth: 12,
                font: { size: 12 },
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
        },
        y: {
            grid: { color: 'rgba(156, 163, 175, 0.1)' },
            ticks: {
                callback: (val: any) => `${props.currency.symbol}${val}`,
            },
        },
    },
};

const categoryChartData = computed(() => {
    return {
        labels: props.categoryChart.labels,
        datasets: [
            {
                data: props.categoryChart.data,
                backgroundColor: props.categoryChart.colors,
                borderWidth: 2,
                borderColor: 'transparent',
            },
        ],
    };
});

const categoryChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'right' as const,
            labels: {
                boxWidth: 10,
                font: { size: 11 },
            },
        },
    },
    cutout: '70%',
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Finance Tracker', href: financeIndex.url() }],
    },
});
</script>

<template>
    <Head title="Finance Tracker" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <!-- Page Header -->
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
        >
            <Heading
                variant="small"
                title="Finance Tracker"
                description="Monitor cash flow, analyze expense distributions, and pace budgets across your workspace"
            />
            <div class="flex items-center gap-2">
                <Button
                    v-if="can('finance.export')"
                    variant="outline"
                    class="rounded-xl border-border/80"
                    @click="handleExport(false)"
                >
                    <FileSpreadsheet class="mr-1.5 size-4 text-emerald-600" />
                    Export CSV
                </Button>
                <Button
                    v-if="can('finance.create')"
                    class="rounded-xl shadow-xs"
                    @click="openCreateDrawer"
                >
                    <Plus class="mr-1.5 size-4" />
                    Add Transaction
                </Button>
            </div>
        </div>

        <!-- 4 Executive KPI Metric Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Period Income -->
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >Period Income</span
                    >
                    <div
                        class="flex size-7 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-500"
                    >
                        <ArrowUpRight class="size-4" />
                    </div>
                </div>
                <div
                    class="mt-2 text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400"
                >
                    +{{ currency.symbol
                    }}{{ formatCurrency(periodMetrics.income) }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    In selected date range
                </p>
            </div>

            <!-- Period Expense -->
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >Period Expenses</span
                    >
                    <div
                        class="flex size-7 items-center justify-center rounded-lg bg-rose-500/10 text-rose-500"
                    >
                        <ArrowDownLeft class="size-4" />
                    </div>
                </div>
                <div
                    class="mt-2 text-2xl font-bold tracking-tight text-rose-600 dark:text-rose-400"
                >
                    -{{ currency.symbol
                    }}{{ formatCurrency(periodMetrics.expense) }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    In selected date range
                </p>
            </div>

            <!-- Period Net Cash Flow -->
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >Period Net Cashflow</span
                    >
                    <div
                        class="flex size-7 items-center justify-center rounded-lg"
                        :class="
                            periodMetrics.net >= 0
                                ? 'bg-emerald-500/10 text-emerald-500'
                                : 'bg-rose-500/10 text-rose-500'
                        "
                    >
                        <Wallet class="size-4" />
                    </div>
                </div>
                <div
                    class="mt-2 text-2xl font-bold tracking-tight"
                    :class="
                        periodMetrics.net >= 0
                            ? 'text-foreground'
                            : 'text-rose-500'
                    "
                >
                    {{ periodMetrics.net >= 0 ? '+' : '' }}{{ currency.symbol
                    }}{{ formatCurrency(periodMetrics.net) }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    Net operating margin
                </p>
            </div>

            <!-- Monthly Budget Health -->
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >Monthly Global Budget Target</span
                    >
                    <div
                        class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Activity class="size-4" />
                    </div>
                </div>
                <div class="mt-2 flex items-baseline justify-between">
                    <span
                        class="text-2xl font-bold tracking-tight text-foreground"
                    >
                        {{ monthlySummary.budget_spent_percentage }}%
                    </span>
                    <span class="text-xs font-medium text-muted-foreground">
                        {{ currency.symbol
                        }}{{ formatCurrency(monthlySummary.total_expense) }} /
                        {{ currency.symbol
                        }}{{ formatCurrency(monthlySummary.budget_total) }}
                    </span>
                </div>
                <div
                    class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-muted"
                >
                    <div
                        class="h-full rounded-full transition-all duration-500"
                        :class="[
                            monthlySummary.budget_spent_percentage > 100
                                ? 'bg-rose-500'
                                : monthlySummary.budget_spent_percentage > 80
                                  ? 'bg-amber-500'
                                  : 'bg-emerald-500',
                        ]"
                        :style="{
                            width: `${Math.min(monthlySummary.budget_spent_percentage, 100)}%`,
                        }"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Cash Flow Timeline Bar Chart (2 cols) -->
            <div class="rounded-xl border bg-card p-5 shadow-xs lg:col-span-2">
                <div
                    class="flex items-center justify-between border-b border-border/50 pb-3"
                >
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">
                            Cash Flow Timeline
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Income vs Expenses over the selected period
                        </p>
                    </div>
                    <Badge variant="outline" class="text-xs capitalize">
                        By {{ timelineChart.interval }}
                    </Badge>
                </div>
                <div class="mt-4 h-64 w-full">
                    <Bar
                        v-if="timelineChart.labels.length > 0"
                        :data="timelineChartData"
                        :options="timelineChartOptions"
                    />
                    <div
                        v-else
                        class="flex h-full items-center justify-center text-xs text-muted-foreground"
                    >
                        No transactions recorded for this period.
                    </div>
                </div>
            </div>

            <!-- Expense Category Breakdown Donut Chart (1 col) -->
            <div class="rounded-xl border bg-card p-5 shadow-xs">
                <div
                    class="flex items-center justify-between border-b border-border/50 pb-3"
                >
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">
                            Expense Distribution
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Spending share by category
                        </p>
                    </div>
                    <PieChart class="size-4 text-muted-foreground" />
                </div>
                <div class="mt-4 h-64 w-full">
                    <Doughnut
                        v-if="categoryChart.data.length > 0"
                        :data="categoryChartData"
                        :options="categoryChartOptions"
                    />
                    <div
                        v-else
                        class="flex h-full items-center justify-center text-xs text-muted-foreground"
                    >
                        No expense records in this timeframe.
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Toolbar -->
        <div
            class="flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-xs"
        >
            <div
                class="grid gap-3 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-12"
            >
                <!-- Search Input -->
                <div class="relative flex items-center lg:col-span-4">
                    <Search
                        class="absolute left-3 size-4 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        placeholder="Search description, reference, category..."
                        class="rounded-xl border-border/70 pl-9"
                    />
                </div>

                <!-- Type Combobox -->
                <div class="lg:col-span-2">
                    <Combobox v-model="selectedType">
                        <ComboboxAnchor class="w-full">
                            <ComboboxInput
                                aria-label="Filter by type"
                                :display-value="
                                    (value) => String(value || 'All Types')
                                "
                                placeholder="All Types"
                                class="h-9 w-full rounded-xl border-border/70 text-sm"
                            />
                        </ComboboxAnchor>
                        <ComboboxList class="z-[100]">
                            <ComboboxEmpty
                                class="p-2 text-xs text-muted-foreground"
                                >No types found.</ComboboxEmpty
                            >
                            <ComboboxItem
                                value="All Types"
                                class="px-2 py-1.5 text-xs"
                            >
                                All Types
                            </ComboboxItem>
                            <ComboboxItem
                                value="Income Only"
                                class="px-2 py-1.5 text-xs"
                            >
                                Income Only
                            </ComboboxItem>
                            <ComboboxItem
                                value="Expense Only"
                                class="px-2 py-1.5 text-xs"
                            >
                                Expense Only
                            </ComboboxItem>
                        </ComboboxList>
                    </Combobox>
                </div>

                <!-- Category Combobox -->
                <div class="lg:col-span-3">
                    <Combobox v-model="selectedCategory">
                        <ComboboxAnchor class="w-full">
                            <ComboboxInput
                                aria-label="Filter by category"
                                :display-value="
                                    (value) => String(value || 'All Categories')
                                "
                                placeholder="All Categories"
                                class="h-9 w-full rounded-xl border-border/70 text-sm"
                            />
                        </ComboboxAnchor>
                        <ComboboxList class="z-[100] max-h-60 overflow-y-auto">
                            <ComboboxEmpty
                                class="p-2 text-xs text-muted-foreground"
                                >No categories found.</ComboboxEmpty
                            >
                            <ComboboxItem
                                value="All Categories"
                                class="px-2 py-1.5 text-xs"
                            >
                                All Categories
                            </ComboboxItem>
                            <div
                                class="px-2 py-1 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Income Categories
                            </div>
                            <ComboboxItem
                                v-for="cat in categories.income"
                                :key="cat.id"
                                :value="cat.name"
                                class="flex cursor-pointer items-center gap-2 px-2 py-1.5 text-xs"
                            >
                                <span
                                    class="size-2 rounded-full"
                                    :style="{ backgroundColor: cat.color }"
                                />
                                {{ cat.name }}
                            </ComboboxItem>
                            <div
                                class="mt-1 border-t border-border/40 px-2 py-1 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Expense Categories
                            </div>
                            <ComboboxItem
                                v-for="cat in categories.expense"
                                :key="cat.id"
                                :value="cat.name"
                                class="flex cursor-pointer items-center gap-2 px-2 py-1.5 text-xs"
                            >
                                <span
                                    class="size-2 rounded-full"
                                    :style="{ backgroundColor: cat.color }"
                                />
                                {{ cat.name }}
                            </ComboboxItem>
                        </ComboboxList>
                    </Combobox>
                </div>

                <!-- Date Range Picker Component -->
                <div class="lg:col-span-3">
                    <DateRangePicker v-model="dateRange" class="w-full" />
                </div>
            </div>
        </div>

        <!-- Sticky Bulk Activity Toolbar (appears when rows are selected) -->
        <div
            v-if="selectedIds.length > 0"
            class="flex items-center justify-between rounded-xl border border-primary/40 bg-primary/5 px-4 py-2.5 shadow-sm transition-all"
        >
            <div class="flex items-center gap-2">
                <Badge
                    variant="default"
                    class="rounded-md px-2 py-0.5 text-xs font-semibold"
                >
                    {{ selectedIds.length }} selected
                </Badge>
                <span class="text-xs font-medium text-muted-foreground"
                    >Bulk activity on ledger items</span
                >
            </div>
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 rounded-lg text-xs"
                    @click="handleExport(true)"
                >
                    <Download class="mr-1 size-3.5" />
                    Export Selected
                </Button>
                <Button
                    v-if="can('finance.delete')"
                    variant="destructive"
                    size="sm"
                    class="h-8 rounded-lg text-xs"
                    @click="bulkDeleteDialogOpen = true"
                >
                    <Trash2 class="mr-1 size-3.5" />
                    Delete Selected
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-8 px-2 text-xs text-muted-foreground hover:text-foreground"
                    @click="selectedIds = []"
                >
                    <X class="size-3.5" />
                </Button>
            </div>
        </div>

        <!-- Transactions Ledger Table -->
        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <Table>
                <TableHeader>
                    <TableRow class="bg-muted/40">
                        <TableHead class="w-10">
                            <Checkbox
                                :model-value="
                                    isAllSelected
                                        ? true
                                        : selectedIds.length > 0
                                          ? 'indeterminate'
                                          : false
                                "
                                @update:model-value="toggleSelectAll"
                                aria-label="Select all transactions"
                            />
                        </TableHead>
                        <TableHead class="w-28">Date</TableHead>
                        <TableHead class="w-24">Type</TableHead>
                        <TableHead class="w-48">Category</TableHead>
                        <TableHead>Description</TableHead>
                        <TableHead class="w-32">Method</TableHead>
                        <TableHead class="w-32 text-right">Amount</TableHead>
                        <TableHead class="w-14 text-center"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="transactions.data.length > 0">
                        <TableRow
                            v-for="tx in transactions.data"
                            :key="tx.id"
                            :class="[
                                'transition-colors',
                                selectedIds.includes(tx.id)
                                    ? 'bg-primary/5'
                                    : '',
                            ]"
                        >
                            <!-- Row Checkbox -->
                            <TableCell>
                                <Checkbox
                                    :model-value="selectedIds.includes(tx.id)"
                                    @update:model-value="toggleSelectRow(tx.id)"
                                    :aria-label="`Select transaction ${tx.id}`"
                                />
                            </TableCell>

                            <!-- Date -->
                            <TableCell
                                class="font-mono text-xs text-muted-foreground"
                            >
                                {{ tx.transaction_date.split('T')[0] }}
                            </TableCell>

                            <!-- Type Badge -->
                            <TableCell>
                                <Badge
                                    :variant="
                                        tx.type === 'income'
                                            ? 'default'
                                            : 'secondary'
                                    "
                                    :class="[
                                        'gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold capitalize',
                                        tx.type === 'income'
                                            ? 'border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                            : 'border border-rose-500/20 bg-rose-500/10 text-rose-600 dark:text-rose-400',
                                    ]"
                                >
                                    <ArrowUpRight
                                        v-if="tx.type === 'income'"
                                        class="size-3"
                                    />
                                    <ArrowDownLeft v-else class="size-3" />
                                    {{ tx.type }}
                                </Badge>
                            </TableCell>

                            <!-- Category -->
                            <TableCell>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-xs font-medium text-foreground"
                                        >{{ tx.category_name }}</span
                                    >
                                </div>
                            </TableCell>

                            <!-- Description -->
                            <TableCell>
                                <div class="flex flex-col">
                                    <span
                                        class="text-xs font-medium text-foreground"
                                        >{{ tx.description }}</span
                                    >
                                    <span
                                        v-if="tx.reference"
                                        class="font-mono text-[11px] text-muted-foreground"
                                    >
                                        Ref: {{ tx.reference }}
                                    </span>
                                </div>
                            </TableCell>

                            <!-- Payment Method -->
                            <TableCell>
                                <span
                                    class="text-xs text-muted-foreground capitalize"
                                >
                                    {{
                                        paymentMethods[tx.payment_method] ??
                                        tx.payment_method.replace(/_/g, ' ')
                                    }}
                                </span>
                            </TableCell>

                            <!-- Amount with Symbol -->
                            <TableCell
                                class="text-right font-mono text-sm font-bold"
                            >
                                <span
                                    :class="
                                        tx.type === 'income'
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-rose-600 dark:text-rose-400'
                                    "
                                >
                                    {{ tx.type === 'income' ? '+' : '-'
                                    }}{{ currency.symbol
                                    }}{{ formatCurrency(tx.amount) }}
                                </span>
                            </TableCell>

                            <!-- Row Actions Menu -->
                            <TableCell class="text-center">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 rounded-lg"
                                        >
                                            <MoreHorizontal class="size-4" />
                                            <span class="sr-only">Actions</span>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        class="w-36 rounded-xl"
                                    >
                                        <DropdownMenuItem
                                            v-if="can('finance.edit')"
                                            class="cursor-pointer text-xs"
                                            @click="openEditDrawer(tx)"
                                        >
                                            <Pencil
                                                class="mr-2 size-3.5 text-muted-foreground"
                                            />
                                            Edit
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator
                                            v-if="
                                                can('finance.edit') &&
                                                can('finance.delete')
                                            "
                                        />
                                        <DropdownMenuItem
                                            v-if="can('finance.delete')"
                                            class="cursor-pointer text-xs text-destructive focus:text-destructive"
                                            @click="promptDelete(tx)"
                                        >
                                            <Trash2 class="mr-2 size-3.5" />
                                            Delete
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else>
                        <TableCell
                            colspan="8"
                            class="h-32 text-center text-xs text-muted-foreground"
                        >
                            No transactions found matching your criteria.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination -->
        <div
            class="flex flex-col justify-between gap-3 text-sm sm:flex-row sm:items-center"
        >
            <p class="text-xs text-muted-foreground">
                Showing {{ transactions.from || 0 }} to
                {{ transactions.to || 0 }} of
                {{ transactions.total || 0 }} transactions
            </p>
            <div
                v-if="transactions.links?.length >= 3"
                class="flex items-center gap-1"
            >
                <Button
                    v-for="link in transactions.links"
                    :key="link.label"
                    variant="outline"
                    size="sm"
                    :disabled="!link.url"
                    :class="[
                        'h-8 rounded-lg px-3 text-xs',
                        link.active
                            ? 'border-primary/20 bg-primary/10 text-primary'
                            : '',
                    ]"
                    @click="
                        link.url
                            ? router.visit(link.url, {
                                  preserveScroll: true,
                                  preserveState: true,
                              })
                            : null
                    "
                >
                    <span>{{ decodePaginationLabel(link.label) }}</span>
                </Button>
            </div>
        </div>

        <!-- Transaction Drawer (Slide-over) -->
        <TransactionDrawer
            v-model:open="drawerOpen"
            :transaction="editingTransaction"
            :categories="categories"
            :payment-methods="paymentMethods"
            @saved="
                () =>
                    router.reload({
                        only: [
                            'transactions',
                            'monthlySummary',
                            'periodMetrics',
                            'timelineChart',
                            'categoryChart',
                        ],
                    })
            "
        />

        <!-- Single Delete Confirmation Dialog -->
        <AlertDialog v-model:open="deleteDialogOpen">
            <AlertDialogContent class="sm:max-w-md">
                <AlertDialogHeader>
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive"
                        >
                            <Trash2 class="size-5" />
                        </div>
                        <div>
                            <AlertDialogTitle
                                >Delete Transaction?</AlertDialogTitle
                            >
                            <AlertDialogDescription class="mt-1">
                                Are you sure you want to delete
                                <span class="font-medium text-foreground">{{
                                    transactionToDelete?.description
                                }}</span>
                                <span
                                    v-if="transactionToDelete"
                                    class="font-semibold text-foreground"
                                >
                                    ({{ currency.symbol
                                    }}{{
                                        formatCurrency(
                                            transactionToDelete.amount,
                                        )
                                    }})</span
                                >?
                            </AlertDialogDescription>
                        </div>
                    </div>
                </AlertDialogHeader>
                <div class="text-sm text-muted-foreground">
                    This will permanently remove this transaction from the
                    financial ledger and recalculate monthly totals. This action
                    cannot be undone.
                </div>
                <AlertDialogFooter class="gap-2 sm:gap-0">
                    <AlertDialogCancel :disabled="isDeleting"
                        >Cancel</AlertDialogCancel
                    >
                    <AlertDialogAction
                        :class="buttonVariants({ variant: 'destructive' })"
                        :disabled="isDeleting"
                        @click="confirmDelete"
                    >
                        <Trash2 class="size-4" />
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Bulk Delete Confirmation Dialog -->
        <AlertDialog v-model:open="bulkDeleteDialogOpen">
            <AlertDialogContent class="sm:max-w-md">
                <AlertDialogHeader>
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive"
                        >
                            <Trash2 class="size-5" />
                        </div>
                        <div>
                            <AlertDialogTitle
                                >Delete Selected Transactions?</AlertDialogTitle
                            >
                            <AlertDialogDescription class="mt-1">
                                Are you sure you want to delete
                                <span class="font-semibold text-foreground"
                                    >{{ selectedIds.length }} selected
                                    transactions</span
                                >?
                            </AlertDialogDescription>
                        </div>
                    </div>
                </AlertDialogHeader>
                <div class="text-sm text-muted-foreground">
                    This will permanently remove all selected transactions from
                    the ledger and recalculate all reports and analytics. This
                    action cannot be undone.
                </div>
                <AlertDialogFooter class="gap-2 sm:gap-0">
                    <AlertDialogCancel :disabled="isBulkDeleting"
                        >Cancel</AlertDialogCancel
                    >
                    <AlertDialogAction
                        :class="buttonVariants({ variant: 'destructive' })"
                        :disabled="isBulkDeleting"
                        @click="confirmBulkDelete"
                    >
                        <Trash2 class="size-4" />
                        {{ isBulkDeleting ? 'Deleting...' : 'Delete Selected' }}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
