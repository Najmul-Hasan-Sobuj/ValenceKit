<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import MediaManager from '@/components/MediaManager.vue';
import type { MediaItem } from '@/types/media';
import { useDebounceFn } from '@vueuse/core';
import { parseDate } from '@internationalized/date';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import type { DateRange } from '@/types';
import {
    Users,
    Plus,
    MoreHorizontal,
    Pencil,
    Eye,
    Check,
    X,
    Clock,
    Download,
    ImagePlus,
} from '@lucide/vue';
import {
    index,
    clock,
    attendance,
    leave,
    review,
    correction,
    settings,
    exportMethod,
    deleteSetting,
} from '@/actions/App/Http/Controllers/Admin/HrController';
import {
    store as storeEmployee,
    update as updateEmployee,
} from '@/routes/admin/hr/employees';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxInput,
    ComboboxList,
    ComboboxEmpty,
    ComboboxItem,
} from '@/components/ui/combobox';
import { Badge } from '@/components/ui/badge';
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogCancel,
} from '@/components/ui/alert-dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Drawer,
    DrawerContent,
    DrawerHeader,
    DrawerTitle,
    DrawerDescription,
    DrawerFooter,
} from '@/components/ui/drawer';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import InputError from '@/components/InputError.vue';
import { usePermissions } from '@/composables/usePermissions';
import { decodePaginationLabel } from '@/lib/decodePaginationLabel';

type Option = { id: number; name: string; is_active?: boolean };
type Employee = Option & {
    employee_code: string;
    email: string | null;
    phone: string | null;
    designation: string;
    department_id: number;
    department: Option;
    manager_id: number | null;
    admin_id: number | null;
    joined_on: string;
    ended_on: string | null;
    emergency_contact: string | null;
    notes: string | null;
    media: MediaItem[];
};
type Page<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};
type Leave = {
    id: number;
    employee: Employee;
    leave_type: Option;
    start_date: string;
    end_date: string;
    dates: string[];
    reason: string;
    status: string;
    review_note: string | null;
};
type Correction = {
    id: number;
    employee: Option;
    work_date: string;
    check_in: string;
    check_out: string | null;
    reason: string;
};
type Report = {
    employee_id: number;
    name: string;
    date: string;
    status: string;
    check_in: string | null;
    check_out: string | null;
    minutes: number | null;
    late_minutes: number;
    timezone: string;
};
type Value = string | number | boolean | number[] | null;
type Field = {
    key: string;
    label: string;
    type?: string;
    options?: { value: string | number; label: string }[];
};
const props = defineProps<{
    section: string;
    filters: Record<string, string>;
    date: string;
    month: string;
    rangeStart: string;
    rangeEnd: string;
    employees: Page<Employee>;
    employeeChoices: Option[];
    calendar: { date: string; name: string }[];
    reportLinks: { url: string | null; label: string; active: boolean }[];
    reportPagination: {
        from: number | null;
        to: number | null;
        total: number;
        currentPage: number;
        lastPage: number;
    } | null;
    leaves: Page<Leave>;
    corrections: Page<Correction>;
    report: Report[];
    departments: Option[];
    schedules: (Option & {
        start_time: string;
        end_time: string;
        timezone: string;
        weekdays: number[];
    })[];
    holidays: (Option & { date: string })[];
    leaveTypes: Option[];
    accounts: Option[];
    allocations: {
        id: number;
        employee: Option;
        leave_type: Option;
        days: number;
        reserved: number;
        year: number;
    }[];
    own: (Option & { is_active: boolean }) | null;
    clockState: { checked_in: boolean; checked_out: boolean };
    totals: {
        active: number;
        pending: number;
        present: number;
        on_leave: number;
    };
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'People & HR', href: index.url() }] },
});
const { can, admin } = usePermissions();
const page = usePage();
const mediaEnabled = computed(() => page.props.moduleStates.media ?? true);
const photoEmployeeId = ref<number | null>(null);
const photoEmployee = computed(() =>
    props.employees.data.find(
        (employee) => employee.id === photoEmployeeId.value,
    ),
);
const employeeAvatar = (employee: Employee) =>
    employee.media.find((image) => image.collection === 'avatar');
