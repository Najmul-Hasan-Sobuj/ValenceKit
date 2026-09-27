<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Check, Coins, DollarSign, Wallet } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, update } from '@/routes/admin/finance/settings';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps<{
    activeCurrency: {
        code: string;
        symbol: string;
        name: string;
        locale: string;
        precision: number;
    };
    monthlyBudget: number;
    currencies: Record<string, {
        code: string;
        symbol: string;
        name: string;
        locale: string;
        precision: number;
    }>;
}>();

const { can } = usePermissions();

const form = useForm({
    currency: props.activeCurrency.code,
    monthly_budget: props.monthlyBudget.toString(),
});

function submit() {
    form.patch(update.url(), {
        preserveScroll: true,
    });
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Finance settings', href: edit() }] },
});
</script>

<template>
    <Head title="Finance settings" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Finance Settings"
            description="Manage your global currency preferences and monthly budget target"
        />

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Currency Selection -->
            <div class="space-y-3">
                <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Active Global Currency
                </Label>
                <p class="text-xs text-muted-foreground">
                    Select the currency used across the sidebar widget, transactions ledger, KPI metrics, and exports.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <button
                        v-for="(curr, code) in currencies"
                        :key="code"
                        type="button"
                        @click="form.currency = code"
                        :class="[
                            'relative flex items-start gap-3.5 rounded-xl border p-4 text-left transition-all',
                            form.currency === code
                                ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                : 'border-border/70 bg-card hover:bg-muted/40',
                        ]"
                    >
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted font-bold text-lg text-primary ring-1 ring-border/50"
                        >
                            {{ curr.symbol }}
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold text-foreground">{{ curr.name }}</p>
                                <div
                                    v-if="form.currency === code"
                                    class="flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
                                >
                                    <Check class="size-3" />
                                </div>
                            </div>
                            <p class="text-xs text-muted-foreground mt-0.5">{{ curr.code }} ({{ curr.symbol }})</p>
                            <p class="text-[11px] font-mono text-muted-foreground/80 mt-1">
                                Sample: {{ curr.symbol }}1,250.00
                            </p>
                        </div>
                    </button>
                </div>
                <InputError :message="form.errors.currency" />
            </div>

            <!-- Monthly Budget Target -->
            <div class="space-y-2 pt-2 border-t border-border/60">
                <Label for="monthly_budget" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Monthly Global Budget Target
                </Label>
                <p class="text-xs text-muted-foreground">
                    Set a monthly expenditure ceiling. The sidebar and dashboard will pace your spending against this target.
                </p>

                <div class="relative flex max-w-sm items-center">
                    <span class="absolute left-3.5 text-base font-bold text-muted-foreground select-none">
                        {{ currencies[form.currency]?.symbol || '$' }}
                    </span>
                    <Input
                        id="monthly_budget"
                        v-model="form.monthly_budget"
                        type="number"
                        step="10"
                        min="0"
                        placeholder="3500.00"
                        required
                        class="pl-9 h-11 text-base font-semibold rounded-xl border-border/70"
                    />
                </div>
                <InputError :message="form.errors.monthly_budget" />
            </div>

            <Button
                type="submit"
                :disabled="form.processing || !can('settings.finance.edit')"
                class="rounded-xl px-5"
            >
                {{ form.processing ? 'Saving...' : 'Save Settings' }}
            </Button>
        </form>
    </div>
</template>
