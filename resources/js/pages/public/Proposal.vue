<script setup lang="ts">
import { formatMoney } from '@/lib/money';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    CheckCircle2,
    FileSignature,
    XCircle,
    FileText,
    History,
    Settings2,
    Printer,
    UserRound,
    CalendarDays,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useIntervalFn } from '@vueuse/core';
import ProposalItems from '@/components/ProposalItems.vue';
import BusinessDocument from '@/components/BusinessDocument.vue';
import { Toaster } from '@/components/ui/sonner';
import { usePermissions } from '@/composables/usePermissions';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import {
    index as adminProposals,
    preview as adminPreview,
    revise,
    transfer,
    invoice,
    receipt,
    remind,
    passwordReset,
} from '@/routes/admin/proposals';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    respond,
    sendCode,
    show as showDocument,
} from '@/routes/proposals/public';
import { index as clientDocuments } from '@/routes/client/proposals';

defineOptions({
    layout: (pageProps: { preview: boolean }) =>
        pageProps.preview
            ? [
                  AdminLayout,
                  {
                      breadcrumbs: [
                          {
                              title: 'Proposals & Contracts',
                              href: adminProposals.url(),
                          },
                          { title: 'Admin preview' },
                      ],
                  },
              ]
            : null,
});

export type Proposal = {
    public_id: string;
    number: string;
    revision: number;
    fingerprint: string;
    type: string;
    status:
        | 'draft'
        | 'sent'
        | 'accepted'
        | 'rejected'
        | 'expired'
        | 'superseded';
    title: string;
    client_name: string;
    currency: string;
    items: {
        description: string;
        quantity: number;
        unit_price_minor: number;
    }[];
    subtotal_minor: number;
    discount_minor: number;
    tax_rate_basis_points: number;
    tax_minor: number;
    total_minor: number;
    issued_on: string;
    valid_until: string;
    terms: string | null;
    notes: string | null;
    signer_name: string | null;
    responded_at: string | null;
    recipient_verified_at: string | null;
    response_note: string | null;
    invitation_status: string | null;
    invitation_sent_at: string | null;
    invoice: { number: string; status: string; amount_minor: number } | null;
    history: {
        id: number;
        action: string;
        description: string | null;
        created_at: string;
    }[];
    versions: {
        public_id: string;
        number: string;
        revision: number;
        status: string;
    }[];
};

export type ProposalPreviewProps = {
    proposal: Proposal;
    preview: boolean;
    retry_after: number;
    embedded?: boolean;
};
const props = defineProps<ProposalPreviewProps>();
const emit = defineEmits<{
    updated: [];
    close: [];
    version: [publicId: string];
}>();
const { can } = usePermissions();
const activePane = ref<'overview' | 'activity' | 'manage'>('overview');
const panes = [
    { value: 'overview', label: 'Overview', icon: FileText },
    { value: 'activity', label: 'Activity', icon: History },
    { value: 'manage', label: 'Manage', icon: Settings2 },
] as const;
const visiblePanes = computed(() =>
    panes.filter(
        (pane) =>
            pane.value !== 'manage' ||
            can('proposals.edit') ||
            can('proposals.transfer') ||
            can('clients.password-reset') ||
            can('invoices.create') ||
            props.proposal.invoice,
    ),
);
const statusTone = computed(
    () =>
        ({
            draft: 'bg-muted text-muted-foreground',
            sent: 'border-blue-500/20 bg-blue-500/10 text-blue-700 dark:text-blue-300',
            accepted:
                'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
            rejected:
                'border-destructive/20 bg-destructive/10 text-destructive',
            expired:
                'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
            superseded: 'bg-muted text-muted-foreground',
        })[props.proposal.status],
);
const printRef = ref<InstanceType<typeof BusinessDocument> | null>(null);
const revisionForm = useForm({});
const receiptForm = useForm({});
const reminderForm = useForm({});
const passwordResetForm = useForm({});
const transferForm = useForm({ email: '', reason: '' });
const invoiceForm = useForm({ percentage: 100, due_on: '' });
const cooldown = ref(props.retry_after);
watch(
    () => props.retry_after,
    (value) => {
        cooldown.value = value;
    },
);
useIntervalFn(() => {
    if (cooldown.value > 0) cooldown.value--;
}, 1000);
const decision = ref<'accepted' | 'rejected' | null>(null);
const form = useForm({
    decision: 'accepted',
    signer_name: '',
    response_note: '',
    verification_code: '',
    fingerprint: props.proposal.fingerprint,
});
const codeForm = useForm({
    fingerprint: props.proposal.fingerprint,
    verification_code: '',
});
watch(
    () => props.proposal.fingerprint,
    (value) => {
        form.fingerprint = value;
        codeForm.fingerprint = value;
        form.verification_code = '';
        codeSent.value = false;
    },
);
const codeSent = ref(false);
function requestCode(): void {
    codeForm.post(sendCode.url(props.proposal.public_id), {
        preserveScroll: true,
        onSuccess: () => {
            codeSent.value = true;
            cooldown.value = 60;
        },
    });
}
const canRespond = computed(
    () => !props.preview && props.proposal.status === 'sent',
);
const money = (minor: number) => formatMoney(minor, props.proposal.currency);
const dateTime = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

