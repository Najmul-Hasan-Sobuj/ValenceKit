<script setup lang="ts">
import { Head, Link, router, useForm, useHttp } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import {
    Copy,
    ExternalLink,
    FileSignature,
    MoreHorizontal,
    Mail,
    Pencil,
    Plus,
    Trash2,
    X,
    Eye,
    RefreshCw,
} from '@lucide/vue';
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { toast } from 'vue-sonner';
import ProposalDocument, {
    type ProposalPreviewProps,
} from '@/pages/public/Proposal.vue';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
    SheetFooter,
    SheetClose,
} from '@/components/ui/sheet';
import InputError from '@/components/InputError.vue';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermissions } from '@/composables/usePermissions';
import { decodePaginationLabel } from '@/lib/decodePaginationLabel';
import {
    destroy,
    index,
    store,
    update,
    invite,
    preview,
    revise,
} from '@/routes/admin/proposals';

type Item = {
    description: string;
    quantity: number | string;
    unit_price_minor: number | string;
};
type Proposal = {
    id: number;
    public_id: string;
    number: string;
    type: 'proposal' | 'quotation' | 'contract';
    status:
        | 'draft'
        | 'sent'
        | 'accepted'
        | 'rejected'
        | 'expired'
        | 'superseded';
    title: string;
    client_name: string;
    client_email: string;
    currency: string;
    items: Item[];
    subtotal_minor: number;
    discount_minor: number;
    tax_rate_basis_points: number;
    tax_minor: number;
    total_minor: number;
    issued_on: string;
    valid_until: string;
    terms: string | null;
    notes: string | null;
    public_url: string;
};

const props = defineProps<{
    filters: { search: string; type: string; status: string };
    proposals: {
        data: Proposal[];
        meta: {
            from: number | null;
            to: number | null;
            total: number;
            last_page: number;
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
}>();

const { can } = usePermissions();
const search = ref(props.filters.search);
const typeFilter = ref(props.filters.type);
const statusFilter = ref(props.filters.status);
watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search;
        typeFilter.value = filters.type;
        statusFilter.value = filters.status;
    },
);
const drawerOpen = ref(false);
const editing = ref<Proposal | null>(null);
const deleting = ref<Proposal | null>(null);
const invitation = useForm({});
const previewOpen = ref(false);
const previewId = ref<string | null>(null);
const previewData = ref<ProposalPreviewProps | null>(null);
const previewError = ref('');
const previewRequest = useHttp<{}, ProposalPreviewProps>({});
let previewRequestId = 0;

function loadPreview(publicId: string): void {
    previewRequest.cancel();
    const requestId = ++previewRequestId;
    previewId.value = publicId;
    if (
        !previewOpen.value ||
        previewData.value?.proposal.public_id !== publicId
    )
        previewData.value = null;
    previewError.value = '';
    previewOpen.value = true;
    void previewRequest
        .get(preview.url(publicId), {
            onSuccess: (result) => {
                if (requestId === previewRequestId && previewOpen.value)
                    previewData.value = result;
            },
            onHttpException: () => {
                if (requestId === previewRequestId)
                    previewError.value =
                        'This document could not be loaded. It may have been removed or your access may have changed.';
                return false;
            },
            onNetworkError: () => {
                if (requestId === previewRequestId)
                    previewError.value =
                        'Could not connect. Check your connection and try again.';
                return false;
            },
        })
        .catch(() => {});
}
watch(previewOpen, (open) => {
    if (!open) {
        ++previewRequestId;
        previewRequest.cancel();
    }
});
onBeforeUnmount(() => previewRequest.cancel());
function inviteClient(row: Proposal): void {
    invitation.post(invite.url(row.public_id), { preserveScroll: true });
}

const blankItem = (): Item => ({
    description: '',
    quantity: 1,
    unit_price_minor: '',
});
const form = useForm({
    number: '',
    type: 'proposal',
    status: 'draft',
    title: '',
    client_name: '',
    client_email: '',
    currency: 'BDT',
    items: [blankItem()],
    discount_minor: '0',
    tax_rate_basis_points: 0,
    issued_on: '',
    valid_until: '',
    terms: '',
    notes: '',
});

