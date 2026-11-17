<script setup lang="ts">
import { money, toMinor as minor } from '@/lib/money';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import { FileText, MoreHorizontal, Plus, Printer } from '@lucide/vue';
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
import {
    Combobox,
    ComboboxAnchor,
    ComboboxInput,
    ComboboxList,
    ComboboxItem,
    ComboboxEmpty,
} from '@/components/ui/combobox';
import { usePermissions } from '@/composables/usePermissions';
import { decodePaginationLabel } from '@/lib/decodePaginationLabel';
import { store, approve, pay } from '@/routes/admin/payroll';
import { Badge } from '@/components/ui/badge';

type Payroll = {
    id: number;
    status: 'draft' | 'approved' | 'paid';
    paid_on: string | null;
    payment_reference: string | null;
    employee_name: string;
    period: string;
    base_minor: number;
    allowance_minor: number;
    deduction_minor: number;
    currency: string;
    note: string | null;
};
type Employee = { id: number; name: string; employee_code: string };
const props = defineProps<{
    payrolls: {
        data: Payroll[];
        from: number | null;
        to: number | null;
        total: number;
        last_page: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    employees: Employee[];
}>();
const { can } = usePermissions();
const documentRef = ref<InstanceType<typeof BusinessDocument> | null>(null);
const selected = ref<Payroll | null>(null);
async function printPayslip(row: Payroll): Promise<void> {
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
const createOpen = ref(false);
const paying = ref<Payroll | null>(null);
const approveForm = useForm({ status: '' });
const payment = useForm({
    paid_on: new Date().toLocaleDateString('en-CA'),
    payment_reference: '',
    status: '',
});
function recordPayment() {
    if (!paying.value) return;
    payment.post(pay.url(paying.value.id), {
        onSuccess: () => {
            paying.value = null;
            payment.reset();
        },
    });
}
const form = useForm({
    employee_id: '',
    period: '',
    base_minor: '',
    allowance_minor: '0',
    deduction_minor: '0',
    currency: 'BDT',
    note: '',
});

function createPayroll() {
    const base = minor(form.base_minor),
        allowance = minor(form.allowance_minor),
        deduction = minor(form.deduction_minor);
    if (base === null || allowance === null || deduction === null) {
        form.setError(
            'base_minor',
            'Enter valid amounts with up to two decimal places.',
        );
        return;
    }
    form.transform((data) => ({
        ...data,
        base_minor: base,
        allowance_minor: allowance,
        deduction_minor: deduction,
    })).post(store.url(), {
        onSuccess: () => {
            form.reset();
            createOpen.value = false;
        },
    });
}
</script>
<template>
    <div class="space-y-6 p-4 md:p-6">
        <Head title="Payroll" />
        <header>
            <h1 class="text-2xl font-semibold">Payroll</h1>
            <p class="text-sm text-muted-foreground">
                Review monthly payroll, approvals and published payslips.
            </p>
        </header>
        <div class="flex justify-end">
            <Button
                v-if="can('payroll.create')"
                @click="
                    form.reset();
                    form.clearErrors();
                    createOpen = true;
                "
                ><Plus class="size-4" /> Create payroll record</Button
            >
        </div>
        <Drawer :open="!!paying" @update:open="if (!$event) paying = null;">
            <DrawerContent class="max-h-[90vh] overflow-y-auto">
                <DrawerHeader
                    ><DrawerTitle>Record payroll payment</DrawerTitle
                    ><DrawerDescription
                        >Confirm the completed payment for
                        {{ paying?.employee_name }}. This records a payment; it
                        does not transfer funds.</DrawerDescription
                    ></DrawerHeader
                >
                <form
                    class="grid gap-4 px-6 pb-6"
                    @submit.prevent="recordPayment"
                >
                    <label
                        >Payment date<Input
                            v-model="payment.paid_on"
                            type="date"
                            required
                        /><span class="text-sm text-destructive">{{
                            payment.errors.paid_on
                        }}</span></label
                    >
                    <label
                        >Payment reference<Input
                            v-model="payment.payment_reference"
                            maxlength="255"
                            required
                        /><span class="text-sm text-destructive">{{
                            payment.errors.payment_reference
                        }}</span></label
                    >
                    <p class="text-sm text-destructive">
                        {{ payment.errors.status }}
                    </p>
                    <Button :disabled="payment.processing"
                        >Record payment</Button
                    >
                </form>
            </DrawerContent>
        </Drawer>
        <p
            v-if="approveForm.errors.status"
            role="alert"
            class="text-sm text-destructive"
        >
            {{ approveForm.errors.status }}
        </p>
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
                        <DrawerTitle>Create payroll record</DrawerTitle
                        ><DrawerDescription
                            >Review pay and adjustments for the selected
                            employee and month.</DrawerDescription
                        >
                    </div>
                </DrawerHeader>
                <form
                    class="mt-4 grid gap-4 sm:grid-cols-2"
                    @submit.prevent="createPayroll"
                >
                    <label
                        >Employee<Combobox
                            :model-value="form.employee_id"
                            @update:model-value="
                                (value) =>
                                    (form.employee_id = String(value ?? ''))
                            "
                            ><ComboboxAnchor
                                ><ComboboxInput
                                    aria-label="Employee"
                                    placeholder="Search employees"
                                    :display-value="
                                        (value) =>
                                            props.employees.find(
                                                (employee) =>
                                                    String(employee.id) ===
                                                    String(value),
                                            )?.name || ''
                                    " /></ComboboxAnchor
                            ><ComboboxList
                                ><ComboboxEmpty
                                    >No employees found.</ComboboxEmpty
                                ><ComboboxItem
                                    v-for="employee in employees"
                                    :key="employee.id"
                                    :value="String(employee.id)"
                                    >{{ employee.name }} ·
                                    {{ employee.employee_code }}</ComboboxItem
                                ></ComboboxList
                            ></Combobox
                        ><span class="text-sm text-destructive">{{
                            form.errors.employee_id
                        }}</span></label
                    >
                    <label
                        >Pay month<Input
                            v-model="form.period"
                            type="month"
                            required
                        /><span class="text-sm text-destructive">{{
                            form.errors.period
                        }}</span></label
                    >
                    <label
                        >Base pay<Input
                            v-model="form.base_minor"
                            inputmode="decimal"
                            required
                        /><span class="text-sm text-destructive">{{
                            form.errors.base_minor
                        }}</span></label
                    >
                    <label
                        >Allowances<Input
                            v-model="form.allowance_minor"
                            inputmode="decimal"
                            required
                        /><span class="text-sm text-destructive">{{
                            form.errors.allowance_minor
                        }}</span></label
                    >
                    <label
                        >Deductions<Input
                            v-model="form.deduction_minor"
                            inputmode="decimal"
                            required
                        /><span class="text-sm text-destructive">{{
                            form.errors.deduction_minor
                        }}</span></label
                    >
                    <label
                        >Currency<select
                            v-model="form.currency"
                            class="mt-1 h-9 w-full rounded-md border bg-background px-3"
                        >
                            <option>BDT</option>
                            <option>USD</option>
                        </select></label
                    >
                    <label class="sm:col-span-2"
                        >Notes (optional)<Input v-model="form.note" /><span
                            class="text-sm text-destructive"
                            >{{ form.errors.note }}</span
                        ></label
                    >
                    <div>
                        <Button :disabled="form.processing"
                            >Create payroll record</Button
                        >
                    </div>
                </form>
            </DrawerContent>
        </Drawer>
        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-3">Employee</th>
                        <th class="p-3">Month</th>
                        <th class="p-3">Net pay</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in props.payrolls.data"
                        :key="row.id"
                        class="border-t"
                    >
                        <td class="p-3">{{ row.employee_name }}</td>
                        <td class="p-3">{{ row.period }}</td>
                        <td class="p-3">
                            {{
                                money(
                                    Number(row.base_minor) +
                                        Number(row.allowance_minor) -
                                        Number(row.deduction_minor),
                                    row.currency,
                                )
                            }}
                        </td>
                        <td class="p-3">
                            <Badge variant="secondary">{{ row.status }}</Badge>
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
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        v-if="
                                            row.status === 'draft' &&
                                            can('payroll.approve')
                                        "
                                        :disabled="approveForm.processing"
                                        @select="
                                            approveForm.post(
                                                approve.url(row.id),
                                            )
                                        "
                                        >Approve payroll</DropdownMenuItem
                                    >
                                    <DropdownMenuItem
                                        v-if="
                                            row.status === 'approved' &&
                                            can('payroll.pay')
                                        "
                                        @select="
                                            payment.clearErrors();
                                            paying = row;
                                        "
                                        >Record payment</DropdownMenuItem
                                    ><DropdownMenuItem
                                        @select="printPayslip(row)"
                                        ><Printer class="size-4" /> Print
                                        payslip</DropdownMenuItem
                                    ></DropdownMenuContent
                                >
                            </DropdownMenu>
                        </td>
                    </tr>
                    <tr v-if="!props.payrolls.data.length">
                        <td colspan="5" class="p-5 text-muted-foreground">
                            No payroll records yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <p class="text-muted-foreground" aria-live="polite">
                Showing {{ payrolls.from ?? 0 }}–{{ payrolls.to ?? 0 }} of
                {{ payrolls.total }} records
            </p>
            <nav
                v-if="payrolls.last_page > 1"
                class="flex flex-wrap items-center gap-1"
                aria-label="Payroll pages"
            >
                <template v-for="(link, index) in payrolls.links" :key="index">
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
                        ><Link
                            :href="link.url"
                            preserve-scroll
                            :aria-current="link.active ? 'page' : undefined"
                            >{{ decodePaginationLabel(link.label) }}</Link
                        ></Button
                    >
                    <Button v-else size="sm" variant="outline" disabled>{{
                        decodePaginationLabel(link.label)
                    }}</Button>
                </template>
            </nav>
        </div>
        <div
            v-if="selected"
            class="sr-only print:not-sr-only"
            aria-hidden="true"
        >
            <BusinessDocument
                ref="documentRef"
                v-if="selected"
                :title="
                    selected.status === 'draft' ? 'Draft payslip' : 'Payslip'
                "
                :reference="`Payroll ${selected.id}`"
                :date="selected.period"
                date-label="Pay period"
                :recipient="{
                    label: 'Employee',
                    name: selected.employee_name,
                }"
                :columns="['Item', 'Amount']"
                :rows="[
                    {
                        id: 1,
                        values: [
                            'Base pay',
                            money(selected.base_minor, selected.currency),
                        ],
                    },
                    {
                        id: 2,
                        values: [
                            'Allowances',
                            money(selected.allowance_minor, selected.currency),
                        ],
                    },
                    {
                        id: 3,
                        values: [
                            'Deductions',
                            money(selected.deduction_minor, selected.currency),
                        ],
                    },
                ]"
                :totals="[
                    {
                        label: 'Gross pay',
                        value: money(
                            Number(selected.base_minor) +
                                Number(selected.allowance_minor),
                            selected.currency,
                        ),
                    },
                    {
                        label: 'Net pay',
                        value: money(
                            Number(selected.base_minor) +
                                Number(selected.allowance_minor) -
                                Number(selected.deduction_minor),
                            selected.currency,
                        ),
                    },
                ]"
                :note="selected.note"
            />
        </div>
    </div>
</template>
