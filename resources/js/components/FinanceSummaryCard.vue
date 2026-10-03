<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, TrendingDown, TrendingUp, Wallet } from '@lucide/vue';
import { index as financeIndex } from '@/routes/admin/finance';

const props = defineProps<{
    summary?: {
        total_income: number;
        total_expense: number;
        net_balance: number;
        net_margin?: number;
        remaining_budget?: number;
        burn_rate?: number;
        budget_total?: number;
    } | null;
    class?: string;
}>();

const page = usePage();

const currency = computed(() => {
    return (
        (page.props as any).finance?.currency ?? { symbol: '$', code: 'USD' }
    );
});

const netBalance = computed(() => props.summary?.net_balance ?? 0);
const netMargin = computed(() => {
    if (props.summary?.net_margin !== undefined)
        return props.summary.net_margin;
    const income = props.summary?.total_income ?? 0;
    return income > 0 ? Math.round((netBalance.value / income) * 1000) / 10 : 0;
});
const remainingBudget = computed(() => props.summary?.remaining_budget ?? 0);

function formatNumber(num: number): string {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
}
</script>

<template>
    <div
        :class="[
            'flex flex-col justify-between rounded-xl border border-border/80 bg-card p-6 shadow-xs transition-colors hover:border-border',
            props.class,
        ]"
    >
        <div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-muted-foreground"
                    >Net Cash Flow</span
                >
                <div
                    class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <Wallet class="size-4" />
                </div>
            </div>

            <!-- 1 Main Metric -->
            <div
                class="mt-3 text-3xl font-bold tracking-tight"
                :class="netBalance >= 0 ? 'text-emerald-500' : 'text-rose-500'"
            >
                {{ netBalance >= 0 ? '+' : '' }}{{ currency.symbol
                }}{{ formatNumber(netBalance) }}
            </div>

            <!-- 2 Simple Clean Metrics -->
            <div
                class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
            >
                <span
                    class="inline-flex items-center font-medium whitespace-nowrap"
                    :class="
                        netMargin >= 0 ? 'text-emerald-500' : 'text-rose-500'
                    "
                >
                    <TrendingUp v-if="netMargin >= 0" class="mr-1 size-3.5" />
                    <TrendingDown v-else class="mr-1 size-3.5" />
                    {{ netMargin >= 0 ? '+' : '' }}{{ netMargin }}% margin
                </span>
                <span class="text-muted-foreground/40">•</span>
                <span class="whitespace-nowrap"
                    >{{ currency.symbol
                    }}{{ formatNumber(remainingBudget) }} remaining</span
                >
            </div>
        </div>

        <div class="mt-5 border-t border-border/50 pt-3">
            <Link
                :href="financeIndex()"
                class="group inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
            >
                <span>View Full Tracker</span>
                <ArrowRight
                    class="size-3.5 transition-transform group-hover:translate-x-0.5"
                />
            </Link>
        </div>
    </div>
</template>
