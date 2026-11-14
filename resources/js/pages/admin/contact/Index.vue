<script setup lang="ts">
import { Head, router, useForm, useHttp, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Inbox,
    Download,
    Mail,
    MessageSquare,
    PenLine,
    RefreshCw,
    Send,
    ShieldCheck,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onMounted,
    onUnmounted,
    reactive,
    ref,
    watch,
} from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';
import {
    index,
    messages as threadMessages,
    more,
    reply,
    start,
    sync,
} from '@/routes/admin/contact';
import {
    connect,
    disconnect,
    search as searchGmail,
    importMethod as importGmail,
    importStatus,
} from '@/routes/admin/contact/gmail';

type Reply = {
    id: number;
    body: string;
    incoming: boolean;
    created_at: string;
};
type ContactMessage = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    message: string;
    subject: string | null;
    referral_source: string | null;
    created_at: string;
    initiated_by_admin: boolean;
    latest_reply: Reply | null;
};

type CursorPage<T> = { data: T[]; next_cursor: string | null };
type GmailThread = {
    id: string;
    uid: string;
    subject: string;
    date: string | null;
    imported: boolean;
};

const props = defineProps<{
    gmail: { email: string | null; configured: boolean; callback_url: string };
    messages: CursorPage<ContactMessage>;
    filters: { search: string };
}>();

defineOptions({ breadcrumbs: [{ title: 'Contact', href: index.url() }] });

const { can } = usePermissions();
const page = usePage<{ errors: Record<string, string> }>();
const selectedId = ref<number | null>(props.messages.data[0]?.id ?? null);
const conversations = ref<ContactMessage[]>([...props.messages.data]);
const nextConversationCursor = ref(props.messages.next_cursor);
const hasLoadedMore = ref(false);
const conversationSearch = ref(props.filters.search);
let appliedSearch = props.filters.search;
const listRequest = useHttp<{}, CursorPage<ContactMessage>>({});
const threadRequest = useHttp<{}, CursorPage<Reply>>({});
const conversationError = ref('');
const threadError = ref('');
const replies = ref<Reply[]>([]);
const nextReplyCursor = ref<string | null>(null);
let threadRequestId = 0;
const showMobileThread = ref(false);
const showStart = ref(false);
const showImport = ref(false);
const gmailSearch = useHttp<
    { email: string; before: string | null },
    { threads: GmailThread[]; next_before: string | null }
>({
    email: '',
    before: null,
});
const importRequest = useHttp<
    { email: string; threads: { thread_id: string; uid: string }[] },
    { batch_id: string }
>({ email: '', threads: [] });
const importStatusRequest = useHttp<
    {},
    {
        total: number;
        processed: number;
        failed: number;
        finished: boolean;
        imported_ids: string[];
    }
>({});
const gmailThreads = ref<GmailThread[] | null>(null);
const selectedThreadIds = ref<string[]>([]);
const importing = ref(false);
const importProgress = ref('');
const importSummary = ref('');
let importPollTimer: ReturnType<typeof setTimeout> | undefined;
const selectableThreads = computed(() =>
    (gmailThreads.value ?? []).filter((thread) => !thread.imported),
);
const selectedThreads = computed(() =>
    selectableThreads.value.filter((thread) =>
        selectedThreadIds.value.includes(thread.id),
    ),
);
const selectAllState = computed(() =>
    selectedThreads.value.length === 0
        ? false
        : selectedThreads.value.length === selectableThreads.value.length
          ? true
          : 'indeterminate',
);
const nextGmailCursor = ref<string | null>(null);
const searchError = ref('');
const searchedEmail = ref('');
const replyForm = useForm({ body: '' });
const startForm = useForm({ name: '', email: '', body: '' });
const syncForm = useForm({});
const disconnectForm = useForm({});
const showDisconnect = ref(false);
const drafts = reactive<Record<number, string>>({});
const threadScroll = ref<HTMLElement | null>(null);
const selected = computed(
    () =>
        conversations.value.find(
            (message) => message.id === selectedId.value,
        ) ?? null,
);
const replyDraft = computed({
    get: () =>
        selectedId.value === null ? '' : (drafts[selectedId.value] ?? ''),
    set: (value: string) => {
        if (selectedId.value !== null) drafts[selectedId.value] = value;
    },
});
const timeline = computed(() => {
    if (!selected.value) return [];
    const initial =
        selected.value.initiated_by_admin ||
        nextReplyCursor.value !== null ||
        threadRequest.processing ||
        threadError.value
            ? []
            : [
                  {
                      id: 'initial',
                      body: selected.value.message,
                      incoming: true,
                      created_at: selected.value.created_at,
                  },
              ];
    return [
        ...initial,
        ...replies.value.map((item) => ({
            ...item,
            id: String(item.id),
        })),
    ];
});

