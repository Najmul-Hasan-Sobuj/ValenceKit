<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import { watchDebounced } from '@vueuse/core';
import {
    Printer,
    FileText,
    MoreHorizontal,
    Plus,
    Wallet,
    Mail,
} from '@lucide/vue';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import BusinessDocument from '@/components/BusinessDocument.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import { decodePaginationLabel } from '@/lib/decodePaginationLabel';
import { index, store } from '@/routes/admin/invoices';
import { store as storePayment } from '@/routes/admin/invoices/payments';

type Invoice = {
    id: number;
    number: string;
    customer_name: string;
    customer_email: string | null;
    description: string;
    amount_minor: number;
    payments_sum_amount_minor: number | null;
    currency: string;
    issued_on: string;
    due_on: string;
    payment_status: 'paid' | 'overdue' | 'due_today' | 'unpaid';
    overdue_days: number;
};
const props = defineProps<{
    filters: { search: string };
    invoices: {
        data: Invoice[];
        from: number | null;
        to: number | null;
        total: number;
        last_page: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}>();
const { can } = usePermissions();
const search = ref(props.filters.search);
const createOpen = ref(false);
watchDebounced(
    search,
    (value) => {
        router.get(
            index.url(),
            { search: value || undefined },
            { preserveState: true, replace: true },
        );
    },
    { debounce: 300 },
);
const documentRef = ref<InstanceType<typeof BusinessDocument> | null>(null);
const selected = ref<Invoice | null>(null);
async function printInvoice(row: Invoice): Promise<void> {
    selected.value = row;
    await nextTick();
    window.addEventListener(
        'afterprint',
        () => {
            selected.value = null;
        },
        { once: true },
    );
    await documentRef.value?.printDocument();
}
const paymentFor = ref<Invoice | null>(null);
const invoice = useForm({
    number: '',
    customer_name: '',
    customer_email: '',
    description: '',
    amount_minor: '',
    currency: 'BDT',
    issued_on: '',
    due_on: '',
});
const payment = useForm({ amount_minor: '', paid_on: '', reference: '' });
const money = (value: number | string | null, currency: string) =>
    `${currency} ${(Number(value ?? 0) / 100).toFixed(2)}`;
function minor(value: string): number | null {
    if (!/^\d+(\.\d{1,2})?$/.test(value)) return null;
    const [whole, fraction = ''] = value.split('.');
    const amount = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
    return Number.isSafeInteger(amount) ? amount : null;
}
function reminderLink(row: Invoice): string {
    const due = money(
        row.amount_minor - Number(row.payments_sum_amount_minor ?? 0),
        row.currency,
    );
    const subject = `Payment reminder: invoice ${row.number}`;
    const body = `Hello ${row.customer_name},\n\nThis is a reminder that invoice ${row.number}, due ${row.due_on}, has an outstanding balance of ${due}.\n\nThank you.`;
    return `mailto:${encodeURIComponent(row.customer_email ?? '')}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
}
function createInvoice() {
    const amount = minor(invoice.amount_minor);
    if (amount === null) {
        invoice.setError(
            'amount_minor',
            'Enter a valid amount with up to two decimal places.',
        );
        return;
    }
    invoice
        .transform((data) => ({ ...data, amount_minor: amount }))
        .post(store.url(), {
            onSuccess: () => {
                invoice.reset();
                createOpen.value = false;
            },
        });
}
function recordPayment() {
    if (!paymentFor.value) return;
    const amount = minor(payment.amount_minor);
    if (amount === null) {
        payment.setError(
            'amount_minor',
            'Enter a valid amount with up to two decimal places.',
        );
        return;
    }
    payment
        .transform((data) => ({ ...data, amount_minor: amount }))
        .post(storePayment.url(String(paymentFor.value.id)), {
            onSuccess: () => {
                payment.reset();
                paymentFor.value = null;
            },
        });
}
</script>
<template>
    <div class="space-y-6 p-4 md:p-6">
        <Head title="Invoices" />
        <header>
            <h1 class="text-2xl font-semibold">Invoices</h1>
            <p class="text-sm text-muted-foreground">
                Create invoices and record payments received.
            </p>
        </header>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Input
                v-model="search"
                class="w-full sm:max-w-sm"
                aria-label="Search invoices"
                placeholder="Search by invoice number or customer"
            />
            <Button
                v-if="can('invoices.create')"
                @click="
                    invoice.reset();
                    invoice.clearErrors();
                    createOpen = true;
                "
                ><Plus class="size-4" /> Create invoice</Button
            >
        </div>
        <Drawer v-model:open="createOpen">
            <DrawerContent
                class="max-h-[92vh] overflow-y-auto md:inset-x-auto md:right-2 md:left-64"
            >
                <DrawerHeader
                    class="flex flex-row items-center gap-3 text-left"
                >
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/20"
                    >
                        <FileText class="size-5" />
                    </div>
                    <div>
                        <DrawerTitle>Create invoice</DrawerTitle
                        ><DrawerDescription
                            >Enter the customer, amount and due
                            date.</DrawerDescription
                        >
                    </div>
                </DrawerHeader>
                <form
                    class="mt-4 grid gap-4 sm:grid-cols-2"
                    @submit.prevent="createInvoice"
                >
                    <label
                        >Invoice number<Input
                            v-model="invoice.number"
                            required
                        /><span class="text-sm text-destructive">{{
                            invoice.errors.number
                        }}</span></label
                    >
                    <label
                        >Customer name<Input
                            v-model="invoice.customer_name"
                            required
                        /><span class="text-sm text-destructive">{{
                            invoice.errors.customer_name
                        }}</span></label
                    >
                    <label
                        >Customer email (optional)<Input
                            v-model="invoice.customer_email"
                            type="email"
                        /><span class="text-sm text-destructive">{{
                            invoice.errors.customer_email
                        }}</span></label
                    >
                    <label
                        >Description<Input
                            v-model="invoice.description"
                            required
                        /><span class="text-sm text-destructive">{{
                            invoice.errors.description
                        }}</span></label
                    >
                    <label
                        >Amount<Input
                            v-model="invoice.amount_minor"
                            inputmode="decimal"
                            placeholder="0.00"
                            required
                        /><span class="text-sm text-destructive">{{
                            invoice.errors.amount_minor
                        }}</span></label
                    >
                    <label
                        >Currency<select
                            v-model="invoice.currency"
                            class="mt-1 h-9 w-full rounded-md border bg-background px-3"
                        >
                            <option>BDT</option>
                            <option>USD</option>
                        </select></label
                    >
                    <label
                        >Issue date<Input
                            v-model="invoice.issued_on"
                            type="date"
                            required
                        /><span class="text-sm text-destructive">{{
                            invoice.errors.issued_on
                        }}</span></label
                    >
                    <label
                        >Due date<Input
                            v-model="invoice.due_on"
                            type="date"
                            required
                        /><span class="text-sm text-destructive">{{
                            invoice.errors.due_on
                        }}</span></label
                    >
                    <div>
                        <Button :disabled="invoice.processing"
                            >Create invoice</Button
                        >
                    </div>
                </form>
            </DrawerContent>
        </Drawer>
        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-3">Invoice</th>
                        <th class="p-3">Customer</th>
                        <th class="p-3">Due</th>
                        <th class="p-3">Total</th>
                        <th class="p-3">Outstanding</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in props.invoices.data"
                        :key="row.id"
                        class="border-t"
                    >
                        <td class="p-3">{{ row.number }}</td>
                        <td class="p-3">{{ row.customer_name }}</td>
                        <td class="p-3">{{ row.due_on }}</td>
                        <td class="p-3">
                            {{ money(row.amount_minor, row.currency) }}
                        </td>
                        <td class="p-3">
                            {{
                                money(
                                    row.amount_minor -
                                        Number(
                                            row.payments_sum_amount_minor ?? 0,
                                        ),
                                    row.currency,
                                )
                            }}
                        </td>
                        <td class="p-3">
                            <span
                                v-if="row.payment_status === 'paid'"
                                class="font-medium text-emerald-700 dark:text-emerald-400"
                                >Paid</span
                            >
                            <span
                                v-else-if="row.payment_status === 'overdue'"
                                class="font-medium text-destructive"
                                >{{ row.overdue_days }}
                                {{ row.overdue_days === 1 ? 'day' : 'days' }}
                                overdue</span
                            >
                            <span
                                v-else-if="row.payment_status === 'due_today'"
                                class="font-medium text-amber-700 dark:text-amber-400"
                                >Due today</span
                            >
                            <span v-else class="text-muted-foreground"
                                >Unpaid</span
                            >
                        </td>
                        <td class="p-3 text-right">
                            <DropdownMenu
                                ><DropdownMenuTrigger as-child
                                    ><Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Open actions"
                                        ><MoreHorizontal
                                            class="size-4" /></Button
                                ></DropdownMenuTrigger>
                                <DropdownMenuContent align="end"
                                    ><DropdownMenuItem
                                        @select="printInvoice(row)"
                                        ><Printer class="size-4" /> Print
                                        invoice</DropdownMenuItem
                                    ><DropdownMenuItem
                                        v-if="
                                            row.customer_email &&
                                            row.payment_status !== 'paid'
                                        "
                                        as-child
                                    >
                                        <a :href="reminderLink(row)"
                                            ><Mail class="size-4" /> Draft email
                                            reminder</a
                                        > </DropdownMenuItem
                                    ><DropdownMenuItem
                                        v-if="
                                            can('invoices.payments.create') &&
                                            row.amount_minor >
                                                Number(
                                                    row.payments_sum_amount_minor ??
                                                        0,
                                                )
                                        "
                                        @select="
                                            payment.reset();
                                            payment.clearErrors();
                                            paymentFor = row;
                                        "
                                        ><Wallet class="size-4" /> Record
                                        payment</DropdownMenuItem
                                    ></DropdownMenuContent
                                >
                            </DropdownMenu>
                        </td>
                    </tr>
                    <tr v-if="!props.invoices.data.length">
                        <td colspan="7" class="p-5 text-muted-foreground">
                            {{
                                search
                                    ? 'No invoices match your search.'
                                    : 'No invoices yet.'
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <p class="text-muted-foreground" aria-live="polite">
                Showing {{ invoices.from ?? 0 }}–{{ invoices.to ?? 0 }} of
                {{ invoices.total }} invoices
            </p>
            <nav
                v-if="invoices.last_page > 1"
                class="flex flex-wrap items-center gap-1"
                aria-label="Invoice pages"
            >
                <template v-for="(link, index) in invoices.links" :key="index">
                    <Button
                        v-if="link.url"
                        as-child
                        size="sm"
                        variant="outline"
                        :class="
                            link.active
                                ? 'border-primary/20 bg-primary/10 text-primary'
                                : ''
                        "
                    >
                        <Link
                            :href="link.url"
                            preserve-scroll
                            :aria-current="link.active ? 'page' : undefined"
                            >{{ decodePaginationLabel(link.label) }}</Link
                        >
                    </Button>
                    <Button v-else size="sm" variant="outline" disabled>{{
                        decodePaginationLabel(link.label)
                    }}</Button>
                </template>
            </nav>
        </div>
        <Drawer
            :open="!!paymentFor"
            @update:open="
                (value) => {
                    if (!value) paymentFor = null;
                }
            "
        >
            <DrawerContent
                class="max-h-[92vh] overflow-y-auto md:inset-x-auto md:right-2 md:left-64"
            >
                <DrawerHeader class="flex flex-row items-center gap-3 text-left"
                    ><div
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/20"
                    >
                        <Wallet class="size-5" />
                    </div>
                    <div>
                        <DrawerTitle>Record payment</DrawerTitle
                        ><DrawerDescription
                            >Record a payment received for this
                            invoice.</DrawerDescription
                        >
                    </div></DrawerHeader
                >
                <form
                    class="space-y-3 rounded-xl border p-4"
                    @submit.prevent="recordPayment"
                >
                    <h2 class="font-semibold">
                        Record payment: {{ paymentFor?.number }}
                    </h2>
                    <label class="block"
                        >Amount<Input
                            v-model="payment.amount_minor"
                            inputmode="decimal"
                            required
                        /><span class="text-sm text-destructive">{{
                            payment.errors.amount_minor
                        }}</span></label
                    ><label class="block"
                        >Payment date<Input
                            v-model="payment.paid_on"
                            type="date"
                            required
                        /><span class="text-sm text-destructive">{{
                            payment.errors.paid_on
                        }}</span></label
                    ><label class="block"
                        >Reference (optional)<Input
                            v-model="payment.reference" /></label
                    ><Button :disabled="payment.processing"
                        >Save payment</Button
                    >
                    <Button
                        type="button"
                        variant="outline"
                        @click="paymentFor = null"
                        >Cancel</Button
                    >
                </form>
            </DrawerContent>
        </Drawer>
        <div
            v-if="selected"
            class="sr-only print:not-sr-only"
            aria-hidden="true"
        >
            <BusinessDocument
                ref="documentRef"
                v-if="selected"
                title="Invoice"
                :reference="selected.number"
                :date="selected.issued_on"
                date-label="Issued"
                :metadata="[{ label: 'Due date', value: selected.due_on }]"
                :recipient="{
                    label: 'Bill to',
                    name: selected.customer_name,
                    details: selected.customer_email
                        ? [selected.customer_email]
                        : [],
                }"
                :columns="['Description', 'Amount']"
                :rows="[
                    {
                        id: selected.id,
                        values: [
                            selected.description,
                            money(selected.amount_minor, selected.currency),
                        ],
                    },
                ]"
                :totals="[
                    {
                        label: 'Total',
                        value: money(selected.amount_minor, selected.currency),
                    },
                    {
                        label: 'Paid',
                        value: money(
                            selected.payments_sum_amount_minor,
                            selected.currency,
                        ),
                    },
                    {
                        label: 'Balance due',
                        value: money(
                            selected.amount_minor -
                                Number(selected.payments_sum_amount_minor ?? 0),
                            selected.currency,
                        ),
                    },
                ]"
            />
        </div>
    </div>
</template>