watchDebounced(
    [search, typeFilter, statusFilter],
    () => {
        if (
            search.value === props.filters.search &&
            typeFilter.value === props.filters.type &&
            statusFilter.value === props.filters.status
        )
            return;
        router.get(
            index.url(),
            {
                search: search.value || undefined,
                type: typeFilter.value === 'all' ? undefined : typeFilter.value,
                status:
                    statusFilter.value === 'all'
                        ? undefined
                        : statusFilter.value,
            },
            { preserveState: true, replace: true },
        );
    },
    { debounce: 300 },
);

function amount(minor: number, currency: string): string {
    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
        }).format(minor / 100);
    } catch {
        return `${(minor / 100).toFixed(2)} ${currency}`;
    }
}
const fromMinor = (minor: number) => (minor / 100).toFixed(2);
function toMinor(value: number | string): number | null {
    const text = String(value);
    if (!/^\d+(\.\d{1,2})?$/.test(text)) return null;
    const [whole, fraction = ''] = text.split('.');
    const result = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
    return Number.isSafeInteger(result) ? result : null;
}

const previewSubtotal = computed(() =>
    form.items.reduce((total, item) => {
        const price = toMinor(item.unit_price_minor) ?? 0;
        return total + Math.round(Number(item.quantity || 0) * price);
    }, 0),
);
const previewDiscount = computed(() => toMinor(form.discount_minor) ?? 0);
const previewTax = computed(() =>
    Math.round(
        (Math.max(0, previewSubtotal.value - previewDiscount.value) *
            Number(form.tax_rate_basis_points || 0)) /
            100,
    ),
);
const previewTotal = computed(
    () =>
        Math.max(0, previewSubtotal.value - previewDiscount.value) +
        previewTax.value,
);
const pagination = computed(() => props.proposals.meta);

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.items = [blankItem()];
    form.clearErrors();
    drawerOpen.value = true;
}

function openEdit(row: Proposal): void {
    editing.value = row;
    form.clearErrors();
    form.number = row.number;
    form.type = row.type;
    form.status = row.status === 'expired' ? 'sent' : row.status;
    form.title = row.title;
    form.client_name = row.client_name;
    form.client_email = row.client_email;
    form.currency = row.currency;
    form.items = row.items.map((item) => ({
        ...item,
        unit_price_minor: fromMinor(Number(item.unit_price_minor)),
    }));
    form.discount_minor = fromMinor(row.discount_minor);
    form.tax_rate_basis_points = row.tax_rate_basis_points / 100;
    form.issued_on = row.issued_on;
    form.valid_until = row.valid_until;
    form.terms = row.terms ?? '';
    form.notes = row.notes ?? '';
    drawerOpen.value = true;
}

function submit(): void {
    const itemPrices = form.items.map((item) => toMinor(item.unit_price_minor));
    const discount = toMinor(form.discount_minor);
    if (itemPrices.some((price) => price === null) || discount === null) {
        form.setError(
            'items',
            'Enter valid amounts with up to two decimal places.',
        );
        return;
    }

    form.transform((data) => ({
        ...data,
        items: data.items.map((item, i) => ({
            ...item,
            quantity: Number(item.quantity),
            unit_price_minor: itemPrices[i],
        })),
        discount_minor: discount,
        tax_rate_basis_points: Math.round(
            Number(data.tax_rate_basis_points) * 100,
        ),
    }));
    const options = {
        onSuccess: () => {
            drawerOpen.value = false;
            form.reset();
        },
    };
    if (editing.value) {
        form.put(update.url(editing.value.public_id), options);
    } else {
        form.post(store.url(), options);
    }
}

function removeItem(position: number): void {
    if (form.items.length > 1) form.items.splice(position, 1);
}

function confirmDelete(): void {
    if (!deleting.value) return;
    router.delete(destroy.url(deleting.value.public_id), {
        onFinish: () => {
            deleting.value = null;
        },
    });
}

async function copyLink(row: Proposal): Promise<void> {
    try {
        await navigator.clipboard.writeText(row.public_url);
        toast.success('Client link copied');
    } catch {
        toast.error('Could not copy the link. Please check clipboard access.');
    }
}

const statusClass = (status: Proposal['status']) =>
    ({
        draft: 'bg-muted text-muted-foreground',
        sent: 'bg-blue-500/10 text-blue-700 dark:text-blue-300',
        accepted: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        rejected: 'bg-destructive/10 text-destructive',
        expired: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
        superseded: 'bg-muted text-muted-foreground',
    })[status];