function formatDate(value: string, compact = false): string {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return new Intl.DateTimeFormat(
        undefined,
        compact
            ? { month: 'short', day: 'numeric', year: 'numeric' }
            : { dateStyle: 'medium', timeStyle: 'short' },
    ).format(date);
}

function preview(message: ContactMessage): string {
    const last = message.latest_reply;
    return last
        ? `${last.incoming ? '' : 'You: '}${last.body}`
        : message.message;
}

async function openThread(id: number) {
    selectedId.value = id;
    showMobileThread.value = true;
    replyForm.clearErrors();
    loadThread(id);
}

function searchInbox() {
    listRequest.cancel();
    router.get(
        index.url(),
        { search: conversationSearch.value.trim() || undefined },
        {
            only: ['messages', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function loadMoreConversations() {
    if (!nextConversationCursor.value || listRequest.processing) return;
    conversationError.value = '';
    void listRequest
        .get(
            more.url({
                query: {
                    cursor: nextConversationCursor.value,
                    search: appliedSearch || undefined,
                },
            }),
            {
                onSuccess: (result) => {
                    const existing = new Set(
                        conversations.value.map((item) => item.id),
                    );
                    conversations.value.push(
                        ...result.data.filter((item) => !existing.has(item.id)),
                    );
                    nextConversationCursor.value = result.next_cursor;
                    hasLoadedMore.value = true;
                },
                onHttpException: () => {
                    conversationError.value =
                        'Could not load more conversations. Please try again.';
                    return false;
                },
                onNetworkError: () => {
                    conversationError.value =
                        'Could not load more conversations. Please check your connection.';
                    return false;
                },
            },
        )
        .catch(() => {});
}

function loadThread(id: number, older = false) {
    if (older && (!nextReplyCursor.value || threadRequest.processing)) return;
    threadRequest.cancel();
    const requestId = ++threadRequestId;
    const cursor = older ? nextReplyCursor.value : null;
    const previousHeight = threadScroll.value?.scrollHeight ?? 0;
    if (!older) {
        replies.value = [];
        nextReplyCursor.value = null;
    }
    threadError.value = '';
    void threadRequest
        .get(threadMessages.url(id, { query: cursor ? { cursor } : {} }), {
            onSuccess: async (result) => {
                if (requestId !== threadRequestId || selectedId.value !== id)
                    return;
                const batch = [...result.data].reverse();
                const existing = new Set(replies.value.map((item) => item.id));
                replies.value = older
                    ? [
                          ...batch.filter((item) => !existing.has(item.id)),
                          ...replies.value,
                      ]
                    : batch;
                nextReplyCursor.value = result.next_cursor;
                await nextTick();
                if (threadScroll.value)
                    threadScroll.value.scrollTop = older
                        ? threadScroll.value.scrollHeight - previousHeight
                        : threadScroll.value.scrollHeight;
            },
            onHttpException: () => {
                if (requestId === threadRequestId)
                    threadError.value =
                        'Could not load this conversation. Please try again.';
                return false;
            },
            onNetworkError: () => {
                if (requestId === threadRequestId)
                    threadError.value =
                        'Could not load this conversation. Please check your connection.';
                return false;
            },
        })
        .catch(() => {});
}

function sendReply() {
    if (selectedId.value === null || !replyDraft.value.trim()) return;
    const id = selectedId.value;
    replyForm.body = replyDraft.value;
    replyForm.post(reply.url(id), {
        preserveScroll: true,
        onSuccess: async () => {
            delete drafts[id];
            replyForm.reset();
            if (selectedId.value === id) loadThread(id);
        },
    });
}

function startConversation() {
    startForm.post(start.url(), {
        preserveScroll: true,
        onSuccess: () => {
            startForm.reset();
            showStart.value = false;
        },
    });
}

function searchConversations(older = false) {
    const email = gmailSearch.email;
    gmailSearch.before = older ? nextGmailCursor.value : null;
    if (!older) {
        gmailThreads.value = null;
        selectedThreadIds.value = [];
        importSummary.value = '';
    }
    searchError.value = '';
    void gmailSearch
        .post(searchGmail.url(), {
            onSuccess: (result) => {
                if (email !== gmailSearch.email) return;
                const existing = new Set(
                    (older ? (gmailThreads.value ?? []) : []).map(
                        (thread) => thread.id,
                    ),
                );
                gmailThreads.value = [
                    ...(older ? (gmailThreads.value ?? []) : []),
                    ...result.threads.filter(
                        (thread) => !existing.has(thread.id),
                    ),
                ];
                nextGmailCursor.value = result.next_before;
                searchedEmail.value = email;
            },
            onHttpException: () => {
                searchError.value = 'Could not search Gmail. Please try again.';
                return false;
            },
            onNetworkError: () => {
                searchError.value =
                    'Could not reach the server. Please try again.';
                return false;
            },
        })
        .catch(() => {});
}

function toggleThread(thread: GmailThread, checked: boolean | 'indeterminate') {
    selectedThreadIds.value = selectedThreadIds.value.filter(
        (id) => id !== thread.id,
    );
    if (checked === true) selectedThreadIds.value.push(thread.id);
}

async function importConversations() {
    const threads = [...selectedThreads.value];
    if (!threads.length || importing.value) return;
    if (threads.length > 100) {
        importSummary.value = 'Select up to 100 conversations per import.';
        return;
    }
    importing.value = true;
    importSummary.value = '';
    importProgress.value = 'Adding conversations to the queue…';
    importRequest.email = searchedEmail.value;
    importRequest.threads = threads.map((thread) => ({
        thread_id: thread.id,
        uid: thread.uid,
    }));
    try {
        await importRequest.post(importGmail.url(), {
            onSuccess: ({ batch_id }) => {
                sessionStorage.setItem('contact-gmail-import', batch_id);
                pollImport(batch_id);
            },
            onError: () => {
                importing.value = false;
                importProgress.value = '';
                importSummary.value =
                    'Could not start the import. Please try again.';
            },
            onHttpException: () => {
                importing.value = false;
                importProgress.value = '';
                importSummary.value =
                    'Could not start the import. Please try again.';
                return false;
            },
            onNetworkError: () => {
                importing.value = false;
                importProgress.value = '';
                importSummary.value =
                    'Could not start the import. Please check your connection.';
                return false;
            },
        });
    } catch {
        /* The request callbacks show the error. */
    }
}

function pollImport(batchId: string) {
    void importStatusRequest
        .get(importStatus.url(batchId), {
            onSuccess: (status) => {
                importProgress.value = `Queued or importing: ${status.processed} of ${status.total} complete. You can close this drawer.`;
                if (!status.finished) {
                    importPollTimer = setTimeout(
                        () => pollImport(batchId),
                        3000,
                    );
                    return;
                }
                sessionStorage.removeItem('contact-gmail-import');
                importing.value = false;
                importProgress.value = '';
                for (const thread of gmailThreads.value ?? []) {
                    if (status.imported_ids.includes(thread.id))
                        thread.imported = true;
                }
                selectedThreadIds.value = selectedThreadIds.value.filter(
                    (id) => !status.imported_ids.includes(id),
                );
                importSummary.value = `${status.total - status.failed} imported${status.failed ? `; ${status.failed} failed. Select Import to retry.` : '.'}`;
                router.reload({ only: ['messages'] });
            },
            onHttpException: (response) => {
                if (response.status === 404 || response.status === 403) {
                    sessionStorage.removeItem('contact-gmail-import');
                    importing.value = false;
                    importProgress.value = '';
                    importSummary.value = 'Import status is unavailable.';
                } else {
                    importPollTimer = setTimeout(
                        () => pollImport(batchId),
                        5000,
                    );
                }
                return false;
            },
            onNetworkError: () => {
                importProgress.value =
                    'Waiting for connection to check import status…';
                importPollTimer = setTimeout(() => pollImport(batchId), 5000);
                return false;
            },
        })
        .catch(() => {});
}

watch(
    () => [props.messages.data, props.filters.search] as const,
    ([messages, search]) => {
        if (search !== appliedSearch) {
            appliedSearch = search;
            conversations.value = [...messages];
            nextConversationCursor.value = props.messages.next_cursor;
            hasLoadedMore.value = false;
            conversationError.value = '';
        }
        const refreshedIds = new Set(messages.map((message) => message.id));
        conversations.value = [
            ...messages,
            ...conversations.value.filter(
                (message) => !refreshedIds.has(message.id),
            ),
        ];
        if (!hasLoadedMore.value)
            nextConversationCursor.value = props.messages.next_cursor;
        if (
            !conversations.value.some(
                (message) => message.id === selectedId.value,
            )
        ) {
            selectedId.value = conversations.value[0]?.id ?? null;
            showMobileThread.value = false;
            replyForm.clearErrors();
            if (selectedId.value !== null) loadThread(selectedId.value);
        }
    },
);

onMounted(() => {
    if (selectedId.value !== null) loadThread(selectedId.value);
    const batchId = sessionStorage.getItem('contact-gmail-import');
    if (batchId) {
        importing.value = true;
        pollImport(batchId);
    }
});
onUnmounted(() => {
    clearTimeout(importPollTimer);
    importStatusRequest.cancel();
});
</script>

<template>
    <div class="mx-auto flex w-full max-w-[1600px] flex-col gap-5 p-4 md:p-6">
        <Head title="Contact" />
        <Dialog v-model:open="showDisconnect">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Disconnect Gmail?</DialogTitle>
                    <DialogDescription
                        >This revokes this app's Gmail access and stops sending
                        and syncing. Conversation history stays available. You
                        can reconnect the same account later.</DialogDescription
                    >
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showDisconnect = false"
                        >Cancel</Button
                    >
                    <Button
                        :disabled="disconnectForm.processing"
                        @click="
                            disconnectForm.delete(disconnect.url(), {
                                preserveScroll: true,
                                onSuccess: () => {
                                    showDisconnect = false;
                                },
                            })
                        "
                        >{{
                            disconnectForm.processing
                                ? 'Disconnecting…'
                                : 'Disconnect Gmail'
                        }}</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p
                    class="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                >
                    Communications
                </p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Contact inbox
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Keep website conversations together, from first message to
                    follow-up.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="can('contact.manage') && gmail.email"
                    variant="outline"
                    size="sm"
                    @click="showImport = true"
                >
                    <Download class="size-4" /> Import from Gmail
                </Button>
                <Button
                    v-if="can('contact.manage') && gmail.email"
                    variant="outline"
                    size="sm"
                    :disabled="syncForm.processing"
                    @click="
                        syncForm.post(sync.url(), {
                            preserveScroll: true,
                            onSuccess: () => {
                                if (selectedId !== null) loadThread(selectedId);
                            },
                        })
                    "
                >
                    <RefreshCw
                        class="size-4"
                        :class="{ 'animate-spin': syncForm.processing }"
                    />
                    {{ syncForm.processing ? 'Syncing…' : 'Sync Gmail' }}
                </Button>
                <Button
                    v-if="can('contact.reply') && gmail.email"
                    size="sm"
                    @click="showStart = true"
                >
                    <PenLine class="size-4" /> New conversation
                </Button>
            </div>
        </header>
        <p
            v-if="importProgress || importSummary"
            role="status"
            class="text-sm text-muted-foreground"
        >
            {{ importProgress || importSummary }}
        </p>

        <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted/30 px-4 py-3 text-sm"
        >
            <div class="flex min-w-0 items-center gap-2">
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-background text-muted-foreground ring-1 ring-border"
                    ><Mail class="size-4"
                /></span>
                <div class="min-w-0">
                    <p v-if="gmail.email" class="truncate font-medium">
                        Gmail connected
                        <span class="font-normal text-muted-foreground"
                            >· {{ gmail.email }}</span
                        >
                    </p>
                    <p v-else class="font-medium">Gmail not connected</p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            gmail.email
                                ? 'Website and selected Gmail conversations are shown here.'
                                : 'Connect Gmail to send and sync conversations.'
                        }}
                    </p>
                </div>
            </div>
            <Button
                v-if="can('contact.manage') && gmail.email"
                variant="outline"
                size="sm"
                @click="showDisconnect = true"
                >Disconnect Gmail</Button
            >
            <Button
                v-if="can('contact.manage') && gmail.configured"
                as-child
                variant="outline"
                size="sm"
                ><a :href="connect.url()">{{
                    gmail.email ? 'Reconnect' : 'Connect Gmail'
                }}</a></Button
            >
            <p
                v-else-if="can('contact.manage') && !gmail.configured"
                class="text-xs text-muted-foreground"
            >
                Configure Google OAuth credentials first.
            </p>
            <InputError :message="page.props.errors.gmail" />
        </div>
        <div
            v-if="can('contact.manage') && !gmail.email"
            class="space-y-1 text-xs text-muted-foreground"
        >
            <p>
                Google requests full mailbox access for IMAP. Only website
                conversations you select are displayed here.
            </p>
            <p class="break-all">
                Google OAuth redirect URI: {{ gmail.callback_url }}
            </p>
        </div>

        <section
            class="overflow-hidden rounded-xl border bg-card shadow-sm"
            aria-label="Contact conversations"
        >
            <div
                class="grid min-h-[560px] md:h-[min(72vh,760px)] md:min-h-[560px] md:grid-cols-[minmax(250px,320px)_minmax(0,1fr)]"
            >
                <aside
                    :class="showMobileThread ? 'hidden md:flex' : 'flex'"
                    class="min-h-0 flex-col border-border md:border-r"
                    aria-label="Conversation list"
                >
                    <div
                        class="flex items-center justify-between border-b px-4 py-4"
                    >
                        <div>
                            <h2 class="font-semibold">Conversations</h2>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ conversations.length }} loaded
                            </p>
                        </div>
                        <Inbox class="size-4 text-muted-foreground" />
                    </div>
                    <form
                        class="flex gap-2 border-b p-3"
                        @submit.prevent="searchInbox"
                    >
                        <Label for="conversation-search" class="sr-only"
                            >Search conversations</Label
                        >
                        <Input
                            id="conversation-search"
                            v-model="conversationSearch"
                            maxlength="100"
                            placeholder="Search name, email or subject"
                        />
                        <Button type="submit" variant="outline">Search</Button>
                    </form>
                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <button
                            v-for="message in conversations"
                            :key="message.id"
                            type="button"
                            :aria-pressed="selectedId === message.id"
                            class="flex w-full items-start gap-3 border-b px-4 py-4 text-left transition-colors hover:bg-muted/60 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-ring"
                            :class="
                                selectedId === message.id ? 'bg-muted/70' : ''
                            "
                            @click="openThread(message.id)"
                        >
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                                >{{
                                    message.name
                                        .trim()
                                        .charAt(0)
                                        .toUpperCase() || '?'
                                }}</span
                            >
                            <span class="min-w-0 flex-1">
                                <span
                                    class="flex items-start justify-between gap-2"
                                    ><span
                                        class="truncate text-sm font-medium"
                                        >{{ message.name }}</span
                                    ><time
                                        class="shrink-0 text-[11px] text-muted-foreground"
                                        :datetime="
                                            message.latest_reply?.created_at ??
                                            message.created_at
                                        "
                                        >{{
                                            formatDate(
                                                message.latest_reply
                                                    ?.created_at ??
                                                    message.created_at,
                                                true,
                                            )
                                        }}</time
                                    ></span
                                >
                                <span
                                    class="mt-0.5 block truncate text-xs text-muted-foreground"
                                    >{{
                                        message.subject || message.email
                                    }}</span
                                >
                                <span
                                    class="mt-1.5 block truncate text-xs text-muted-foreground"
                                    >{{ preview(message) }}</span
                                >
                            </span>
                        </button>
                        <div
                            v-if="!conversations.length"
                            class="flex flex-col items-center gap-2 px-6 py-14 text-center text-sm text-muted-foreground"
                        >
                            <MessageSquare class="size-6" />
                            <p>
                                {{
                                    appliedSearch
                                        ? 'No conversations match your search.'
                                        : 'No conversations yet.'
                                }}
                            </p>
                            <p v-if="!appliedSearch" class="text-xs">
                                Website messages and imported conversations will
                                appear here.
                            </p>
                        </div>
                    </div>
                    <div
                        v-if="nextConversationCursor || conversationError"
                        class="space-y-2 border-t px-3 py-3"
                    >
                        <p
                            v-if="conversationError"
                            role="alert"
                            class="text-xs text-destructive"
                        >
                            {{ conversationError }}
                        </p>
                        <Button
                            v-if="nextConversationCursor"
                            class="w-full"
                            variant="outline"
                            size="sm"
                            :disabled="listRequest.processing"
                            @click="loadMoreConversations"
                            >{{
                                listRequest.processing
                                    ? 'Loading…'
                                    : 'Load more'
                            }}</Button
                        >
                    </div>
                </aside>

                <div
                    :class="showMobileThread ? 'flex' : 'hidden md:flex'"
                    class="min-h-0 min-w-0 flex-col"
                >
                    <template v-if="selected">
                        <div
                            class="flex items-center gap-3 border-b px-4 py-4 sm:px-6"
                        >
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="md:hidden"
                                aria-label="Back to conversations"
                                @click="showMobileThread = false"
                                ><ArrowLeft
                            /></Button>
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                                >{{
                                    selected.name
                                        .trim()
                                        .charAt(0)
                                        .toUpperCase() || '?'
                                }}</span
                            >
                            <div class="min-w-0 flex-1">
                                <h2 class="truncate font-semibold">
                                    {{ selected.subject || selected.name }}
                                </h2>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{
                                        selected.name === selected.email
                                            ? selected.email
                                            : `${selected.name} · ${selected.email}`
                                    }}
                                </p>
                            </div>
                            <span
                                class="hidden items-center gap-1.5 text-xs text-muted-foreground sm:flex"
                                ><ShieldCheck class="size-3.5" />
                                {{
                                    selected.subject !== null
                                        ? 'Gmail conversation'
                                        : 'Website conversation'
                                }}</span
                            >
                        </div>
                        <div
                            ref="threadScroll"
                            class="min-h-[260px] flex-1 space-y-5 overflow-y-auto bg-muted/20 px-4 py-6 sm:px-6"
                            aria-label="Conversation messages"
                            role="log"
                            aria-live="polite"
                        >
                            <div
                                v-if="nextReplyCursor && !threadError"
                                class="flex justify-center"
                            >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="threadRequest.processing"
                                    @click="loadThread(selected.id, true)"
                                    >{{
                                        threadRequest.processing
                                            ? 'Loading…'
                                            : 'Load older messages'
                                    }}</Button
                                >
                            </div>
                            <div
                                v-if="threadError"
                                role="alert"
                                class="space-y-2 text-center text-sm text-destructive"
                            >
                                <p>{{ threadError }}</p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="
                                        loadThread(
                                            selected.id,
                                            nextReplyCursor !== null,
                                        )
                                    "
                                    >Try again</Button
                                >
                            </div>
                            <div
                                v-if="
                                    selected.phone || selected.referral_source
                                "
                                class="mx-auto flex max-w-2xl flex-wrap gap-x-4 gap-y-1 rounded-lg border bg-background px-3 py-2 text-xs text-muted-foreground"
                            >
                                <span v-if="selected.phone" class="break-words"
                                    >Phone: {{ selected.phone }}</span
                                ><span
                                    v-if="selected.referral_source"
                                    class="break-words"
                                    >Source:
                                    {{ selected.referral_source }}</span
                                >
                            </div>
                            <div
                                v-for="item in timeline"
                                :key="item.id"
                                class="flex"
                                :class="
                                    item.incoming
                                        ? 'justify-start'
                                        : 'justify-end'
                                "
                            >
                                <div class="max-w-[90%] min-w-0 sm:max-w-[75%]">
                                    <div
                                        class="mb-1.5 flex flex-wrap gap-x-2 text-xs text-muted-foreground"
                                        :class="
                                            item.incoming ? '' : 'justify-end'
                                        "
                                    >
                                        <span class="font-medium">{{
                                            item.incoming
                                                ? selected.name
                                                : 'You'
                                        }}</span
                                        ><time :datetime="item.created_at">{{
                                            formatDate(item.created_at)
                                        }}</time>
                                    </div>
                                    <p
                                        class="rounded-2xl px-4 py-3 text-sm leading-relaxed break-words whitespace-pre-wrap"
                                        :class="
                                            item.incoming
                                                ? 'rounded-tl-sm border bg-background'
                                                : 'rounded-tr-sm bg-primary text-primary-foreground'
                                        "
                                    >
                                        {{ item.body }}
                                    </p>
                                </div>
                            </div>
                            <p
                                v-if="
                                    threadRequest.processing && !replies.length
                                "
                                class="py-10 text-center text-sm text-muted-foreground"
                            >
                                Loading conversation…
                            </p>
                            <p
                                v-else-if="!timeline.length && !threadError"
                                class="py-10 text-center text-sm text-muted-foreground"
                            >
                                No messages in this conversation yet.
                            </p>
                        </div>
                        <div class="border-t bg-background px-4 py-4 sm:px-6">
                            <form
                                v-if="can('contact.reply') && gmail.email"
                                class="space-y-2"
                                @submit.prevent="sendReply"
                            >
                                <Label :for="`reply-${selected.id}`"
                                    >Reply to {{ selected.name }}</Label
                                >
                                <textarea
                                    :id="`reply-${selected.id}`"
                                    v-model="replyDraft"
                                    required
                                    maxlength="10000"
                                    rows="3"
                                    placeholder="Write your reply…"
                                    class="w-full resize-y rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError :message="replyForm.errors.body" />
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <p class="text-xs text-muted-foreground">
                                        Replies are sent from your connected
                                        Gmail account.
                                    </p>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        :disabled="
                                            replyForm.processing ||
                                            !replyDraft.trim()
                                        "
                                        ><Send class="size-4" />{{
                                            replyForm.processing
                                                ? 'Sending…'
                                                : 'Send reply'
                                        }}</Button
                                    >
                                </div>
                            </form>
                            <p
                                v-else-if="can('contact.reply')"
                                class="text-sm text-muted-foreground"
                            >
                                Connect Gmail to reply to this conversation.
                            </p>
                        </div>
                    </template>
                    <div
                        v-else
                        class="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center text-muted-foreground"
                    >
                        <MessageSquare class="size-8" />
                        <div>
                            <p class="font-medium text-foreground">
                                Select a conversation
                            </p>
                            <p class="mt-1 text-sm">
                                Messages and replies will appear here.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <p class="text-xs text-muted-foreground">
            Attachments and very long messages can be viewed in Gmail.
        </p>

        <Sheet v-model:open="showImport">
            <SheetContent side="right" class="w-full gap-0 sm:max-w-xl">
                <SheetHeader class="border-b pr-12">
                    <SheetTitle>Import from Gmail</SheetTitle>
                    <SheetDescription
                        >Search by client email and choose a conversation to
                        sync.</SheetDescription
                    >
                </SheetHeader>
                <div
                    class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4 sm:p-6"
                >
                    <form
                        class="space-y-3"
                        @submit.prevent="searchConversations()"
                    >
                        <Label for="gmail-client-email">Client email</Label>
                        <div class="flex gap-2">
                            <Input
                                id="gmail-client-email"
                                v-model="gmailSearch.email"
                                type="email"
                                required
                                maxlength="255"
                                :disabled="importing"
                                @input="
                                    gmailThreads = null;
                                    nextGmailCursor = null;
                                    selectedThreadIds = [];
                                    importSummary = '';
                                "
                            /><Button
                                type="submit"
                                :disabled="gmailSearch.processing || importing"
                                >{{
                                    gmailSearch.processing
                                        ? 'Searching…'
                                        : 'Search'
                                }}</Button
                            >
                        </div>
                        <InputError
                            :message="gmailSearch.errors.email || searchError"
                        />
                    </form>
                    <p class="text-xs text-muted-foreground">
                        Finds messages exchanged directly with this address,
                        including CC. Gmail's general search also finds mentions
                        in message text. Import each conversation you want to
                        sync.
                    </p>
                    <div v-if="gmailThreads" class="space-y-2">
                        <div
                            v-if="gmailThreads.length"
                            class="flex items-center justify-between gap-3 border-b py-3"
                        >
                            <label
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <Checkbox
                                    :model-value="selectAllState"
                                    :disabled="
                                        !selectableThreads.length || importing
                                    "
                                    @update:model-value="
                                        (value) => {
                                            selectedThreadIds =
                                                value === true
                                                    ? selectableThreads.map(
                                                          (thread) => thread.id,
                                                      )
                                                    : [];
                                        }
                                    "
                                />
                                Select all loaded
                            </label>
                            <span class="text-xs text-muted-foreground"
                                >{{ selectedThreads.length }} selected ·
                                {{ gmailThreads.length }} loaded</span
                            >
                        </div>
                        <p
                            v-if="!gmailThreads.length && !nextGmailCursor"
                            class="py-4 text-center text-sm text-muted-foreground"
                        >
                            No matching conversations found.
                        </p>
                        <div
                            v-for="thread in gmailThreads"
                            :key="thread.id"
                            class="flex items-start gap-3 rounded-lg border p-3"
                        >
                            <Checkbox
                                :id="`gmail-thread-${thread.id}`"
                                class="mt-1"
                                :model-value="
                                    selectedThreadIds.includes(thread.id)
                                "
                                :disabled="thread.imported || importing"
                                @update:model-value="
                                    (value) => toggleThread(thread, value)
                                "
                            />
                            <label
                                :for="`gmail-thread-${thread.id}`"
                                class="min-w-0 flex-1 cursor-pointer"
                            >
                                <p class="truncate text-sm font-medium">
                                    {{ thread.subject }}
                                </p>
                                <p
                                    v-if="thread.date"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ formatDate(thread.date, true) }}
                                </p>
                            </label>
                            <span
                                v-if="thread.imported"
                                class="text-xs text-muted-foreground"
                                >Imported</span
                            >
                        </div>
                        <Button
                            v-if="nextGmailCursor"
                            type="button"
                            variant="outline"
                            class="w-full"
                            :disabled="gmailSearch.processing || importing"
                            @click="searchConversations(true)"
                            >{{
                                gmailSearch.processing
                                    ? 'Searching…'
                                    : 'Load more'
                            }}</Button
                        >
                    </div>
                </div>
                <div class="space-y-2 border-t p-4 sm:px-6" aria-live="polite">
                    <p
                        v-if="importProgress || importSummary"
                        class="text-sm text-muted-foreground"
                    >
                        {{ importProgress || importSummary }}
                    </p>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showImport = false"
                            >Close</Button
                        >
                        <Button
                            type="button"
                            :disabled="
                                !selectedThreads.length ||
                                importing ||
                                gmailSearch.processing
                            "
                            @click="importConversations"
                            >{{
                                importing
                                    ? 'Importing…'
                                    : `Import selected (${selectedThreads.length})`
                            }}</Button
                        >
                    </div>
                </div>
            </SheetContent>
        </Sheet>

        <Dialog v-model:open="showStart">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader
                    ><DialogTitle>New conversation</DialogTitle
                    ><DialogDescription
                        >Start a conversation from your connected Gmail
                        account.</DialogDescription
                    ></DialogHeader
                >
                <form
                    id="start-contact-conversation"
                    class="space-y-4"
                    @submit.prevent="startConversation"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="contact-name">Name</Label
                            ><Input
                                id="contact-name"
                                v-model="startForm.name"
                                required
                                maxlength="255"
                                autocomplete="name"
                            /><InputError :message="startForm.errors.name" />
                        </div>
                        <div class="space-y-2">
                            <Label for="contact-email">Email</Label
                            ><Input
                                id="contact-email"
                                v-model="startForm.email"
                                required
                                type="email"
                                maxlength="255"
                                autocomplete="email"
                            /><InputError :message="startForm.errors.email" />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="contact-body">Message</Label
                        ><textarea
                            id="contact-body"
                            v-model="startForm.body"
                            required
                            maxlength="10000"
                            rows="5"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        /><InputError :message="startForm.errors.body" />
                    </div>
                </form>
                <DialogFooter
                    ><Button
                        type="button"
                        variant="outline"
                        :disabled="startForm.processing"
                        @click="showStart = false"
                        >Cancel</Button
                    ><Button
                        type="submit"
                        form="start-contact-conversation"
                        :disabled="startForm.processing"
                        ><Send class="size-4" />{{
                            startForm.processing ? 'Sending…' : 'Send message'
                        }}</Button
                    ></DialogFooter
                >
            </DialogContent>
        </Dialog>
    </div>
</template>
