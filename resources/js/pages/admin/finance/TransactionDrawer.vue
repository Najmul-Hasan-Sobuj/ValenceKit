<script setup lang="ts">
import { computed, watch } from 'vue';
import type { Component } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowUpRight,
    Briefcase,
    Building2,
    Calendar,
    CircleDollarSign,
    Code,
    CreditCard,
    FileText,
    Hash,
    Home,
    Laptop,
    Megaphone,
    Receipt,
    Repeat,
    RotateCcw,
    Server,
    ShoppingBag,
    Tag,
    TrendingUp,
    Users,
    Utensils,
    Wallet,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import { Checkbox } from '@/components/ui/checkbox';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { store, update } from '@/actions/App/Http/Controllers/Admin/FinanceController';
import { toast } from 'vue-sonner';

const props = defineProps<{
    open: boolean;
    transaction: any | null; // null for create
    categories: {
        income: Array<{ id: string; name: string; icon: string; color: string }>;
        expense: Array<{ id: string; name: string; icon: string; color: string }>;
    };
    paymentMethods: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const page = usePage();
const currency = computed(() => (page.props as any).finance?.currency ?? { symbol: '$', code: 'USD' });

function generateReference(): string {
    const today = new Date().toISOString().slice(0, 10).replace(/-/g, '');
    const rand = Math.random().toString(36).substring(2, 6).toUpperCase();
    return `TRX-${today}-${rand}`;
}

const form = useForm({
    type: 'expense' as 'income' | 'expense',
    amount: '',
    currency: currency.value.code,
    category_key: '',
    category_name: '',
    description: '',
    reference: generateReference(),
    transaction_date: new Date().toISOString().split('T')[0],
    payment_method: 'card',
    is_recurring: false,
    recurring_frequency: 'monthly',
    notes: '',
});

const currentCategories = computed(() => {
    return form.type === 'income' ? props.categories.income : props.categories.expense;
});

// Update category name whenever category_key changes
watch(
    () => form.category_key,
    (newKey) => {
        const found = currentCategories.value.find((c) => c.id === newKey);
        if (found) {
            form.category_name = found.name;
        }
    }
);

// Switch default category if switching type
watch(
    () => form.type,
    () => {
        if (!props.transaction) {
            const firstCat = currentCategories.value[0];
            if (firstCat) {
                form.category_key = firstCat.id;
                form.category_name = firstCat.name;
            }
        }
    }
);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            if (props.transaction) {
                form.type = props.transaction.type;
                form.amount = props.transaction.amount;
                form.currency = props.transaction.currency || currency.value.code;
                form.category_key = props.transaction.category_key;
                form.category_name = props.transaction.category_name;
                form.description = props.transaction.description;
                form.reference = props.transaction.reference || generateReference();
                form.transaction_date = props.transaction.transaction_date
                    ? String(props.transaction.transaction_date).split('T')[0]
                    : new Date().toISOString().split('T')[0];
                form.payment_method = props.transaction.payment_method || 'card';
                form.is_recurring = Boolean(props.transaction.is_recurring);
                form.recurring_frequency = props.transaction.recurring_frequency || 'monthly';
                form.notes = props.transaction.notes || '';
            } else {
                form.reset();
                form.type = 'expense';
                form.currency = currency.value.code;
                form.reference = generateReference();
                form.transaction_date = new Date().toISOString().split('T')[0];
                form.payment_method = 'card';
                form.is_recurring = false;
                form.recurring_frequency = 'monthly';
                if (currentCategories.value.length > 0) {
                    form.category_key = currentCategories.value[0].id;
                    form.category_name = currentCategories.value[0].name;
                }
            }
            form.clearErrors();
        }
    }
);

const iconMap: Record<string, Component> = {
    Briefcase, ShoppingBag, Code, TrendingUp, CircleDollarSign,
    Server, Laptop, Megaphone, Building2, Users, Utensils, Home, Receipt,
};

function getCategoryIcon(iconName: string): Component {
    return iconMap[iconName] ?? Wallet;
}

const selectedCategory = computed(() => {
    return currentCategories.value.find((c) => c.id === form.category_key);
});

function submit() {
    if (props.transaction) {
        form.put(update.url(props.transaction.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Transaction updated successfully.');
                emit('update:open', false);
                emit('saved');
            },
        });
    } else {
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Transaction recorded successfully.');
                emit('update:open', false);
                emit('saved');
            },
        });
    }
}
</script>