</script>

<template>
    <div class="space-y-6 p-4 md:p-6">
        <Head title="Proposals & Contracts" />
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Proposals & Contracts</h1>
                <p class="text-sm text-muted-foreground">
                    Prepare, share, and track client-facing commercial
                    documents.
                </p>
            </div>
            <Button v-if="can('proposals.create')" @click="openCreate"
                ><Plus class="size-4" /> Create document</Button
            >
        </header>

        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_11rem_11rem]">
            <Input
                v-model="search"
                aria-label="Search documents"
                placeholder="Search number, title, or client"
            />
            <select
                v-model="typeFilter"
                aria-label="Filter by type"
                class="h-9 rounded-md border bg-background px-3 text-sm"
            >
                <option value="all">All types</option>
                <option value="proposal">Proposals</option>
                <option value="quotation">Quotations</option>
                <option value="contract">Contracts</option>
            </select>
            <select
                v-model="statusFilter"
                aria-label="Filter by status"
                class="h-9 rounded-md border bg-background px-3 text-sm"
            >
                <option value="all">All statuses</option>
                <option value="draft">Draft</option>
                <option value="sent">Sent</option>
                <option value="accepted">Accepted</option>
                <option value="rejected">Rejected</option>
                <option value="expired">Expired</option>
                <option value="superseded">Superseded</option>
            </select>
        </div>

        <div class="overflow-hidden rounded-xl border bg-card">
            <Table>
                <TableHeader
                    ><TableRow>
                        <TableHead>Document</TableHead
                        ><TableHead class="hidden md:table-cell"
                            >Client</TableHead
                        ><TableHead>Status</TableHead
                        ><TableHead class="hidden lg:table-cell"
                            >Valid until</TableHead
                        ><TableHead class="hidden text-right sm:table-cell"
                            >Total</TableHead
                        >
                        <TableHead class="w-12 pr-5 text-right"
                            ><span class="sr-only">Actions</span></TableHead
                        >
                    </TableRow></TableHeader
                >
                <TableBody>
                    <TableRow v-for="row in proposals.data" :key="row.id">
                        <TableCell class="whitespace-normal"
                            ><div class="min-w-0">
                                <button
                                    class="line-clamp-2 w-full text-left font-medium break-words underline-offset-4 hover:underline focus-visible:rounded focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    @click="loadPreview(row.public_id)"
                                >
                                    {{ row.title }}
                                </button>
                                <p
                                    class="text-xs text-muted-foreground uppercase"
                                >
                                    {{ row.number }} · {{ row.type }}
                                </p>
                                <p
                                    class="mt-1 line-clamp-1 text-xs text-muted-foreground md:hidden"
                                >
                                    {{ row.client_name }}
                                </p>
                                <p class="mt-1 text-xs font-medium sm:hidden">
                                    {{ amount(row.total_minor, row.currency) }}
                                </p>
                            </div></TableCell
                        >
                        <TableCell
                            class="hidden whitespace-normal md:table-cell"
                            ><div class="min-w-0">
                                <p
                                    class="line-clamp-2 block w-full break-words"
                                >
                                    {{ row.client_name }}
                                </p>
                                <p
                                    class="block w-full truncate text-xs text-muted-foreground"
                                >
                                    {{ row.client_email }}
                                </p>
                            </div></TableCell
                        >
                        <TableCell
                            ><Badge
                                variant="outline"
                                :class="statusClass(row.status)"
                                >{{ row.status }}</Badge
                            ></TableCell
                        >
                        <TableCell class="hidden lg:table-cell">{{
                            row.valid_until
                        }}</TableCell>
                        <TableCell
                            class="hidden text-right font-medium sm:table-cell"
                            >{{
                                amount(row.total_minor, row.currency)
                            }}</TableCell
                        >
                        <TableCell
                            class="pr-5 text-right align-top"
                            @click.stop
                        >
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
                                        @select="loadPreview(row.public_id)"
                                    >
                                        <Eye class="size-4" />Admin preview
                                        &amp; history
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="
                                            can('proposals.create') &&
                                            can('proposals.edit')
                                        "
                                        @select="
                                            invitation.post(
                                                revise.url(row.public_id),
                                            )
                                        "
                                        ><Copy class="size-4" /> Create draft
                                        revision</DropdownMenuItem
                                    >
                                    <DropdownMenuItem
                                        v-if="
                                            row.status !== 'draft' &&
                                            can('proposals.edit')
                                        "
                                        :disabled="invitation.processing"
                                        @select="inviteClient(row)"
                                        ><Mail class="size-4" /> Invite client
                                        to portal</DropdownMenuItem
                                    >
                                    <DropdownMenuItem
                                        v-if="row.status !== 'draft'"
                                        as-child
                                        ><a
                                            :href="row.public_url"
                                            target="_blank"
                                            rel="noopener"
                                            ><ExternalLink class="size-4" />
                                            Open client view</a
                                        ></DropdownMenuItem
                                    >
                                    <DropdownMenuItem
                                        v-if="row.status !== 'draft'"
                                        @select="copyLink(row)"
                                        ><Copy class="size-4" /> Copy client
                                        link</DropdownMenuItem
                                    >
                                    <DropdownMenuItem
                                        v-if="
                                            can('proposals.edit') &&
                                            row.status === 'draft'
                                        "
                                        @select="openEdit(row)"
                                        ><Pencil class="size-4" />
                                        Edit</DropdownMenuItem
                                    >
                                    <DropdownMenuSeparator
                                        v-if="
                                            can('proposals.delete') &&
                                            row.status !== 'accepted'
                                        "
                                    />
                                    <DropdownMenuItem
                                        v-if="
                                            can('proposals.delete') &&
                                            row.status !== 'accepted'
                                        "
                                        variant="destructive"
                                        class="text-destructive focus:text-destructive"
                                        @select="deleting = row"
                                        ><Trash2 class="size-4" />
                                        Delete</DropdownMenuItem
                                    >
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="proposals.data.length === 0"
                        ><TableCell
                            colspan="6"
                            class="h-32 text-center text-muted-foreground"
                            >No documents match these filters.</TableCell
                        ></TableRow
                    >
                </TableBody>
            </Table>
        </div>

        <div
            v-if="pagination.total"
            class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
        >
            <p>
                Showing {{ pagination.from }}–{{ pagination.to }} of
                {{ pagination.total }}
            </p>
            <nav
                v-if="pagination.last_page > 1"
                class="flex flex-wrap gap-1"
                aria-label="Document pages"
            >
                <Button
                    v-for="link in pagination.links"
                    :key="link.label"
                    as-child
                    size="sm"
                    :variant="link.active ? 'default' : 'outline'"
                    :disabled="!link.url"
                >
                    <Link v-if="link.url" :href="link.url" preserve-state>{{
                        decodePaginationLabel(link.label)
                    }}</Link
                    ><span v-else>{{ decodePaginationLabel(link.label) }}</span>
                </Button>
            </nav>
        </div>

        <Sheet v-model:open="previewOpen">
            <SheetContent side="right" class="w-full gap-0 sm:max-w-2xl">
                <SheetHeader class="shrink-0 border-b p-4 pr-12 text-left">
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary ring-1 ring-primary/20"
                        >
                            <FileSignature class="size-4" />
                        </div>
                        <div class="min-w-0">
                            <SheetTitle class="text-sm"
                                >Admin preview</SheetTitle
                            >
                            <SheetDescription class="truncate text-xs">{{
                                previewData
                                    ? `${previewData.proposal.number} · Revision ${previewData.proposal.revision}`
                                    : 'Document details, response history and actions'
                            }}</SheetDescription>
                        </div>
                    </div>
                </SheetHeader>
                <div
                    class="min-h-0 flex-1 overflow-y-auto overscroll-contain"
                    :aria-busy="previewRequest.processing"
                >
                    <div
                        v-if="previewError"
                        role="alert"
                        class="m-5 space-y-3 rounded-xl border border-destructive/30 p-5"
                    >
                        <p class="text-sm text-destructive">
                            {{ previewError }}
                        </p>
                        <Button
                            variant="outline"
                            @click="previewId && loadPreview(previewId)"
                            ><RefreshCw class="size-4" />Retry</Button
                        >
                    </div>
                    <div
                        v-else-if="!previewData"
                        role="status"
                        aria-label="Loading document preview"
                        class="space-y-5 p-5"
                    >
                        <span class="sr-only">Loading document preview…</span>
                        <div class="h-16 animate-pulse rounded-xl bg-muted" />
                        <div class="h-36 animate-pulse rounded-xl bg-muted" />
                        <div class="h-64 animate-pulse rounded-xl bg-muted" />
                    </div>
                    <ProposalDocument
                        v-else
                        :key="previewData.proposal.public_id"
                        v-bind="previewData"
                        embedded
                        @updated="previewId && loadPreview(previewId)"
                        @close="previewOpen = false"
                        @version="loadPreview"
                    />
                </div>
                <SheetFooter class="shrink-0 border-t px-5 py-3">
                    <SheetClose as-child
                        ><Button variant="outline" size="sm"
                            >Close preview</Button
                        ></SheetClose
                    >
                </SheetFooter>
            </SheetContent>
        </Sheet>

        <Drawer v-model:open="drawerOpen">
            <DrawerContent
                class="max-h-[92vh] overflow-y-auto md:inset-x-auto md:right-2 md:left-64"
            >
                <DrawerHeader class="flex flex-row items-center gap-3 text-left"
                    ><div
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/20"
                    >
                        <FileSignature class="size-5" />
                    </div>
                    <div>
                        <DrawerTitle>{{
                            editing ? 'Edit document' : 'Create document'
                        }}</DrawerTitle
                        ><DrawerDescription
                            >Define the client, commercial terms, and line
                            items.</DrawerDescription
                        >
                    </div></DrawerHeader
                >
                <form class="space-y-5 px-4 pb-6" @submit.prevent="submit">
                    <section
                        class="grid gap-4 rounded-xl border p-4 sm:grid-cols-2"
                    >
                        <label class="space-y-1 text-sm"
                            >Number<Input
                                v-model="form.number"
                                required /><InputError
                                :message="form.errors.number"
                        /></label>
                        <label class="space-y-1 text-sm"
                            >Document type<select
                                v-model="form.type"
                                class="block h-9 w-full rounded-md border bg-background px-3"
                                required
                            >
                                <option value="proposal">Proposal</option>
                                <option value="quotation">Quotation</option>
                                <option value="contract">
                                    Contract
                                </option></select
                            ><InputError :message="form.errors.type"
                        /></label>
                        <label class="space-y-1 text-sm sm:col-span-2"
                            >Title<Input
                                v-model="form.title"
                                required /><InputError
                                :message="form.errors.title"
                        /></label>
                        <label class="space-y-1 text-sm"
                            >Client name<Input
                                v-model="form.client_name"
                                required /><InputError
                                :message="form.errors.client_name"
                        /></label>
                        <label class="space-y-1 text-sm"
                            >Client email<Input
                                v-model="form.client_email"
                                type="email"
                                required /><InputError
                                :message="form.errors.client_email"
                        /></label>
                        <label class="space-y-1 text-sm"
                            >Status<select
                                v-model="form.status"
                                class="block h-9 w-full rounded-md border bg-background px-3"
                                required
                            >
                                <option value="draft">Draft</option>
                                <option value="sent">
                                    Sent / shareable
                                </option></select
                            ><InputError :message="form.errors.status"
                        /></label>
                        <label class="space-y-1 text-sm"
                            >Currency<Input
                                v-model="form.currency"
                                maxlength="3"
                                required
                                @input="
                                    form.currency = form.currency.toUpperCase()
                                " /><InputError :message="form.errors.currency"
                        /></label>
                        <label class="space-y-1 text-sm"
                            >Issued on<Input
                                v-model="form.issued_on"
                                type="date"
                                required /><InputError
                                :message="form.errors.issued_on"
                        /></label>
                        <label class="space-y-1 text-sm"
                            >Valid until<Input
                                v-model="form.valid_until"
                                type="date"
                                required /><InputError
                                :message="form.errors.valid_until"
                        /></label>
                    </section>

                    <section class="space-y-3 rounded-xl border p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-medium">Line items</h3>
                                <p class="text-xs text-muted-foreground">
                                    Prices are entered in
                                    {{
                                        form.currency ||
                                        'the selected currency'
                                    }}.
                                </p>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="form.items.push(blankItem())"
                                ><Plus class="size-4" /> Add item</Button
                            >
                        </div>
                        <div
                            v-for="(item, i) in form.items"
                            :key="i"
                            class="grid min-w-0 gap-3 rounded-lg bg-muted/40 p-3 sm:grid-cols-[minmax(0,1fr)_7rem_9rem_auto]"
                        >
                            <label class="min-w-0 space-y-1 text-sm"
                                >Description<Input
                                    v-model="item.description"
                                    required /><InputError
                                    :message="
                                        form.errors[`items.${i}.description`]
                                    "
                            /></label>
                            <label class="space-y-1 text-sm"
                                >Quantity<Input
                                    v-model="item.quantity"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    required /><InputError
                                    :message="
                                        form.errors[`items.${i}.quantity`]
                                    "
                            /></label>
                            <label class="space-y-1 text-sm"
                                >Unit price<Input
                                    v-model="item.unit_price_minor"
                                    inputmode="decimal"
                                    required /><InputError
                                    :message="
                                        form.errors[
                                            `items.${i}.unit_price_minor`
                                        ]
                                    "
                            /></label>
                            <Button
                                type="button"
                                size="icon-sm"
                                variant="ghost"
                                class="self-end"
                                aria-label="Remove item"
                                :disabled="form.items.length === 1"
                                @click="removeItem(i)"
                                ><X class="size-4"
                            /></Button>
                        </div>
                        <InputError :message="form.errors.items" />
                    </section>

                    <section
                        class="grid gap-4 rounded-xl border p-4 sm:grid-cols-2"
                    >
                        <label class="space-y-1 text-sm"
                            >Discount<Input
                                v-model="form.discount_minor"
                                inputmode="decimal" /><InputError
                                :message="form.errors.discount_minor"
                        /></label>
                        <label class="space-y-1 text-sm"
                            >Tax rate (%)<Input
                                v-model="form.tax_rate_basis_points"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01" /><InputError
                                :message="form.errors.tax_rate_basis_points"
                        /></label>
                        <div
                            class="space-y-2 rounded-lg bg-muted p-4 text-sm sm:col-span-2"
                        >
                            <div class="flex justify-between gap-4">
                                <span class="text-muted-foreground"
                                    >Subtotal</span
                                ><span>{{
                                    amount(
                                        previewSubtotal,
                                        form.currency || 'BDT',
                                    )
                                }}</span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-muted-foreground"
                                    >Discount</span
                                ><span
                                    >−{{
                                        amount(
                                            previewDiscount,
                                            form.currency || 'BDT',
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-muted-foreground"
                                    >Tax ({{
                                        form.tax_rate_basis_points || 0
                                    }}%)</span
                                ><span>{{
                                    amount(previewTax, form.currency || 'BDT')
                                }}</span>
                            </div>
                            <div
                                class="flex justify-between gap-4 border-t pt-2 font-semibold"
                            >
                                <span>Document total</span
                                ><span>{{
                                    amount(previewTotal, form.currency || 'BDT')
                                }}</span>
                            </div>
                        </div>
                        <label class="space-y-1 text-sm sm:col-span-2"
                            >Terms<textarea
                                v-model="form.terms"
                                rows="4"
                                class="block w-full rounded-md border bg-background px-3 py-2" /><InputError
                                :message="form.errors.terms"
                        /></label>
                        <label class="space-y-1 text-sm sm:col-span-2"
                            >Client notes<textarea
                                v-model="form.notes"
                                rows="3"
                                class="block w-full rounded-md border bg-background px-3 py-2" /><InputError
                                :message="form.errors.notes"
                        /></label>
                    </section>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="drawerOpen = false"
                            >Cancel</Button
                        ><Button type="submit" :disabled="form.processing">{{
                            form.processing ? 'Saving…' : 'Save document'
                        }}</Button>
                    </div>
                </form>
            </DrawerContent>
        </Drawer>

        <AlertDialog
            :open="Boolean(deleting)"
            @update:open="
                (open) => {
                    if (!open) deleting = null;
                }
            "
        >
            <AlertDialogContent class="sm:max-w-md"
                ><AlertDialogHeader
                    ><AlertDialogTitle
                        >Delete {{ deleting?.number }}?</AlertDialogTitle
                    ><AlertDialogDescription
                        >This permanently removes the document and disables its
                        client link.</AlertDialogDescription
                    ></AlertDialogHeader
                ><AlertDialogFooter
                    ><AlertDialogCancel>Cancel</AlertDialogCancel
                    ><AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        @click="confirmDelete"
                        >Delete document</AlertDialogAction
                    ></AlertDialogFooter
                ></AlertDialogContent
            >
        </AlertDialog>
    </div>
</template>