const filters = ref({
    search: props.filters.search || '',
    department_id: props.filters.department_id || '',
    employee_id: props.filters.employee_id || '',
    date: props.date,
    month: props.month,
    from: props.filters.from || '',
    to: props.filters.to || '',
    status: props.filters.status || '',
});
function navigate(section = props.section) {
    const changedSection = section !== props.section;
    router.get(
        index.url(),
        { ...filters.value, section },
        { preserveState: true, preserveScroll: !changedSection },
    );
}
const applyFilters = useDebounceFn(() => navigate(), 400);
watch(
    () => props.filters,
    () => {
        filters.value = {
            search: props.filters.search || '',
            department_id: props.filters.department_id || '',
            employee_id: props.filters.employee_id || '',
            date: props.date,
            month: props.month,
            from: props.filters.from || '',
            to: props.filters.to || '',
            status: props.filters.status || '',
        };
    },
);
const dateRange = ref<DateRange | undefined>({
    start: parseDate(props.rangeStart),
    end: parseDate(props.rangeEnd),
});
const rangeError = ref('');
watch(dateRange, (value) => {
    if (value?.start && !value.end) return;
    const from = value?.start?.toString() || '';
    const to = value?.end?.toString() || '';
    if (from && to && (Date.parse(to) - Date.parse(from)) / 86400000 > 366) {
        rangeError.value = 'Select a range of at most 367 days.';
        return;
    }
    rangeError.value = '';
    filters.value.from = from;
    filters.value.to = to;
    applyFilters();
});
watch(
    () => [props.rangeStart, props.rangeEnd],
    ([start, end]) => {
        if (
            dateRange.value?.start?.toString() !== start ||
            dateRange.value?.end?.toString() !== end
        ) {
            dateRange.value = { start: parseDate(start), end: parseDate(end) };
        }
    },
);
const departmentName = computed(
    () =>
        props.departments.find(
            (row) => String(row.id) === String(filters.value.department_id),
        )?.name || '',
);
const visibleFormErrors = computed(() =>
    Object.entries(form.errors).filter(
        ([key]) => !fields.value.some((field) => field.key === key),
    ),
);
const open = ref(false);
const title = ref('');
const description = ref('');
const fields = ref<Field[]>([]);
const target = ref('');
const method = ref<'post' | 'put'>('post');
const formFields = ref<HTMLDivElement | null>(null);
const form = useForm<Record<string, Value>>({
    id: null,
    name: '',
    email: '',
    phone: '',
    emergency_contact: '',
    notes: '',
    designation: '',
    department_id: '',
    manager_id: '',
    admin_id: '',
    joined_on: '',
    ended_on: '',
    is_active: true,
    work_schedule_id: '',
    effective_from: '',
    employee_id: '',
    leave_type_id: '',
    start_date: '',
    end_date: '',
    reason: '',
    work_date: '',
    check_in: '',
    check_out: '',
    action: '',
    note: '',
    kind: '',
    start_time: '',
    end_time: '',
    weekdays: [],
    grace_minutes: 10,
    timezone: 'Asia/Dhaka',
    date: '',
    is_paid: true,
    year: 2026,
    days: 0,
});
const lateAfter = computed(() => {
    const start = String(form.start_time ?? '');
    const grace = Number(form.grace_minutes);
    if (
        !/^\d{2}:\d{2}$/.test(start) ||
        form.grace_minutes === '' ||
        !Number.isInteger(grace) ||
        grace < 0 ||
        grace > 120
    )
        return null;
    const [hours, minutes] = start.split(':').map(Number);
    if (hours > 23 || minutes > 59) return null;
    return new Intl.DateTimeFormat('en', {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(2000, 0, 1, hours, minutes + grace + 1));
});
const clockForm = useForm({ action: 'in' });
type ParentForm = {
    title: string;
    description: string;
    fields: Field[];
    target: string;
    method: 'post' | 'put';
    data: Record<string, Value>;
    errors: Record<string, string>;
    selectKey: string;
    kind: string;
    scrollTop: number;
};
const parents = ref<ParentForm[]>([]);
const options = (rows: Option[]) =>
    rows.map((row) => ({ value: row.id, label: row.name }));
const employeeOptions = computed(() => options(props.employeeChoices));
const field = (key: string, label: string, type = 'text'): Field => ({
    key,
    label,
    type,
});
const select = (
    key: string,
    label: string,
    choices: { value: string | number; label: string }[],
): Field => ({ key, label, options: choices });
function show(
    name: string,
    url: string,
    data: Record<string, Value>,
    inputs: Field[],
    detail = '',
) {
    title.value = name;
    target.value = url;
    description.value =
        detail ||
        'Review the details before saving. Changes are recorded in the audit log.';
    fields.value = inputs;
    method.value = 'post';
    form.reset();
    Object.assign(form, data);
    form.clearErrors();
    open.value = true;
}
function submit() {
    form.submit(method.value, target.value, {
        preserveScroll: true,
        onSuccess: async (page) => {
            const parent = parents.value.pop();
            if (!parent) {
                open.value = false;
                const created = page.props.hr_created as {
                    kind: string;
                    id: number;
                } | null;
                if (
                    created?.kind === 'employee' &&
                    method.value === 'post' &&
                    mediaEnabled.value &&
                    can('media.create')
                ) {
                    await nextTick();
                    photoEmployeeId.value = created.id;
                }
                return;
            }
            const created = page.props.hr_created as {
                kind: string;
                id: number;
            } | null;
            await nextTick();
            restoreParent(parent);
            if (created && created.kind === parent.kind) {
                form[parent.selectKey] = created.id;
                form.clearErrors(parent.selectKey);
            }
        },
    });
}
function refreshedOptions(
    key: string,
    current: Field['options'],
): Field['options'] {
    if (key === 'department_id')
        return options(
            props.departments.filter((row) => row.is_active !== false),
        );
    if (key === 'work_schedule_id')
        return options(
            props.schedules.filter((row) => row.is_active !== false),
        );
    if (key === 'leave_type_id')
        return options(
            props.leaveTypes.filter((row) => row.is_active !== false),
        );
    if (key === 'manager_id') {
        return employeeOptions.value.filter(
            (option) =>
                option.value !== form.id &&
                !parents.value.some(
                    (parent) => parent.data.id === option.value,
                ),
        );
    }
    if (key === 'employee_id' && (can('employees.view') || can('hr.team')))
        return employeeOptions.value;
    return current;
}
function addRelated(input: Field) {
    const kind = relatedKind(input.key);
    if (!kind) return;
    parents.value.push({
        title: title.value,
        description: description.value,
        fields: fields.value,
        target: target.value,
        method: method.value,
        data: { ...form.data() },
        errors: { ...form.errors },
        selectKey: input.key,
        kind,
        scrollTop: formFields.value?.scrollTop ?? 0,
    });
    if (kind === 'employee') {
        employeeForm();
        if (input.key === 'manager_id') {
            title.value = 'Add manager';
            description.value =
                'Create a manager, then return to the previous employee form. Your entered details are kept.';
        }
        void nextTick(() => {
            if (formFields.value) formFields.value.scrollTop = 0;
            document.getElementById('hr-name')?.focus();
        });
    } else setting(kind);
}
function relatedKind(key: string): string | null {
    if (
        ['employee_id', 'manager_id'].includes(key) &&
        can('employees.create') &&
        (can('employees.view') || can('hr.team'))
    )
        return 'employee';
    if (!can('hr.settings.edit')) return null;
    return (
        (
            {
                department_id: 'department',
                work_schedule_id: 'schedule',
                leave_type_id: 'leave-type',
            } as Record<string, string>
        )[key] || null
    );
}
function cancelForm() {
    const parent = parents.value.pop();
    if (!parent) {
        open.value = false;
        return;
    }
    restoreParent(parent);
}
function restoreParent(parent: ParentForm) {
    title.value = parent.title;
    description.value = parent.description;
    target.value = parent.target;
    method.value = parent.method;
    form.reset();
    Object.assign(form, parent.data);
    fields.value = parent.fields.map((input) => ({
        ...input,
        options: input.options
            ? refreshedOptions(input.key, input.options)
            : undefined,
    }));
    form.clearErrors();
    form.setError(parent.errors);
    void nextTick(() => {
        if (formFields.value) {
            formFields.value.scrollTop = parent.scrollTop;
            formFields.value
                .querySelector<HTMLButtonElement>(
                    `[data-related-key="${parent.selectKey}"]`,
                )
                ?.focus({ preventScroll: true });
        }
    });
}
function employeeForm(employee?: Employee) {
    show(
        employee ? 'Edit employee' : 'Add employee',
        employee ? updateEmployee.url(employee.id) : storeEmployee.url(),
        {
            id: employee?.id ?? null,
            name: employee?.name || '',
            email: employee?.email || '',
            phone: employee?.phone || '',
            emergency_contact: employee?.emergency_contact || '',
            notes: employee?.notes || '',
            designation: employee?.designation || '',
            department_id: employee?.department_id || '',
            manager_id: employee?.manager_id || '',
            admin_id: employee?.admin_id || '',
            joined_on: employee?.joined_on || props.date,
            ended_on: employee?.ended_on || '',
            is_active: employee?.is_active ?? true,
            work_schedule_id: '',
            effective_from: '',
        },
        [
            field('name', 'Full name'),
            field('email', 'Email', 'email'),
            field('phone', 'Phone'),
            select('department_id', 'Department', options(props.departments)),
            field('designation', 'Designation'),
            select(
                'manager_id',
                'Manager (optional)',
                employeeOptions.value.filter(
                    (o) =>
                        o.value !== employee?.id &&
                        !parents.value.some(
                            (parent) => parent.data.id === o.value,
                        ),
                ),
            ),
            select(
                'admin_id',
                'Linked account (optional)',
                options(props.accounts),
            ),
            field('joined_on', 'Joining date', 'date'),
            field('ended_on', 'Employment end date', 'date'),
            select(
                'work_schedule_id',
                employee ? 'New schedule (optional)' : 'Work schedule',
                options(props.schedules),
            ),
            field(
                'effective_from',
                'Schedule effective date (defaults to joining date)',
                'date',
            ),
            field('is_active', 'Active employee', 'checkbox'),
            field('emergency_contact', 'Emergency contact (optional)'),
            field('notes', 'Notes (optional)', 'textarea'),
        ],
    );
    if (employee) method.value = 'put';
}
function leaveForm() {
    show(
        'Apply for leave',
        leave.url(),
        {
            employee_id: props.own?.id || '',
            leave_type_id: '',
            start_date: props.date,
            end_date: props.date,
            reason: '',
        },
        [
            select(
                'employee_id',
                'Employee',
                can('leave.manage')
                    ? employeeOptions.value
                    : options(props.own ? [props.own] : []),
            ),
            select('leave_type_id', 'Leave type', options(props.leaveTypes)),
            field('start_date', 'From', 'date'),
            field('end_date', 'Through', 'date'),
            field('reason', 'Reason', 'textarea'),
        ],
        'Full working days only. Holidays and weekly days off are excluded; pending requests reserve your balance.',
    );
}
function attendanceForm() {
    show(
        can('attendance.manage')
            ? 'Record / correct attendance'
            : 'Request attendance correction',
        attendance.url(),
        {
            employee_id: props.own?.id || '',
            work_date: props.date,
            check_in: '',
            check_out: '',
            reason: '',
        },
        [
            select(
                'employee_id',
                'Employee',
                can('attendance.manage')
                    ? employeeOptions.value
                    : options(props.own ? [props.own] : []),
            ),
            field('work_date', 'Work date', 'date'),
            field(
                'check_in',
                'Check-in (employee schedule timezone)',
                'datetime-local',
            ),
            field('check_out', 'Check-out (optional)', 'datetime-local'),
            field('reason', 'Reason for this record or correction', 'textarea'),
        ],
    );
}
function decision(
    row: Leave | Correction,
    action: string,
    isCorrection = false,
) {
    show(
        `${action === 'approve' ? 'Approve' : action === 'reject' ? 'Reject' : 'Cancel'} ${isCorrection ? 'correction' : 'leave'}`,
        isCorrection ? correction.url(row.id) : review.url(row.id),
        { action, note: '' },
        [field('note', 'Review note (required for rejection)', 'textarea')],
        `${row.employee.name} — ${row.reason}`,
    );
}
function setting(kind: string) {
    const base = [field('name', 'Name')];
    if (kind === 'schedule')
        show(
            'Add work schedule',
            settings.url(),
            {
                kind,
                name: '',
                start_time: '09:00',
                end_time: '18:00',
                weekdays: [1, 2, 3, 4, 5],
                grace_minutes: 10,
                timezone: 'Asia/Dhaka',
            },
            [
                ...base,
                field('start_time', 'Start time', 'time'),
                field('end_time', 'End time', 'time'),
                field('weekdays', 'Working days', 'weekdays'),
                field('grace_minutes', 'Late grace period', 'number'),
                field('timezone', 'Time zone'),
            ],
            'Choose the working days and hours for this schedule.',
        );
    else if (kind === 'holiday')
        show(
            'Add holiday',
            settings.url(),
            { kind, name: '', date: props.date },
            [...base, field('date', 'Date', 'date')],
        );
    else if (kind === 'leave-type')
        show(
            'Add leave type',
            settings.url(),
            { kind, name: '', is_paid: true },
            [
                field('name', 'Leave type name'),
                field('is_paid', 'Pay status', 'pay-status'),
            ],
            'Name the leave type and choose whether it is paid.',
        );
    else if (kind === 'allocation')
        show(
            'Set annual leave allowance',
            settings.url(),
            {
                kind,
                employee_id: '',
                leave_type_id: '',
                year: Number(props.date.slice(0, 4)),
                days: 0,
                reason: '',
            },
            [
                select('employee_id', 'Employee', employeeOptions.value),
                select(
                    'leave_type_id',
                    'Leave type',
                    options(props.leaveTypes),
                ),
                field('year', 'Year', 'number'),
                field('days', 'Total annual allowance (days)', 'number'),
                field('reason', 'Adjustment reason', 'textarea'),
            ],
        );
    else show('Add department', settings.url(), { kind, name: '' }, base);
}
function punch(action: string) {
    clockForm.action = action;
    clockForm.post(clock.url(), { preserveScroll: true });
}
const reviewable = (row: Leave) =>
    row.employee.admin_id !== admin.value?.id &&
    (can('leave.approve') ||
        (can('leave.approve-team') &&
            row.employee.manager_id === props.own?.id));
const balance = computed(() =>
    props.section === 'workspace'
        ? props.allocations.filter((row) => row.employee.id === props.own?.id)
        : props.allocations,
);
const detail = ref<Employee | null>(null);
const removal = ref<{ kind: string; id: number; name: string } | null>(null);
const removalForm = useForm({});
function removeSetting() {
    if (!removal.value) return;
    removalForm.delete(
        deleteSetting.url({ kind: removal.value.kind, id: removal.value.id }),
        {
            preserveScroll: true,
            onSuccess: () => {
                removal.value = null;
            },
        },
    );
}
function editSetting(kind: string, row: Option) {
    setting(kind);
    Object.assign(form, row);
    form.id = row.id;
    title.value = `Edit ${row.name}`;
    if (['department', 'leave-type'].includes(kind))
        fields.value.push(field('is_active', 'Active', 'checkbox'));
}
</script>

<template>
    <div
        class="flex min-w-0 flex-col gap-6 p-4 [color-scheme:light] md:p-6 dark:[color-scheme:dark]"
    >
        <Head title="People & HR" />
        <AlertDialog
            :open="!!removal"
            @update:open="
                (value) => {
                    if (!value && !removalForm.processing) removal = null;
                }
            "
            ><AlertDialogContent class="sm:max-w-md"
                ><AlertDialogHeader
                    ><AlertDialogTitle
                        >Delete {{ removal?.name }}?</AlertDialogTitle
                    ><AlertDialogDescription
                        >This removes the setting permanently. Records
                        referenced by HR history cannot be
                        deleted.</AlertDialogDescription
                    ></AlertDialogHeader
                ><InputError
                    v-for="(error, key) in removalForm.errors"
                    :key="key"
                    :message="error"
                /><AlertDialogFooter
                    ><AlertDialogCancel :disabled="removalForm.processing"
                        >Cancel</AlertDialogCancel
                    ><Button
                        variant="destructive"
                        :disabled="removalForm.processing"
                        @click="removeSetting"
                        >Delete setting</Button
                    ></AlertDialogFooter
                ></AlertDialogContent
            ></AlertDialog
        >
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    People & HR
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Your team's people, time and leave in one place.
                </p>
            </div>
            <Button
                v-if="
                    can('employees.create') &&
                    ['overview', 'employees'].includes(section)
                "
                @click="employeeForm()"
                ><Plus class="size-4" /> Add employee</Button
            >
        </div>
        <div v-if="section !== 'settings'" class="flex flex-wrap gap-3">
            <Input
                v-model="filters.search"
                @update:model-value="applyFilters()"
                class="w-full sm:w-64"
                aria-label="Search employees"
                placeholder="Search employee name or ID…"
            />
            <Combobox
                :model-value="departmentName"
                @update:model-value="
                    (value) => {
                        filters.department_id = String(
                            departments.find(
                                (department) => department.name === value,
                            )?.id || '',
                        );
                        navigate();
                    }
                "
                class="w-full sm:w-52"
            >
                <ComboboxAnchor class="w-full"
                    ><ComboboxInput
                        aria-label="Department"
                        placeholder="All departments"
                /></ComboboxAnchor>
                <ComboboxList
                    ><ComboboxEmpty>No departments found.</ComboboxEmpty
                    ><ComboboxItem
                        v-for="department in departments"
                        :key="department.id"
                        :value="department.name"
                        >{{ department.name }}</ComboboxItem
                    ></ComboboxList
                >
            </Combobox>
            <DateRangePicker
                v-model="dateRange"
                :allow-future="true"
                class="w-full sm:w-72"
            />
            <p
                v-if="rangeError"
                role="alert"
                class="w-full text-sm text-destructive"
            >
                {{ rangeError }}
            </p>
            <Button
                v-if="filters.employee_id"
                variant="outline"
                @click="
                    filters.employee_id = '';
                    navigate();
                "
                >Clear employee filter</Button
            >
        </div>
        <div
            v-if="['overview', 'workspace'].includes(section)"
            class="grid grid-cols-2 gap-2 lg:grid-cols-4"
        >
            <div
                v-for="metric in [
                    { label: 'Active employees', value: totals.active },
                    {
                        label: 'Present in period',
                        value: totals.present,
                    },
                    { label: 'On leave in period', value: totals.on_leave },
                    { label: 'Pending requests', value: totals.pending },
                ]"
                :key="metric.label"
                class="flex items-center justify-between gap-3 rounded-lg border bg-card px-3 py-2"
            >
                <p class="text-sm text-muted-foreground">{{ metric.label }}</p>
                <p class="text-xl font-semibold tabular-nums">
                    {{ metric.value }}
                </p>
            </div>
        </div>
        <div
            v-if="section === 'workspace'"
            class="rounded-xl border bg-card p-5"
        >
            <template v-if="own?.is_active"
                ><h2 class="font-semibold">Welcome, {{ own.name }}</h2>
                <div class="mt-4 flex flex-wrap gap-2">
                    <Button
                        :disabled="
                            clockForm.processing || clockState.checked_in
                        "
                        @click="punch('in')"
                        ><Clock class="size-4" /> Check in</Button
                    ><Button
                        variant="outline"
                        :disabled="
                            clockForm.processing ||
                            !clockState.checked_in ||
                            clockState.checked_out
                        "
                        @click="punch('out')"
                        >Check out</Button
                    ><Button variant="outline" @click="leaveForm"
                        >Apply for leave</Button
                    ><Button variant="outline" @click="attendanceForm"
                        >Request correction</Button
                    >
                </div>
                <InputError
                    v-for="(error, key) in clockForm.errors"
                    :key="key"
                    :message="error"
            /></template>
            <p v-else class="text-sm text-muted-foreground">
                Ask HR to link your account to an active employee record to use
                self-service.
            </p>
        </div>
        <template v-if="section === 'employees'">
            <div class="overflow-hidden rounded-xl border bg-card">
                <Table
                    ><TableHeader
                        ><TableRow
                            ><TableHead class="pl-5">Employee</TableHead
                            ><TableHead>Department</TableHead
                            ><TableHead>Designation</TableHead
                            ><TableHead>Status</TableHead
                            ><TableHead class="w-12 pr-5 text-right"
                                ><span class="sr-only">Actions</span></TableHead
                            ></TableRow
                        ></TableHeader
                    ><TableBody>
                        <TableRow
                            v-for="employee in employees.data"
                            :key="employee.id"
                            ><TableCell class="pl-5 whitespace-normal"
                                ><div class="flex items-center gap-3">
                                    <img
                                        v-if="
                                            mediaEnabled &&
                                            employeeAvatar(employee)
                                        "
                                        :src="employeeAvatar(employee)!.url"
                                        :alt="
                                            employeeAvatar(employee)!.alt ||
                                            employee.name
                                        "
                                        class="size-10 shrink-0 rounded-full border object-cover"
                                        loading="lazy"
                                    /><span
                                        v-else
                                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-medium text-muted-foreground"
                                        aria-hidden="true"
                                        >{{
                                            employee.name
                                                .slice(0, 1)
                                                .toUpperCase()
                                        }}</span
                                    >
                                    <div class="min-w-0">
                                        <button
                                            class="block w-full text-left font-medium break-words"
                                            @click="detail = employee"
                                        >
                                            {{ employee.name }}</button
                                        ><span
                                            class="text-xs text-muted-foreground"
                                            >{{ employee.employee_code }}</span
                                        >
                                    </div>
                                </div></TableCell
                            ><TableCell class="whitespace-normal">{{
                                employee.department?.name
                            }}</TableCell
                            ><TableCell class="whitespace-normal">{{
                                employee.designation
                            }}</TableCell
                            ><TableCell
                                ><Badge variant="secondary">{{
                                    employee.is_active ? 'Active' : 'Inactive'
                                }}</Badge></TableCell
                            ><TableCell
                                class="pr-5 text-right align-top"
                                @click.stop
                                ><DropdownMenu
                                    ><DropdownMenuTrigger as-child
                                        ><Button
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label="Open actions"
                                            ><MoreHorizontal
                                                class="size-4" /></Button></DropdownMenuTrigger
                                    ><DropdownMenuContent align="end"
                                        ><DropdownMenuItem
                                            @select="detail = employee"
                                            ><Eye class="size-4" /> View
                                            profile</DropdownMenuItem
                                        ><DropdownMenuItem
                                            v-if="can('employees.edit')"
                                            @select="employeeForm(employee)"
                                            ><Pencil class="size-4" /> Edit
                                            employee</DropdownMenuItem
                                        ><DropdownMenuItem
                                            v-if="
                                                mediaEnabled &&
                                                can('employees.edit') &&
                                                (can('media.create') ||
                                                    can('media.edit') ||
                                                    can('media.delete'))
                                            "
                                            @select="
                                                photoEmployeeId = employee.id
                                            "
                                            ><ImagePlus class="size-4" /> Manage
                                            photo</DropdownMenuItem
                                        ><DropdownMenuSeparator /><DropdownMenuItem
                                            @select="
                                                filters.employee_id = String(
                                                    employee.id,
                                                );
                                                navigate('reports');
                                            "
                                            ><Clock class="size-4" /> Attendance
                                            history</DropdownMenuItem
                                        ></DropdownMenuContent
                                    ></DropdownMenu
                                ></TableCell
                            ></TableRow
                        >
                        <TableRow v-if="!employees.data.length"
                            ><TableCell
                                colspan="5"
                                class="py-12 text-center text-muted-foreground"
                                >No employees found. Create departments and
                                schedules in Settings, then add your first
                                employee.</TableCell
                            ></TableRow
                        >
                    </TableBody></Table
                >
            </div>
            <div class="flex flex-wrap gap-2">
                <template
                    v-for="(link, i) in employees.links.length > 3
                        ? employees.links
                        : []"
                    :key="i"
                    ><Button
                        v-if="link.url"
                        as-child
                        :variant="link.active ? 'secondary' : 'outline'"
                        size="sm"
                        ><Link
                            :href="link.url"
                            :aria-current="link.active ? 'page' : undefined"
                            preserve-scroll
                            >{{ decodePaginationLabel(link.label) }}</Link
                        ></Button
                    ></template
                >
            </div>
        </template>
        <template
            v-if="
                ['overview', 'attendance', 'reports', 'workspace'].includes(
                    section,
                )
            "
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="font-semibold">
                        {{
                            section === 'reports'
                                ? 'Attendance report'
                                : 'Attendance'
                        }}
                    </h2>
                    <p
                        v-if="section === 'reports'"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Daily attendance for the selected date range. CSV
                        includes all matching rows.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button
                        v-if="can('attendance.manage')"
                        variant="outline"
                        @click="attendanceForm"
                        ><Plus class="size-4" /> Add record</Button
                    ><Button v-if="can('hr.export')" as-child variant="outline"
                        ><a
                            :href="
                                exportMethod.url({
                                    query: {
                                        from: filters.from || rangeStart,
                                        to: filters.to || rangeEnd,
                                        employee_id: filters.employee_id,
                                        department_id: filters.department_id,
                                    },
                                })
                            "
                            ><Download class="size-4" /> CSV</a
                        ></Button
                    >
                </div>
            </div>
            <div class="overflow-hidden rounded-xl border bg-card">
                <Table
                    ><TableHeader
                        ><TableRow
                            ><TableHead>Employee / date</TableHead
                            ><TableHead>Check-in</TableHead
                            ><TableHead>Check-out</TableHead
                            ><TableHead>Duration</TableHead
                            ><TableHead>Status</TableHead></TableRow
                        ></TableHeader
                    ><TableBody
                        ><TableRow
                            v-for="row in report"
                            :key="`${row.employee_id}-${row.date}`"
                            ><TableCell class="whitespace-normal"
                                ><span class="block font-medium break-words">{{
                                    row.name
                                }}</span
                                ><span class="text-xs text-muted-foreground"
                                    >{{ row.date }} · {{ row.timezone }}</span
                                ></TableCell
                            ><TableCell>{{ row.check_in || '—' }}</TableCell
                            ><TableCell>{{
                                row.check_out ||
                                (row.check_in ? 'Missing / in progress' : '—')
                            }}</TableCell
                            ><TableCell>{{
                                row.minutes === null
                                    ? '—'
                                    : `${Math.floor(row.minutes / 60)}h ${row.minutes % 60}m`
                            }}</TableCell
                            ><TableCell
                                ><Badge
                                    :variant="
                                        row.status === 'Absent'
                                            ? 'destructive'
                                            : 'secondary'
                                    "
                                    >{{ row.status }}</Badge
                                ><span
                                    v-if="row.late_minutes"
                                    class="ml-2 text-xs text-muted-foreground"
                                    >{{ row.late_minutes }}m late</span
                                ></TableCell
                            ></TableRow
                        ><TableRow v-if="!report.length"
                            ><TableCell
                                colspan="5"
                                class="py-10 text-center text-muted-foreground"
                                >{{
                                    reportPagination?.total
                                        ? 'No records on this page. Choose an earlier page.'
                                        : 'No scheduled employees for this period.'
                                }}</TableCell
                            ></TableRow
                        ></TableBody
                    ></Table
                >
            </div>
        </template>
        <template
            v-if="
                ['overview', 'attendance', 'reports', 'workspace'].includes(
                    section,
                )
            "
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p
                    v-if="reportPagination"
                    class="text-sm text-muted-foreground"
                    aria-live="polite"
                >
                    {{
                        reportPagination.total
                            ? `Showing ${reportPagination.from ?? 0}–${reportPagination.to ?? 0} of ${reportPagination.total} records`
                            : 'No records to show'
                    }}
                </p>
                <nav
                    v-if="reportLinks.length > 3"
                    aria-label="Attendance report pages"
                    class="flex flex-wrap items-center gap-1"
                >
                    <template
                        v-for="(link, i) in reportLinks.length > 3
                            ? reportLinks
                            : []"
                        :key="i"
                        ><Button
                            :as-child="!!link.url"
                            :disabled="!link.url"
                            :variant="link.active ? 'secondary' : 'outline'"
                            size="sm"
                            ><Link
                                v-if="link.url"
                                :href="link.url"
                                :aria-current="link.active ? 'page' : undefined"
                                preserve-scroll
                                >{{ decodePaginationLabel(link.label) }}</Link
                            ><span v-else>{{
                                decodePaginationLabel(link.label)
                            }}</span></Button
                        ></template
                    >
                </nav>
            </div>
        </template>
        <template v-if="['overview', 'leave', 'workspace'].includes(section)">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold">Leave requests</h2>
                <div class="flex gap-2">
                    <select
                        v-model="filters.status"
                        @change="navigate()"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        aria-label="Leave status"
                    >
                        <option value="">All statuses</option>
                        <option
                            v-for="status in [
                                'pending',
                                'approved',
                                'rejected',
                                'cancelled',
                                'cancellation_requested',
                            ]"
                            :key="status"
                        >
                            {{ status }}
                        </option></select
                    ><Button
                        v-if="
                            can('leave.manage') ||
                            (can('hr.self') && own?.is_active)
                        "
                        @click="leaveForm"
                        ><Plus class="size-4" /> Apply</Button
                    >
                </div>
            </div>
            <div class="overflow-hidden rounded-xl border bg-card">
                <Table
                    ><TableHeader
                        ><TableRow
                            ><TableHead>Employee</TableHead
                            ><TableHead>Leave / dates</TableHead
                            ><TableHead>Days</TableHead
                            ><TableHead>Status</TableHead
                            ><TableHead class="w-12 pr-5 text-right"
                                ><span class="sr-only">Actions</span></TableHead
                            ></TableRow
                        ></TableHeader
                    ><TableBody
                        ><TableRow v-for="row in leaves.data" :key="row.id"
                            ><TableCell class="whitespace-normal">{{
                                row.employee.name
                            }}</TableCell
                            ><TableCell class="whitespace-normal"
                                ><span class="block font-medium">{{
                                    row.leave_type.name
                                }}</span
                                ><span class="text-xs text-muted-foreground"
                                    >{{ row.start_date }} →
                                    {{ row.end_date }}</span
                                >
                                <p
                                    class="line-clamp-2 text-xs break-words text-muted-foreground"
                                >
                                    {{ row.reason }}
                                </p>
                                <p v-if="row.review_note" class="text-xs">
                                    Review: {{ row.review_note }}
                                </p></TableCell
                            ><TableCell>{{ row.dates.length }}</TableCell
                            ><TableCell
                                ><Badge variant="secondary">{{
                                    row.status.replaceAll('_', ' ')
                                }}</Badge></TableCell
                            ><TableCell
                                class="pr-5 text-right align-top"
                                @click.stop
                                ><DropdownMenu
                                    ><DropdownMenuTrigger as-child
                                        ><Button
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label="Open actions"
                                            ><MoreHorizontal
                                                class="size-4" /></Button></DropdownMenuTrigger
                                    ><DropdownMenuContent align="end"
                                        ><template
                                            v-if="
                                                reviewable(row) &&
                                                [
                                                    'pending',
                                                    'cancellation_requested',
                                                ].includes(row.status)
                                            "
                                            ><DropdownMenuItem
                                                @select="
                                                    decision(row, 'approve')
                                                "
                                                ><Check class="size-4" />
                                                Approve</DropdownMenuItem
                                            ><DropdownMenuItem
                                                @select="
                                                    decision(row, 'reject')
                                                "
                                                ><X class="size-4" />
                                                Reject</DropdownMenuItem
                                            ></template
                                        ><DropdownMenuItem
                                            v-if="
                                                [
                                                    'pending',
                                                    'approved',
                                                ].includes(row.status) &&
                                                (can('leave.manage') ||
                                                    row.employee.admin_id ===
                                                        admin?.id)
                                            "
                                            @select="decision(row, 'cancel')"
                                            ><X class="size-4" />
                                            {{
                                                row.status === 'approved'
                                                    ? 'Request cancellation'
                                                    : 'Cancel request'
                                            }}</DropdownMenuItem
                                        ></DropdownMenuContent
                                    ></DropdownMenu
                                ></TableCell
                            ></TableRow
                        ><TableRow v-if="!leaves.data.length"
                            ><TableCell
                                colspan="5"
                                class="py-10 text-center text-muted-foreground"
                                >No leave requests match your
                                filters.</TableCell
                            ></TableRow
                        ></TableBody
                    ></Table
                >
            </div>
            <div class="flex flex-wrap gap-2">
                <template
                    v-for="(link, i) in leaves.links.length > 3
                        ? leaves.links
                        : []"
                    :key="i"
                    ><Button
                        v-if="link.url"
                        as-child
                        :variant="link.active ? 'secondary' : 'outline'"
                        size="sm"
                        ><Link
                            :href="link.url"
                            :aria-current="link.active ? 'page' : undefined"
                            preserve-scroll
                            >{{ decodePaginationLabel(link.label) }}</Link
                        ></Button
                    ></template
                >
            </div>
            <h2 class="font-semibold">
                Annual leave balances · {{ date.slice(0, 4) }}
            </h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="row in balance"
                    :key="row.id"
                    class="rounded-xl border bg-card p-4"
                >
                    <p class="font-medium">
                        {{ row.employee.name }} · {{ row.leave_type.name }}
                    </p>
                    <p class="mt-2 text-2xl font-semibold">
                        {{ row.days - row.reserved }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >days available</span
                        >
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ row.days }} allocated · {{ row.reserved }} reserved /
                        approved
                    </p>
                </div>
                <p v-if="!balance.length" class="text-sm text-muted-foreground">
                    HR has not allocated leave for this year.
                </p>
            </div>
        </template>
        <template
            v-if="
                ['attendance', 'overview', 'workspace'].includes(section) &&
                corrections.data.length
            "
        >
            <h2 class="font-semibold">Pending attendance corrections</h2>
            <div
                v-for="row in corrections.data"
                :key="row.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4"
            >
                <div class="min-w-0">
                    <p class="font-medium">
                        {{ row.employee.name }} · {{ row.work_date }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Requested: {{ row.check_in }} →
                        {{ row.check_out || 'No check-out' }} (UTC)
                    </p>
                    <p class="text-sm break-words text-muted-foreground">
                        {{ row.reason }}
                    </p>
                </div>
                <div v-if="can('attendance.manage')" class="flex gap-2">
                    <Button
                        variant="outline"
                        @click="decision(row, 'reject', true)"
                        >Reject</Button
                    ><Button @click="decision(row, 'approve', true)"
                        >Review approval</Button
                    >
                </div>
            </div>
            <div class="flex gap-2">
                <template
                    v-for="(link, i) in corrections.links.length > 3
                        ? corrections.links
                        : []"
                    :key="i"
                    ><Button
                        v-if="link.url"
                        as-child
                        variant="outline"
                        size="sm"
                        ><Link :href="link.url">{{
                            decodePaginationLabel(link.label)
                        }}</Link></Button
                    ></template
                >
            </div>
        </template>
        <section
            v-if="section === 'leave'"
            class="space-y-4 rounded-xl border bg-card p-5"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold">Team leave calendar</h2>
                <Input
                    v-model="filters.month"
                    @update:model-value="applyFilters()"
                    type="month"
                    class="w-44"
                    aria-label="Calendar month"
                />
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <article
                    v-for="day in [
                        ...new Set(calendar.map((event) => event.date)),
                    ]"
                    :key="day"
                    class="min-w-0 rounded-lg border p-3"
                >
                    <p class="text-sm font-semibold">{{ day }}</p>
                    <p
                        v-for="(event, i) in calendar.filter(
                            (event) => event.date === day,
                        )"
                        :key="i"
                        class="mt-2 text-sm break-words text-muted-foreground"
                    >
                        {{ event.name }}
                    </p>
                </article>
            </div>
            <p v-if="!calendar.length" class="text-sm text-muted-foreground">
                No approved leave this month.
            </p>
        </section>
        <template v-if="section === 'settings'">
            <div class="rounded-xl border bg-muted/30 p-4 text-sm">
                Start with a department and a work schedule, then add employees.
                Schedule assignments preserve their original hours and timezone.
                Add a new schedule when policy changes.
            </div>
            <div class="grid gap-5 lg:grid-cols-2">
                <section
                    v-for="group in [
                        {
                            title: 'Departments',
                            kind: 'department',
                            rows: departments,
                        },
                        {
                            title: 'Work schedules',
                            kind: 'schedule',
                            rows: schedules,
                        },
                        { title: 'Holidays', kind: 'holiday', rows: holidays },
                        {
                            title: 'Leave types',
                            kind: 'leave-type',
                            rows: leaveTypes,
                        },
                    ]"
                    :key="group.kind"
                    class="rounded-xl border bg-card p-5"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-semibold">{{ group.title }}</h2>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="setting(group.kind)"
                            ><Plus class="size-4" /> Add</Button
                        >
                    </div>
                    <div
                        v-for="row in group.rows"
                        :key="row.id"
                        class="border-t py-3 text-sm"
                    >
                        <DropdownMenu
                            ><DropdownMenuTrigger as-child
                                ><Button
                                    class="float-right"
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label="Open actions"
                                    ><MoreHorizontal
                                        class="size-4" /></Button></DropdownMenuTrigger
                            ><DropdownMenuContent align="end"
                                ><DropdownMenuItem
                                    @select="editSetting(group.kind, row)"
                                    ><Pencil class="size-4" />
                                    Edit</DropdownMenuItem
                                ><DropdownMenuSeparator /><DropdownMenuItem
                                    variant="destructive"
                                    class="text-destructive focus:text-destructive"
                                    @select="
                                        removalForm.clearErrors();
                                        removal = {
                                            kind: group.kind,
                                            id: row.id,
                                            name: row.name,
                                        };
                                    "
                                    ><X class="size-4" />
                                    Delete</DropdownMenuItem
                                ></DropdownMenuContent
                            ></DropdownMenu
                        >
                        {{ row.name
                        }}<span
                            v-if="'date' in row"
                            class="ml-2 text-muted-foreground"
                            >{{ row.date }}</span
                        ><span
                            v-if="'start_time' in row"
                            class="ml-2 text-muted-foreground"
                            >{{ row.start_time }}–{{ row.end_time }} ·
                            {{ row.timezone }}</span
                        >
                    </div>
                    <p
                        v-if="!group.rows.length"
                        class="text-sm text-muted-foreground"
                    >
                        No records yet.
                    </p>
                </section>
            </div>
            <section class="rounded-xl border bg-card p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-semibold">Annual leave allocations</h2>
                    <Button variant="outline" @click="setting('allocation')"
                        >Set allowance</Button
                    >
                </div>
                <p
                    v-for="row in allocations"
                    :key="row.id"
                    class="border-t py-3 text-sm"
                >
                    {{ row.employee.name }} · {{ row.leave_type.name }} ·
                    {{ row.year }} — {{ row.days }} days ({{ row.reserved }}
                    reserved)
                </p>
            </section>
        </template>
        <Drawer
            :open="!!detail"
            @update:open="
                (value) => {
                    if (!value) detail = null;
                }
            "
            ><DrawerContent
                class="max-h-[92vh] overflow-y-auto [color-scheme:light] md:inset-x-auto md:right-2 md:left-64 dark:[color-scheme:dark]"
                ><DrawerHeader class="shrink-0"
                    ><div class="flex items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/20"
                            ><Users class="size-5"
                        /></span>
                        <div>
                            <DrawerTitle>{{ detail?.name }}</DrawerTitle
                            ><DrawerDescription
                                >{{ detail?.employee_code }} ·
                                {{ detail?.designation }}</DrawerDescription
                            >
                        </div>
                    </div></DrawerHeader
                >
                <div v-if="detail" class="grid gap-5 px-6 pb-6 sm:grid-cols-2">
                    <div
                        v-if="mediaEnabled && employeeAvatar(detail)"
                        class="flex items-center gap-4 sm:col-span-2"
                    >
                        <img
                            :src="employeeAvatar(detail)!.url"
                            :alt="employeeAvatar(detail)!.alt || detail.name"
                            class="size-20 rounded-xl border object-cover"
                        />
                        <div>
                            <p class="font-medium">{{ detail.name }}</p>
                            <Button
                                v-if="
                                    can('employees.edit') && can('media.create')
                                "
                                size="sm"
                                variant="outline"
                                class="mt-2"
                                @click="
                                    photoEmployeeId = detail.id;
                                    detail = null;
                                "
                                ><ImagePlus class="size-4" /> Manage
                                photo</Button
                            >
                        </div>
                    </div>
                    <div class="min-w-0 rounded-xl border p-4">
                        <p class="text-sm text-muted-foreground">Contact</p>
                        <p class="break-words">
                            {{ detail.email || 'No email' }}
                        </p>
                        <p>{{ detail.phone || 'No phone' }}</p>
                    </div>
                    <div class="min-w-0 rounded-xl border p-4">
                        <p class="text-sm text-muted-foreground">Employment</p>
                        <p>{{ detail.department.name }}</p>
                        <p>Joined {{ detail.joined_on }}</p>
                        <p>{{ detail.is_active ? 'Active' : 'Inactive' }}</p>
                    </div>
                    <Button
                        variant="outline"
                        @click="
                            filters.employee_id = String(detail.id);
                            detail = null;
                            navigate('reports');
                        "
                        >View attendance history</Button
                    ><Button
                        variant="outline"
                        @click="
                            filters.employee_id = String(detail.id);
                            detail = null;
                            navigate('leave');
                        "
                        >View leave history</Button
                    >
                </div></DrawerContent
            ></Drawer
        >
        <Drawer
            :open="photoEmployeeId !== null"
            @update:open="
                (value) => {
                    if (!value) photoEmployeeId = null;
                }
            "
        >
            <DrawerContent
                class="max-h-[92vh] overflow-y-auto md:inset-x-auto md:right-2 md:left-64"
            >
                <DrawerHeader
                    ><div class="flex items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/20"
                            ><ImagePlus class="size-5"
                        /></span>
                        <div>
                            <DrawerTitle>Employee photo</DrawerTitle
                            ><DrawerDescription
                                >{{ photoEmployee?.name || 'Employee saved' }} ·
                                One profile photo per
                                employee.</DrawerDescription
                            >
                        </div>
                    </div></DrawerHeader
                >
                <div class="px-6 pb-6">
                    <MediaManager
                        v-if="photoEmployee && mediaEnabled"
                        :key="photoEmployee.id"
                        :items="
                            photoEmployee.media.filter(
                                (image) => image.collection === 'avatar',
                            )
                        "
                        :multiple="false"
                        :owner="{ type: 'employee', id: photoEmployee.id }"
                        collection="avatar"
                        folder="Employees"
                        :editable="can('employees.edit')"
                        empty-message="Add a profile photo to help your team recognize this employee."
                    />
                    <p v-else class="text-sm text-muted-foreground">
                        Find this employee in the list, then choose Manage photo
                        from their actions.
                    </p>
                    <DrawerFooter class="px-0"
                        ><Button
                            variant="outline"
                            @click="photoEmployeeId = null"
                            >Done</Button
                        ></DrawerFooter
                    >
                </div>
            </DrawerContent>
        </Drawer>
        <Drawer
            :open="open"
            @update:open="
                (value) => {
                    if (!value && !form.processing) cancelForm();
                }
            "
            ><DrawerContent
                class="max-h-[92vh] overflow-y-auto [color-scheme:light] md:inset-x-auto md:right-2 md:left-64 dark:[color-scheme:dark]"
                ><DrawerHeader class="shrink-0"
                    ><div class="flex items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/20"
                            ><Users class="size-5"
                        /></span>
                        <div class="min-w-0">
                            <DrawerTitle>{{ title }}</DrawerTitle
                            ><DrawerDescription class="break-words">{{
                                description
                            }}</DrawerDescription>
                        </div>
                    </div></DrawerHeader
                >
                <form
                    class="flex min-h-0 flex-1 flex-col overflow-hidden"
                    @submit.prevent="submit"
                >
                    <div
                        class="min-h-0 flex-1 space-y-5 overflow-y-auto pb-4"
                        data-hr-form-fields
                        ref="formFields"
                    >
                        <div
                            class="grid gap-5 rounded-xl border p-4 md:grid-cols-2"
                            :class="
                                ['schedule', 'leave-type'].includes(
                                    String(form.kind),
                                )
                                    ? 'gap-4'
                                    : ''
                            "
                        >
                            <div
                                v-for="input in fields"
                                :key="input.key"
                                class="min-w-0 space-y-2"
                                :class="
                                    input.type === 'textarea' ||
                                    form.kind === 'leave-type' ||
                                    (form.kind === 'department' &&
                                        input.key === 'name') ||
                                    (form.kind === 'schedule' &&
                                        ['name', 'weekdays'].includes(
                                            input.key,
                                        ))
                                        ? 'md:col-span-2'
                                        : ''
                                "
                            >
                                <Label
                                    :id="`hr-label-${input.key}`"
                                    :for="
                                        input.type === 'pay-status'
                                            ? undefined
                                            : `hr-${input.key}`
                                    "
                                    >{{ input.label }}</Label
                                >
                                <div
                                    v-if="input.options"
                                    class="flex min-w-0 items-center gap-2"
                                >
                                    <Combobox
                                        :model-value="
                                            String(form[input.key] || '')
                                        "
                                        @update:model-value="
                                            (value) => {
                                                form[input.key] = value
                                                    ? Number(value)
                                                    : '';
                                            }
                                        "
                                        class="min-w-0 flex-1"
                                    >
                                        <ComboboxAnchor class="w-full"
                                            ><ComboboxInput
                                                :id="`hr-${input.key}`"
                                                :aria-label="input.label"
                                                placeholder="Search or select…"
                                                :display-value="
                                                    (value) =>
                                                        input.options?.find(
                                                            (option) =>
                                                                String(
                                                                    option.value,
                                                                ) ===
                                                                String(value),
                                                        )?.label || ''
                                                "
                                        /></ComboboxAnchor>
                                        <ComboboxList
                                            ><ComboboxEmpty
                                                >No matching
                                                options.</ComboboxEmpty
                                            ><ComboboxItem
                                                v-for="option in input.options"
                                                :key="option.value"
                                                :value="String(option.value)"
                                                >{{
                                                    option.label
                                                }}</ComboboxItem
                                            ></ComboboxList
                                        >
                                    </Combobox>
                                    <Button
                                        v-if="relatedKind(input.key)"
                                        type="button"
                                        size="icon"
                                        variant="outline"
                                        :aria-label="`Add ${input.label}`"
                                        :data-related-key="input.key"
                                        @click="addRelated(input)"
                                        ><Plus class="size-4"
                                    /></Button>
                                </div>
                                <div
                                    v-else-if="input.type === 'pay-status'"
                                    role="radiogroup"
                                    :aria-labelledby="`hr-label-${input.key}`"
                                    class="grid gap-2 sm:grid-cols-2"
                                >
                                    <label
                                        v-for="choice in [
                                            {
                                                value: true,
                                                title: 'Paid leave',
                                                description:
                                                    'Classify this type as paid in HR records.',
                                            },
                                            {
                                                value: false,
                                                title: 'Unpaid leave',
                                                description:
                                                    'Classify this type as unpaid in HR records.',
                                            },
                                        ]"
                                        :key="String(choice.value)"
                                        class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 has-[:checked]:border-primary/50 has-[:checked]:bg-primary/10"
                                    >
                                        <input
                                            v-model="form[input.key]"
                                            type="radio"
                                            :name="input.key"
                                            :value="choice.value"
                                            class="mt-1 accent-primary"
                                        />
                                        <span class="min-w-0"
                                            ><span
                                                class="block text-sm font-medium"
                                                >{{ choice.title }}</span
                                            ><span
                                                class="mt-1 block text-xs text-muted-foreground"
                                                >{{ choice.description }}</span
                                            ></span
                                        >
                                    </label>
                                </div>
                                <input
                                    v-else-if="input.type === 'checkbox'"
                                    :id="`hr-${input.key}`"
                                    v-model="form[input.key]"
                                    type="checkbox"
                                    class="ml-3 size-4"
                                />
                                <div
                                    v-else-if="input.type === 'weekdays'"
                                    class="flex flex-wrap gap-2"
                                    role="group"
                                    aria-label="Working days"
                                >
                                    <label
                                        v-for="(day, i) in [
                                            'Mon',
                                            'Tue',
                                            'Wed',
                                            'Thu',
                                            'Fri',
                                            'Sat',
                                            'Sun',
                                        ]"
                                        :key="day"
                                        class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm has-[:checked]:border-primary/50 has-[:checked]:bg-primary/10"
                                        ><input
                                            v-model="form[input.key]"
                                            type="checkbox"
                                            :value="i + 1"
                                            class="accent-primary"
                                        />{{ day }}</label
                                    >
                                </div>
                                <textarea
                                    v-else-if="input.type === 'textarea'"
                                    :id="`hr-${input.key}`"
                                    :value="String(form[input.key] ?? '')"
                                    @input="
                                        form[input.key] = (
                                            $event.target as HTMLTextAreaElement
                                        ).value
                                    "
                                    rows="3"
                                    class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                />
                                <div
                                    v-else-if="
                                        form.kind === 'schedule' &&
                                        input.key === 'grace_minutes'
                                    "
                                    class="relative"
                                >
                                    <Input
                                        :id="`hr-${input.key}`"
                                        :model-value="
                                            form[input.key] as string | number
                                        "
                                        type="number"
                                        min="0"
                                        max="120"
                                        step="1"
                                        inputmode="numeric"
                                        aria-describedby="grace-period-help"
                                        class="pr-20"
                                        @update:model-value="
                                            (value) => (form[input.key] = value)
                                        "
                                    />
                                    <span
                                        class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
                                        >minutes</span
                                    >
                                </div>
                                <Input
                                    v-else
                                    :id="`hr-${input.key}`"
                                    :model-value="
                                        form[input.key] as string | number
                                    "
                                    :type="input.type || 'text'"
                                    :placeholder="
                                        form.kind === 'leave-type' &&
                                        input.key === 'name'
                                            ? 'e.g. Annual leave'
                                            : undefined
                                    "
                                    @update:model-value="
                                        (value) => (form[input.key] = value)
                                    "
                                />
                                <p
                                    v-if="
                                        form.kind === 'schedule' &&
                                        input.key === 'grace_minutes'
                                    "
                                    class="text-xs text-muted-foreground"
                                    id="grace-period-help"
                                >
                                    <template v-if="lateAfter"
                                        >Check-ins at or after
                                        {{ lateAfter }} are marked late (counted
                                        in whole minutes).</template
                                    >
                                    <template v-else
                                        >Enter a whole number between 0 and 120
                                        minutes.</template
                                    >
                                </p>
                                <p
                                    v-if="
                                        form.kind === 'schedule' &&
                                        input.key === 'timezone'
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    Use a time zone such as Asia/Dhaka.
                                </p>
                                <p
                                    v-if="
                                        input.key === 'work_schedule_id' &&
                                        method === 'put'
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    Leave empty to keep the existing schedule.
                                </p>
                                <InputError :message="form.errors[input.key]" />
                            </div>
                        </div>
                        <div role="alert">
                            <InputError
                                v-for="[key, error] in visibleFormErrors"
                                :key="key"
                                :message="error"
                            />
                        </div>
                    </div>
                    <DrawerFooter
                        class="shrink-0 flex-row justify-end border-t bg-background px-0 py-4"
                        ><Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            @click="cancelForm()"
                            >{{
                                parents.length
                                    ? 'Back without saving'
                                    : 'Cancel'
                            }}</Button
                        ><Button type="submit" :disabled="form.processing">{{
                            form.processing
                                ? 'Saving…'
                                : title === 'Add manager'
                                  ? 'Save manager & return'
                                  : 'Confirm & save'
                        }}</Button></DrawerFooter
                    >
                </form></DrawerContent
            ></Drawer
        >
    </div>
</template>