function openResponse(value: 'accepted' | 'rejected'): void {
    decision.value = value;
    form.decision = value;
    form.clearErrors();
}

function submit(): void {
    form.post(respond.url(props.proposal.public_id), {
        onSuccess: () => {
            decision.value = null;
        },
    });
}
</script>

<template>
    <div
        class="bg-muted/40 px-4 text-foreground"
        :class="
            embedded
                ? 'py-4'
                : preview
                  ? 'py-6 sm:px-6'
                  : 'min-h-screen py-8 sm:py-12'
        "
    >
        <Head v-if="!embedded" :title="`${proposal.type}: ${proposal.title}`">
            <meta name="robots" content="noindex, nofollow, noarchive" />
            <meta name="referrer" content="same-origin" />
        </Head>
        <Toaster v-if="!preview" />
        <component
            :is="preview ? 'section' : 'main'"
            class="mx-auto max-w-4xl"
            :class="embedded ? 'space-y-4' : 'space-y-6'"
        >
            <Card
                v-if="embedded"
                class="gap-0 overflow-hidden py-0 shadow-none"
            >
                <CardContent class="space-y-3 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge
                                variant="outline"
                                class="text-[11px] capitalize"
                                >{{ proposal.type }}</Badge
                            >
                            <Badge
                                variant="outline"
                                :class="statusTone"
                                class="text-[11px] capitalize"
                                >{{ proposal.status }}</Badge
                            >
                        </div>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="h-8 shrink-0 gap-1.5 px-2"
                            @click="printRef?.printDocument()"
                            ><Printer class="size-3.5" />Print / PDF</Button
                        >
                    </div>
                    <h2
                        class="text-base leading-snug font-semibold break-words"
                    >
                        {{ proposal.title }}
                    </h2>
                    <div
                        class="flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <UserRound class="size-3.5 shrink-0" /><span
                            class="break-words"
                            >{{ proposal.client_name }}</span
                        >
                    </div>
                    <div
                        class="flex flex-wrap items-end justify-between gap-3 border-t pt-3"
                    >
                        <div>
                            <p class="text-[11px] text-muted-foreground">
                                Document total
                            </p>
                            <p
                                class="mt-0.5 text-xl font-semibold tracking-tight tabular-nums"
                            >
                                {{ money(proposal.total_minor) }}
                            </p>
                        </div>
                        <dl class="grid gap-1 text-xs">
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <dt
                                    class="flex items-center gap-1 text-muted-foreground"
                                >
                                    <CalendarDays class="size-3" />Issued
                                </dt>
                                <dd class="tabular-nums">
                                    {{ proposal.issued_on }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">
                                    Valid until
                                </dt>
                                <dd class="tabular-nums">
                                    {{ proposal.valid_until }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </CardContent>
            </Card>
            <nav
                v-if="embedded"
                aria-label="Preview sections"
                class="sticky top-0 z-10 grid gap-1 rounded-lg border bg-background p-1 shadow-xs"
                :class="
                    visiblePanes.length === 3 ? 'grid-cols-3' : 'grid-cols-2'
                "
            >
                <Button
                    v-for="pane in visiblePanes"
                    :key="pane.value"
                    size="sm"
                    variant="ghost"
                    class="h-8 min-w-0 gap-1.5 px-2 text-xs"
                    :class="
                        activePane === pane.value
                            ? 'bg-muted font-semibold text-foreground'
                            : 'text-muted-foreground'
                    "
                    :aria-pressed="activePane === pane.value"
                    @click="activePane = pane.value"
                    ><component :is="pane.icon" class="size-3.5 shrink-0" />{{
                        pane.label
                    }}<span
                        v-if="
                            pane.value === 'activity' && proposal.history.length
                        "
                        class="rounded bg-background px-1 text-[10px] tabular-nums"
                        >{{ proposal.history.length }}</span
                    ></Button
                >
            </nav>
            <component
                :is="embedded ? 'section' : 'details'"
                v-if="preview"
                v-show="!embedded || activePane === 'manage'"
                :open="embedded ? undefined : true"
                class="space-y-4 rounded-xl border bg-card p-4"
            >
                <summary
                    v-if="!embedded"
                    class="cursor-pointer text-sm font-semibold"
                >
                    Document actions · Revision {{ proposal.revision }}
                </summary>
                <h3 v-else class="text-sm font-semibold">Actions & delivery</h3>
                <p class="text-sm text-muted-foreground">
                    Client approval is completed from the recipient's account.
                </p>
                <p v-if="proposal.invitation_status" class="text-sm">
                    Invitation: {{ proposal.invitation_status }} ·
                    {{
                        proposal.invitation_sent_at
                            ? dateTime(proposal.invitation_sent_at)
                            : 'Not delivered'
                    }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="
                            can('clients.password-reset') &&
                            can('proposals.view')
                        "
                        variant="outline"
                        size="sm"
                        :disabled="passwordResetForm.processing"
                        @click="
                            passwordResetForm.post(
                                passwordReset.url(proposal.public_id),
                                {
                                    preserveScroll: true,
                                    onSuccess: () => emit('updated'),
                                },
                            )
                        "
                        >Send client password reset</Button
                    >
                    <Button
                        v-if="
                            can('proposals.edit') && proposal.status === 'sent'
                        "
                        variant="outline"
                        size="sm"
                        :disabled="reminderForm.processing"
                        @click="
                            reminderForm.post(remind.url(proposal.public_id), {
                                preserveScroll: true,
                                onSuccess: () => emit('updated'),
                            })
                        "
                        >Send reminder</Button
                    >
                    <Button
                        v-if="can('proposals.create') && can('proposals.edit')"
                        size="sm"
                        :disabled="revisionForm.processing"
                        @click="
                            revisionForm.post(revise.url(proposal.public_id), {
                                onSuccess: () => emit('close'),
                            })
                        "
                        >Create draft revision</Button
                    >
                    <Button
                        v-if="
                            can('proposals.edit') &&
                            ['accepted', 'rejected'].includes(proposal.status)
                        "
                        variant="outline"
                        size="sm"
                        :disabled="receiptForm.processing"
                        @click="
                            receiptForm.post(receipt.url(proposal.public_id), {
                                preserveScroll: true,
                                onSuccess: () => emit('updated'),
                            })
                        "
                        >Resend approval receipt</Button
                    >
                </div>
                <form
                    v-if="
                        can('proposals.transfer') &&
                        !['accepted', 'rejected'].includes(proposal.status)
                    "
                    class="grid gap-3 rounded-lg border p-4"
                    @submit.prevent="
                        transferForm.post(transfer.url(proposal.public_id), {
                            preserveScroll: true,
                            onSuccess: () => emit('updated'),
                        })
                    "
                >
                    <h2 class="font-medium">Transfer recipient</h2>
                    <label class="text-sm"
                        >Verified client email<Input
                            v-model="transferForm.email"
                            type="email"
                            required /><InputError
                            :message="transferForm.errors.email"
                    /></label>
                    <label class="text-sm"
                        >Reason<Input
                            v-model="transferForm.reason"
                            required /><InputError
                            :message="transferForm.errors.reason"
                    /></label>
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="transferForm.processing"
                        >Transfer access</Button
                    >
                </form>
                <div
                    v-if="proposal.invoice"
                    class="rounded-lg border p-4 text-sm"
                >
                    Linked invoice: {{ proposal.invoice.number }} ·
                    {{ proposal.invoice.status }}
                </div>
                <form
                    v-else-if="
                        proposal.status === 'accepted' && can('invoices.create')
                    "
                    class="grid gap-3 rounded-lg border p-4 sm:grid-cols-2"
                    @submit.prevent="
                        invoiceForm.post(invoice.url(proposal.public_id), {
                            preserveScroll: true,
                            onSuccess: () => emit('updated'),
                        })
                    "
                >
                    <h2 class="font-medium sm:col-span-2">
                        Create invoice draft
                    </h2>
                    <label class="text-sm"
                        >Invoice amount (%)<Input
                            v-model="invoiceForm.percentage"
                            type="number"
                            min="1"
                            max="100"
                            required /><InputError
                            :message="invoiceForm.errors.percentage"
                    /></label>
                    <label class="text-sm"
                        >Due on<Input
                            v-model="invoiceForm.due_on"
                            type="date"
                            required /><InputError
                            :message="invoiceForm.errors.due_on"
                    /></label>
                    <Button type="submit" :disabled="invoiceForm.processing"
                        >Create linked invoice</Button
                    >
                </form>
            </component>
            <div v-if="!embedded" class="flex flex-wrap items-center gap-3">
                <Button variant="outline" @click="printRef?.printDocument()"
                    >Print / Save as PDF</Button
                >
                <Link
                    v-if="!embedded"
                    :href="preview ? adminProposals() : clientDocuments()"
                    class="text-sm underline underline-offset-4"
                    >{{
                        preview ? 'Back to proposals' : 'Back to my documents'
                    }}</Link
                >
            </div>
            <header
                v-if="!embedded"
                class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border bg-card p-5 shadow-sm"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <FileSignature class="size-6" />
                    </div>
                    <div>
                        <p class="font-semibold">ValenceKit</p>
                        <p
                            class="text-sm tracking-wide text-muted-foreground uppercase"
                        >
                            {{ proposal.type }} · {{ proposal.number }} ·
                            Revision {{ proposal.revision }}
                        </p>
                    </div>
                </div>
                <Badge variant="outline" class="capitalize">{{
                    proposal.status
                }}</Badge>
            </header>

            <article
                v-show="!embedded || activePane === 'overview'"
                class="overflow-hidden border bg-card"
                :class="embedded ? 'rounded-xl' : 'rounded-2xl shadow-sm'"
            >
                <section v-if="!embedded" class="border-b p-6 sm:p-8">
                    <p class="text-sm text-muted-foreground">
                        Prepared for {{ proposal.client_name }}
                    </p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight">
                        {{ proposal.title }}
                    </h1>
                    <div class="mt-6 grid gap-3 text-sm sm:grid-cols-2">
                        <p>
                            <span class="text-muted-foreground">Issued:</span>
                            {{ proposal.issued_on }}
                        </p>
                        <p class="sm:text-right">
                            <span class="text-muted-foreground"
                                >Valid until:</span
                            >
                            {{ proposal.valid_until }}
                        </p>
                    </div>
                </section>

                <ProposalItems :proposal="proposal" :embedded="embedded" />

                <section
                    v-if="proposal.terms || proposal.notes"
                    class="grid gap-4 border-t sm:grid-cols-2"
                    :class="embedded ? 'p-4' : 'p-6 sm:p-8'"
                >
                    <div v-if="proposal.terms" class="min-w-0">
                        <h2 class="font-semibold">Terms</h2>
                        <p
                            class="mt-2 text-sm break-words whitespace-pre-wrap text-muted-foreground"
                        >
                            {{ proposal.terms }}
                        </p>
                    </div>
                    <div v-if="proposal.notes" class="min-w-0">
                        <h2 class="font-semibold">Notes</h2>
                        <p
                            class="mt-2 text-sm break-words whitespace-pre-wrap text-muted-foreground"
                        >
                            {{ proposal.notes }}
                        </p>
                    </div>
                </section>
            </article>

            <section
                v-if="canRespond"
                class="rounded-2xl border bg-card p-6 shadow-sm sm:p-8"
            >
                <div
                    v-if="!decision"
                    class="flex flex-wrap items-center justify-between gap-4"
                >
                    <div>
                        <h2 class="text-lg font-semibold">Ready to respond?</h2>
                        <p class="text-sm text-muted-foreground">
                            Accept and sign this document, or decline with a
                            note.
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            @click="openResponse('rejected')"
                            ><XCircle class="size-4" /> Decline</Button
                        ><Button @click="openResponse('accepted')"
                            ><CheckCircle2 class="size-4" /> Accept</Button
                        >
                    </div>
                </div>
                <form v-else class="space-y-4" @submit.prevent="submit">
                    <div>
                        <h2 class="text-lg font-semibold capitalize">
                            {{ decision }} response
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{
                                decision === 'accepted'
                                    ? 'Typing your name records your approval.'
                                    : 'You may include a reason or requested change.'
                            }}
                        </p>
                    </div>
                    <label
                        v-if="decision === 'accepted'"
                        class="block space-y-1 text-sm"
                        >Full name<Input
                            v-model="form.signer_name"
                            required
                            autocomplete="name" /><InputError
                            :message="form.errors.signer_name" /></label
                    ><label class="block space-y-1 text-sm"
                        >Message (optional)<textarea
                            v-model="form.response_note"
                            rows="4"
                            class="block w-full rounded-md border bg-background px-3 py-2" /><InputError
                            :message="form.errors.response_note"
                    /></label>
                    <div class="space-y-3 rounded-xl border p-4">
                        <p class="text-sm text-muted-foreground">
                            Verify access to the recipient email before
                            submitting your response. The code expires in 10
                            minutes.
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="codeForm.processing || cooldown > 0"
                            @click="requestCode"
                            >{{
                                codeForm.processing
                                    ? 'Sending…'
                                    : cooldown > 0
                                      ? `Retry in ${cooldown}s`
                                      : 'Send verification code'
                            }}</Button
                        >
                        <p v-if="codeSent" role="status" class="text-sm">
                            Code sent to the document recipient. Check your
                            inbox.
                        </p>
                        <InputError
                            :message="codeForm.errors.verification_code"
                        />
                        <label class="block space-y-1 text-sm"
                            >Verification code<Input
                                v-model="form.verification_code"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                maxlength="6"
                                pattern="[0-9]{6}"
                                required /><InputError
                                :message="form.errors.verification_code"
                        /></label>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="decision = null"
                            >Cancel</Button
                        ><Button
                            type="submit"
                            :variant="
                                decision === 'rejected'
                                    ? 'destructive'
                                    : 'default'
                            "
                            :disabled="form.processing"
                            >{{
                                form.processing
                                    ? 'Submitting…'
                                    : `Confirm ${decision}`
                            }}</Button
                        >
                    </div>
                </form>
            </section>

            <section
                v-else-if="!preview"
                class="rounded-2xl border bg-card p-6 text-center shadow-sm"
            >
                <CheckCircle2
                    v-if="proposal.status === 'accepted'"
                    class="mx-auto size-10 text-emerald-600"
                /><XCircle
                    v-else
                    class="mx-auto size-10 text-muted-foreground"
                />
                <h2 class="mt-3 text-lg font-semibold capitalize">
                    Document {{ proposal.status }}
                </h2>
                <p
                    v-if="proposal.signer_name"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Signed by {{ proposal.signer_name }}
                </p>
                <p v-else class="mt-1 text-sm text-muted-foreground">
                    This document is no longer awaiting a response.
                </p>
            </section>
            <section
                v-if="proposal.responded_at"
                v-show="!embedded || activePane === 'overview'"
                class="space-y-2 rounded-xl border bg-card p-4"
            >
                <h2 class="font-semibold">Response receipt</h2>
                <p class="text-sm">
                    {{ proposal.status }} · Revision {{ proposal.revision }} ·
                    {{ dateTime(proposal.responded_at) }}
                </p>
                <p v-if="proposal.recipient_verified_at" class="text-sm">
                    Email verified at
                    {{ dateTime(proposal.recipient_verified_at) }}
                </p>
                <p
                    v-if="proposal.response_note"
                    class="text-sm break-words whitespace-pre-wrap"
                >
                    {{ proposal.response_note }}
                </p>
            </section>
            <section
                v-show="!embedded || activePane === 'activity'"
                class="space-y-3 rounded-xl border bg-card p-4"
            >
                <h2 class="text-sm font-semibold">Document history</h2>
                <ol class="space-y-3">
                    <li
                        v-for="event in proposal.history"
                        :key="event.id"
                        class="border-l-2 border-primary/20 pl-3 text-sm"
                    >
                        <p class="font-medium capitalize">
                            {{
                                event.action
                                    .replaceAll('proposal.', '')
                                    .replaceAll('_', ' ')
                                    .replaceAll('.', ' ')
                            }}
                        </p>
                        <p
                            v-if="event.description"
                            class="break-words text-muted-foreground"
                        >
                            {{ event.description }}
                        </p>
                        <time class="text-xs text-muted-foreground">{{
                            dateTime(event.created_at)
                        }}</time>
                    </li>
                </ol>
                <p
                    v-if="!proposal.history.length"
                    class="text-sm text-muted-foreground"
                >
                    No activity recorded yet.
                </p>
                <h3 class="pt-2 text-sm font-medium">Versions</h3>
                <div class="flex flex-wrap gap-3">
                    <template
                        v-for="version in proposal.versions"
                        :key="version.public_id"
                    >
                        <Button
                            v-if="embedded"
                            variant="outline"
                            size="sm"
                            class="h-8 text-xs"
                            :title="version.number"
                            :aria-label="`${version.number}, revision ${version.revision}${version.public_id === proposal.public_id ? ', current' : ''}`"
                            :disabled="version.public_id === proposal.public_id"
                            @click="emit('version', version.public_id)"
                            >R{{ version.revision
                            }}{{
                                version.public_id === proposal.public_id
                                    ? ' · Current'
                                    : ''
                            }}</Button
                        >
                        <Link
                            v-else
                            :href="
                                preview
                                    ? adminPreview(version.public_id)
                                    : showDocument(version.public_id)
                            "
                            class="text-sm underline underline-offset-4"
                            >{{ version.number }} · R{{
                                version.revision
                            }}</Link
                        >
                    </template>
                </div>
            </section>
            <div class="hidden print:block">
                <BusinessDocument
                    ref="printRef"
                    :title="proposal.title"
                    :reference="`${proposal.number} / Revision ${proposal.revision}`"
                    :date="proposal.issued_on"
                    date-label="Issued on"
                    :metadata="[
                        { label: 'Status', value: proposal.status },
                        { label: 'Valid until', value: proposal.valid_until },
                        {
                            label: 'Signer',
                            value: proposal.signer_name || 'Not signed',
                        },
                        {
                            label: 'Response recorded',
                            value: proposal.responded_at || 'Pending',
                        },
                    ]"
                    :recipient="{
                        label: 'Prepared for',
                        name: proposal.client_name,
                    }"
                    :columns="[
                        'Description',
                        'Quantity',
                        'Unit price',
                        'Amount',
                    ]"
                    :rows="
                        proposal.items.map((item, i) => ({
                            id: i,
                            values: [
                                item.description,
                                String(item.quantity),
                                money(item.unit_price_minor),
                                money(
                                    Math.round(
                                        item.quantity * item.unit_price_minor,
                                    ),
                                ),
                            ],
                        }))
                    "
                    :totals="[
                        {
                            label: 'Subtotal',
                            value: money(proposal.subtotal_minor),
                        },
                        {
                            label: 'Discount',
                            value: money(proposal.discount_minor),
                        },
                        { label: 'Tax', value: money(proposal.tax_minor) },
                        { label: 'Total', value: money(proposal.total_minor) },
                    ]"
                    :note="
                        [
                            proposal.terms,
                            proposal.notes,
                            proposal.response_note,
                            proposal.recipient_verified_at
                                ? `Recipient email verified: ${proposal.recipient_verified_at}`
                                : '',
                        ]
                            .filter(Boolean)
                            .join('\n\n')
                    "
                />
            </div>
        </component>
    </div>
</template>
