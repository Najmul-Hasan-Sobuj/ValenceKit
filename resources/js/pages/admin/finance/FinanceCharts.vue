<script setup lang="ts">
import { computed } from 'vue';
import { PieChart } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
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
    timelineChart: {
        labels: string[];
        income: number[];
        expense: number[];
        interval: string;
    };
    categoryChart: { labels: string[]; data: number[]; colors: string[] };
    currencySymbol: string;
}>();

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

const timelineChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top' as const,
            labels: {
                boxWidth: 12,
                font: { size: 12 },
                color: '#9ca3af',
            },
        },
        tooltip: {
            callbacks: {
                label: (context: any) => {
                    const label = context.dataset.label || '';
                    const value = Number(context.raw ?? 0);
                    return ` ${label}: ${props.currencySymbol}${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                },
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { color: '#9ca3af', font: { size: 11 } },
        },
        y: {
            grid: { color: 'rgba(156, 163, 175, 0.1)' },
            ticks: {
                color: '#9ca3af',
                font: { size: 11 },
                callback: (val: any) => `${props.currencySymbol}${val}`,
            },
        },
    },
}));

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

const categoryChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'right' as const,
            labels: {
                boxWidth: 10,
                font: { size: 11 },
                color: '#9ca3af',
            },
        },
        tooltip: {
            callbacks: {
                label: (context: any) => {
                    const label = context.label || '';
                    const value = Number(context.raw ?? 0);
                    const total = (context.dataset.data as number[]).reduce(
                        (a, b) => a + Number(b),
                        0,
                    );
                    const pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0';
                    return ` ${label}: ${props.currencySymbol}${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} (${pct}%)`;
                },
            },
        },
    },
    cutout: '70%',
}));
</script>

<template>
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
</template>