<template>
    <Drawer :open="open" @update:open="emit('update:open', $event)">
        <DrawerContent class="md:inset-x-auto md:right-2 md:left-64 max-h-[92vh] overflow-y-auto">
            <div class="w-full">
                <!-- Compact Drawer Header -->
                <DrawerHeader class="pb-2 pt-3 px-4 border-b border-border/40">
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary ring-1 ring-primary/20"
                        >
                            <Wallet class="size-4" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <DrawerTitle class="text-sm font-semibold tracking-tight text-foreground">
                                {{ transaction ? 'Edit Transaction' : 'Record Transaction' }}
                            </DrawerTitle>
                            <DrawerDescription class="text-[11px] text-muted-foreground truncate">
                                {{ transaction ? 'Modify transaction details' : 'Log an income or expense' }}
                            </DrawerDescription>
                        </div>
                    </div>
                </DrawerHeader>

                <form @submit.prevent="submit" class="flex flex-col gap-3 px-4 pt-3 pb-4">
                    <!-- Compact Type Selector Segmented Pill -->
                    <div class="grid grid-cols-2 gap-1 rounded-lg bg-muted/50 p-1 border border-border/40">
                        <button
                            type="button"
                            @click="form.type = 'expense'"
                            :class="[
                                'flex h-7 items-center justify-center gap-1.5 rounded-md text-xs font-semibold transition-all',
                                form.type === 'expense'
                                    ? 'bg-rose-500 text-white shadow-2xs dark:bg-rose-600'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                        >
                            <ArrowDownLeft class="size-3.5" />
                            Expense
                        </button>
                        <button
                            type="button"
                            @click="form.type = 'income'"
                            :class="[
                                'flex h-7 items-center justify-center gap-1.5 rounded-md text-xs font-semibold transition-all',
                                form.type === 'income'
                                    ? 'bg-emerald-500 text-white shadow-2xs dark:bg-emerald-600'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                        >
                            <ArrowUpRight class="size-3.5" />
                            Income
                        </button>
                    </div>

                    <!-- Amount & Description Row (Compact) -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <div class="sm:col-span-1 flex flex-col gap-1">
                            <Label for="amount" class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Amount</Label>
                            <div class="relative flex items-center">
                                <span class="absolute left-2.5 text-xs font-bold text-muted-foreground select-none">
                                    {{ currency.symbol }}
                                </span>
                                <Input
                                    id="amount"
                                    v-model="form.amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    placeholder="0.00"
                                    required
                                    autofocus
                                    class="h-8 pl-7 text-xs font-bold rounded-lg border-border/70"
                                />
                            </div>
                            <InputError :message="form.errors.amount" />
                        </div>

                        <div class="sm:col-span-2 flex flex-col gap-1">
                            <Label for="description" class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Description</Label>
                            <div class="relative flex items-center">
                                <FileText class="absolute left-2.5 size-3.5 text-muted-foreground" />
                                <Input
                                    id="description"
                                    v-model="form.description"
                                    placeholder="e.g. Cloud server, Client retainer"
                                    required
                                    class="h-8 pl-8 text-xs rounded-lg border-border/70"
                                />
                            </div>
                            <InputError :message="form.errors.description" />
                        </div>
                    </div>

                    <!-- Category & Payment Method Row (Compact 2-col) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <!-- Category Combobox -->
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center justify-between">
                                <Label class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Category</Label>
                                <span v-if="selectedCategory" class="text-[10px] text-muted-foreground font-medium truncate max-w-[120px]">
                                    {{ selectedCategory.name }}
                                </span>
                            </div>

                            <Combobox v-model="form.category_key">
                                <ComboboxAnchor class="w-full">
                                    <ComboboxInput
                                        aria-label="Select category"
                                        :display-value="(value) => currentCategories.find(c => c.id === value)?.name || String(value || 'Choose category')"
                                        placeholder="Select category"
                                        class="h-8 text-xs rounded-lg"
                                    />
                                </ComboboxAnchor>
                                <ComboboxList side="bottom" align="start" class="text-xs max-h-52 overflow-y-auto">
                                    <ComboboxEmpty class="text-xs py-2 px-3 text-muted-foreground">No category found.</ComboboxEmpty>
                                    <ComboboxItem
                                        v-for="cat in currentCategories"
                                        :key="cat.id"
                                        :value="cat.id"
                                        class="flex items-center gap-2 text-xs py-1.5 px-2.5 cursor-pointer rounded-md"
                                    >
                                        <div
                                            class="flex size-4 shrink-0 items-center justify-center rounded text-white"
                                            :style="{ backgroundColor: cat.color }"
                                        >
                                            <component :is="getCategoryIcon(cat.icon)" class="size-2.5" />
                                        </div>
                                        <span class="truncate">{{ cat.name }}</span>
                                    </ComboboxItem>
                                </ComboboxList>
                            </Combobox>
                            <InputError :message="form.errors.category_key" />
                        </div>

                        <!-- Payment Method Combobox -->
                        <div class="flex flex-col gap-1">
                            <Label for="payment_method" class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Payment Method</Label>
                            <Combobox v-model="form.payment_method">
                                <ComboboxAnchor class="w-full">
                                    <ComboboxInput
                                        id="payment_method"
                                        aria-label="Payment method"
                                        :display-value="(value) => String(paymentMethods[String(value)] || value || 'Select method')"
                                        placeholder="Select method"
                                        class="h-8 text-xs rounded-lg"
                                    />
                                </ComboboxAnchor>
                                <ComboboxList side="bottom" align="start" class="text-xs max-h-48 overflow-y-auto">
                                    <ComboboxEmpty class="text-xs py-2 px-3 text-muted-foreground">No method found.</ComboboxEmpty>
                                    <ComboboxItem
                                        v-for="(label, key) in paymentMethods"
                                        :key="key"
                                        :value="key"
                                        class="text-xs py-1.5 px-2.5 cursor-pointer rounded-md"
                                    >
                                        {{ label }}
                                    </ComboboxItem>
                                </ComboboxList>
                            </Combobox>
                            <InputError :message="form.errors.payment_method" />
                        </div>
                    </div>

                    <!-- Date & Reference Row (Compact 2-col) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <!-- Date Picker -->
                        <div class="flex flex-col gap-1">
                            <Label for="transaction_date" class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Date</Label>
                            <DatePicker
                                v-model="form.transaction_date"
                                placeholder="Select date"
                                class="h-8 text-xs"
                            />
                            <InputError :message="form.errors.transaction_date" />
                        </div>

                        <!-- Reference Number (Auto-generated with quick regenerate) -->
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center justify-between">
                                <Label for="reference" class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Reference #</Label>
                                <span class="text-[10px] text-muted-foreground">Auto-generated</span>
                            </div>
                            <div class="relative flex items-center">
                                <Hash class="absolute left-2.5 size-3.5 text-muted-foreground" />
                                <Input
                                    id="reference"
                                    v-model="form.reference"
                                    placeholder="TRX-..."
                                    class="h-8 pl-8 pr-7 text-xs font-mono rounded-lg border-border/70"
                                />
                                <button
                                    type="button"
                                    @click="form.reference = generateReference()"
                                    title="Regenerate reference code"
                                    class="absolute right-1.5 flex size-5 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                >
                                    <RotateCcw class="size-3" />
                                </button>
                            </div>
                            <InputError :message="form.errors.reference" />
                        </div>
                    </div>

                    <!-- Recurring Toggle (Compact) -->
                    <div class="flex items-center justify-between rounded-lg border border-border/50 bg-muted/20 px-3 py-1.5">
                        <div class="flex items-center gap-2">
                            <Repeat class="size-3.5 text-muted-foreground" />
                            <div>
                                <p class="text-xs font-medium text-foreground">Recurring Transaction</p>
                                <p class="text-[10px] text-muted-foreground">Monthly retainer, salary, etc.</p>
                            </div>
                        </div>
                        <Checkbox
                            id="is_recurring"
                            :model-value="form.is_recurring"
                            @update:model-value="form.is_recurring = Boolean($event)"
                        />
                    </div>

                    <div v-if="form.is_recurring" class="flex flex-col gap-1 pl-2 border-l-2 border-primary/30">
                        <Label class="text-[11px] font-semibold text-muted-foreground">Frequency</Label>
                        <select
                            v-model="form.recurring_frequency"
                            class="h-7 rounded-md border border-border/70 bg-transparent px-2 text-xs focus:outline-hidden focus:ring-1 focus:ring-ring"
                        >
                            <option value="weekly" class="bg-popover text-popover-foreground">Weekly</option>
                            <option value="monthly" class="bg-popover text-popover-foreground">Monthly</option>
                            <option value="yearly" class="bg-popover text-popover-foreground">Yearly</option>
                        </select>
                    </div>

                    <!-- Notes (Compact) -->
                    <div class="flex flex-col gap-1">
                        <Label for="notes" class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Notes</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="2"
                            placeholder="Optional notes..."
                            class="w-full rounded-lg border border-border/70 bg-transparent p-2 text-xs focus:outline-hidden focus:ring-1 focus:ring-ring"
                        ></textarea>
                        <InputError :message="form.errors.notes" />
                    </div>

                    <!-- Drawer Actions (Compact) -->
                    <DrawerFooter class="px-0 pt-1 pb-0">
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                class="h-8 flex-1 rounded-lg text-xs"
                                @click="emit('update:open', false)"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                :disabled="form.processing"
                                class="h-8 flex-1 rounded-lg text-xs font-semibold"
                            >
                                {{ form.processing ? 'Saving...' : (transaction ? 'Update' : 'Record') }}
                            </Button>
                        </div>
                    </DrawerFooter>
                </form>
            </div>
        </DrawerContent>
    </Drawer>
</template>
