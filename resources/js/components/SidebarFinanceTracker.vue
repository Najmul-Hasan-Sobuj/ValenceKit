<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowUpRight,
    ChevronRight,
    Plus,
    TrendingDown,
    TrendingUp,
    Wallet,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    HoverCard,
    HoverCardContent,
    HoverCardTrigger,
} from '@/components/ui/hover-card';
import { SidebarMenuAction } from '@/components/ui/sidebar';
import TransactionDrawer from '@/pages/admin/finance/TransactionDrawer.vue';
import {
    index as indexFinance,
    summary as getSummary,
} from '@/routes/admin/finance';

const page = usePage();

const drawerOpen = ref(false);
const summaryData = ref<any>(null);
const isLoading = ref(true);

const currency = computed(() => {
    return (
        (page.props as any).finance?.currency ?? { symbol: '$', code: 'USD' }
    );
});

const categories = computed(
    () =>
        (page.props as any).finance?.categories ?? { income: [], expense: [] },
);
const paymentMethods = computed(
    () => (page.props as any).finance?.payment_methods ?? {},
);

async function fetchSummary() {
    try {
        const response = await fetch(getSummary.url());
        if (response.ok) {
            summaryData.value = await response.json();
        }
    } catch {
        // Fallback gracefully
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    fetchSummary();
    router.on('finish', () => {
        fetchSummary();
    });
});

const netBalance = computed(() => summaryData.value?.net_balance ?? 0);
const totalIncome = computed(() => summaryData.value?.total_income ?? 0);
const totalExpense = computed(() => summaryData.value?.total_expense ?? 0);
const budgetPercentage = computed(
    () => summaryData.value?.budget_spent_percentage ?? 0,
);

function formatNumber(num: number): string {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(num);
}
</script>

<template>
    <HoverCard
        :open-delay="100"
        :close-delay="250"
        @update:open="
            (isOpen) => {
                if (isOpen) fetchSummary();
            }
        "
    >
        <HoverCardTrigger as-child>
            <SidebarMenuAction
                title="This Month Overview"
                class="text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-foreground"
            >
                <TrendingUp
                    v-if="netBalance >= 0"
                    class="size-3.5 text-emerald-500 transition-colors"
                />
                <TrendingDown
                    v-else
                    class="size-3.5 text-rose-500 transition-colors"
                />
                <span class="sr-only">This Month Overview</span>
            </SidebarMenuAction>
        </HoverCardTrigger>

        <HoverCardContent
            side="right"
            align="start"
            :side-offset="12"
            class="w-76 rounded-xl border border-sidebar-border/80 bg-sidebar/95 p-4 text-sidebar-foreground shadow-2xl backdrop-blur-md"
        >
            <div class="space-y-3.5">
                <!-- Header: Title + Quick Log Button -->
                <div
                    class="flex items-center justify-between border-b border-sidebar-border/70 pb-2.5"
                >
                    <div class="flex items-center gap-2">
                        <div
                            class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Wallet class="size-4" />
                        </div>
                        <div>
                            <h4
                                class="text-xs leading-none font-semibold text-sidebar-foreground"
                            >
                                This Month
                            </h4>
                            <p class="mt-0.5 text-[10px] text-muted-foreground">
                                Live Cash Flow
                            </p>
                        </div>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-6 gap-1 rounded-md border-primary/30 px-2 text-[11px] font-medium text-primary shadow-2xs hover:border-primary/50 hover:bg-primary/10"
                        @click="drawerOpen = true"
                    >
                        <Plus class="size-3" />
                        Log
                    </Button>
                </div>

                <!-- Net Flow Stat Box -->
                <div
                    class="rounded-lg border border-sidebar-border/40 bg-sidebar-accent/50 p-2.5"
                >
                    <div class="flex items-baseline justify-between">
                        <span
                            class="text-[11px] font-medium tracking-wider text-muted-foreground uppercase"
                            >Net Flow</span
                        >
                        <span
                            :class="[
                                'text-base font-bold tracking-tight',
                                netBalance >= 0
                                    ? 'text-emerald-500'
                                    : 'text-rose-500',
                            ]"
                        >
                            {{ netBalance >= 0 ? '+' : '' }}{{ currency.symbol
                            }}{{ formatNumber(netBalance) }}
                        </span>
                    </div>

                    <!-- Mini Income & Expense breakdown -->
                    <div
                        class="mt-2.5 grid grid-cols-2 gap-2 border-t border-sidebar-border/60 pt-2 text-[11px]"
                    >
                        <div class="flex items-center gap-1.5 text-emerald-500">
                            <ArrowUpRight class="size-3.5 shrink-0" />
                            <span class="truncate font-semibold"
                                >+{{ currency.symbol
                                }}{{ formatNumber(totalIncome) }}</span
                            >
                        </div>
                        <div
                            class="flex items-center justify-end gap-1.5 text-rose-500"
                        >
                            <ArrowDownLeft class="size-3.5 shrink-0" />
                            <span class="truncate font-semibold"
                                >-{{ currency.symbol
                                }}{{ formatNumber(totalExpense) }}</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Budget Progress Bar -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between text-[11px]">
                        <span class="text-muted-foreground"
                            >Monthly Global Budget Target</span
                        >
                        <span
                            class="font-semibold"
                            :class="
                                budgetPercentage > 100
                                    ? 'text-rose-500'
                                    : budgetPercentage > 80
                                      ? 'text-amber-500'
                                      : 'text-sidebar-foreground'
                            "
                        >
                            {{ budgetPercentage }}%
                        </span>
                    </div>
                    <div
                        class="h-1.5 w-full overflow-hidden rounded-full bg-sidebar-border/80"
                    >
                        <div
                            class="h-full rounded-full transition-all duration-500"
                            :class="[
                                budgetPercentage > 100
                                    ? 'bg-rose-500'
                                    : budgetPercentage > 80
                                      ? 'bg-amber-500'
                                      : 'bg-emerald-500',
                            ]"
                            :style="{
                                width: `${Math.min(budgetPercentage, 100)}%`,
                            }"
                        ></div>
                    </div>
                </div>

                <!-- Footer: Direct Link to Finance Tracker -->
                <div class="border-t border-sidebar-border/60 pt-1">
                    <Link
                        :href="indexFinance()"
                        class="flex items-center justify-between py-0.5 text-xs text-muted-foreground transition-colors hover:text-primary"
                    >
                        <span>Open Full Tracker</span>
                        <ChevronRight class="size-3.5" />
                    </Link>
                </div>
            </div>
        </HoverCardContent>
    </HoverCard>

    <!-- Slide-over Transaction Drawer -->
    <TransactionDrawer
        v-model:open="drawerOpen"
        :transaction="null"
        :categories="categories"
        :payment-methods="paymentMethods"
        @saved="fetchSummary"
    />
</template>
