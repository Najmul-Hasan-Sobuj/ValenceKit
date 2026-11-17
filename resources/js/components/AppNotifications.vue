<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowRight,
    Bell,
    BellRing,
    CheckCheck,
    Check,
    Mail,
    Trash2,
    Inbox,
    LoaderCircle,
    RefreshCw,
} from '@lucide/vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { index, read, readAll, destroy } from '@/routes/admin/notifications';
import * as userNotifications from '@/routes/notifications';
import { auth as adminBroadcastAuth } from '@/routes/admin/broadcasting';
import { auth as userBroadcastAuth } from '@/routes/broadcasting';

const props = withDefaults(defineProps<{ scope?: 'admin' | 'user' }>(), {
    scope: 'admin',
});
const endpoints =
    props.scope === 'user'
        ? userNotifications
        : { index, read, readAll, destroy };

type Notice = {
    id: string;
    title: string;
    body: string;
    url: string;
    read_at: string | null;
    created_at: string;
};
type Settings = {
    key: string | null;
    host: string;
    port: number;
    scheme: string;
};
const page = usePage<{
    auth: { admin: { id: number } | null; user: { id: number } | null };
    reverb: Settings | null;
}>();
const notices = ref<Notice[]>([]);
const unread = ref(0);
const cursor = ref<string | null>(null);
const loading = ref(false);
const error = ref('');
const open = ref(false);
let echo: Echo<'reverb'> | undefined;
const pendingNotifications = new Set<string>();
const shownNotifications = new Set<string>();
const busy = ref(false);
const deleting = ref<Notice | null>(null);
let disposed = false;
const connected = ref(false);
let fallbackTimer: ReturnType<typeof setInterval> | undefined;
function refreshWhenVisible() {
    if (!document.hidden && !connected.value) void load();
}
let stateVersion = 0;
let refreshPending = false;
const csrf = () =>
    decodeURIComponent(
        document.cookie
            .split('; ')
            .find((item) => item.startsWith('XSRF-TOKEN='))
            ?.slice(11) ?? '',
    );

async function request(
    url: string,
    method = 'GET',
    data?: Record<string, boolean>,
) {
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': csrf(),
            'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: data ? JSON.stringify(data) : undefined,
    });
    if (!response.ok)
        throw new Error('Could not load notifications. Please try again.');
    return response.json();
}

async function load(older = false, notificationId?: string) {
    if (disposed) return;
    if (notificationId && !shownNotifications.has(notificationId))
        pendingNotifications.add(notificationId);
    if (loading.value) {
        if (!older) {
            refreshPending = true;
        }
        return;
    }
    loading.value = true;
    const version = stateVersion;
    const requestedNotifications = new Set(pendingNotifications);
    error.value = '';
    try {
        const result = (await request(
            endpoints.index.url({
                query: older && cursor.value ? { cursor: cursor.value } : {},
            }),
        )) as {
            data: Notice[];
            next_cursor: string | null;
            unread_count: number;
        };
        if (disposed) return;
        if (version !== stateVersion) {
            refreshPending = true;
            return;
        }
        notices.value = older
            ? [
                  ...notices.value,
                  ...result.data.filter(
                      (item) =>
                          !notices.value.some(
                              (existing) => existing.id === item.id,
                          ),
                  ),
              ]
            : result.data;
        cursor.value = result.next_cursor;
        unread.value = result.unread_count;
        for (const notice of [...result.data].reverse()) {
            if (
                !pendingNotifications.delete(notice.id) ||
                notice.read_at ||
                shownNotifications.has(notice.id)
            )
                continue;
            shownNotifications.add(notice.id);
            if (shownNotifications.size > 200)
                shownNotifications.delete(
                    shownNotifications.values().next().value!,
                );
            toast.info(notice.title, {
                id: notice.id,
                icon: BellRing,
                description: notice.body,
                duration: 7000,
                closeButton: true,
                action: {
                    label: 'Open',
                    onClick: () => {
                        void select(notice);
                    },
                },
                classes: {
                    toast: 'border-l-4! border-l-primary! shadow-xl!',
                    icon: 'text-primary!',
                    title: 'font-semibold!',
                    description: 'text-muted-foreground!',
                    actionButton: 'bg-primary! text-primary-foreground!',
                },
            });
        }
        for (const id of requestedNotifications)
            pendingNotifications.delete(id);
    } catch {
        error.value = 'Could not load notifications. Please try again.';
    } finally {
        loading.value = false;
        if (refreshPending && !disposed) {
            refreshPending = false;
            void load();
        }
    }
}

async function select(notice: Notice) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        await updateRead(notice, true);
        open.value = false;
        router.visit(notice.url);
    } catch {
        error.value = 'Could not open this notification. Please try again.';
    } finally {
        busy.value = false;
    }
}

async function updateRead(notice: Notice, isRead: boolean) {
    const result = (await request(endpoints.read.url(notice.id), 'PATCH', {
        read: isRead,
    })) as { read_at: string | null; unread_count: number };
    stateVersion++;
    const loaded = notices.value.find((item) => item.id === notice.id);
    if (loaded) loaded.read_at = result.read_at;
    unread.value = result.unread_count;
    toast.dismiss(notice.id);
}

async function toggleRead(notice: Notice) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        await updateRead(notice, !notice.read_at);
    } catch {
        error.value = 'Could not update this notification. Please try again.';
    } finally {
        busy.value = false;
    }
}

async function deleteNotice() {
    if (!deleting.value || busy.value) return;
    busy.value = true;
    error.value = '';
    const notice = deleting.value;
    try {
        const result = (await request(
            endpoints.destroy.url(notice.id),
            'DELETE',
        )) as {
            unread_count: number;
        };
        stateVersion++;
        notices.value = notices.value.filter((item) => item.id !== notice.id);
        unread.value = result.unread_count;
        toast.dismiss(notice.id);
        deleting.value = null;
    } catch {
        error.value = 'Could not delete this notification. Please try again.';
    } finally {
        busy.value = false;
    }
}

async function markAll() {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        const result = (await request(endpoints.readAll.url(), 'PATCH')) as {
            unread_count: number;
        };
        stateVersion++;
        notices.value.forEach((notice) => {
            notice.read_at = notice.read_at ?? new Date().toISOString();
            toast.dismiss(notice.id);
        });
        unread.value = result.unread_count;
    } catch {
        error.value = 'Could not mark notifications as read.';
    } finally {
        busy.value = false;
    }
}

onMounted(() => {
    void load();
    fallbackTimer = setInterval(refreshWhenVisible, 45000);
    document.addEventListener('visibilitychange', refreshWhenVisible);
    const settings = page.props.reverb;
    const account = page.props.auth[props.scope === 'user' ? 'user' : 'admin'];
    if (!settings?.key || !account) return;
    echo = new Echo({
        broadcaster: 'reverb',
        client: new Pusher(settings.key, {
            wsHost: settings.host,
            wsPort: Number(settings.port),
            wssPort: Number(settings.port),
            forceTLS: settings.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            cluster: '',
            channelAuthorization: {
                endpoint: (props.scope === 'user'
                    ? userBroadcastAuth
                    : adminBroadcastAuth
                ).url(),
                transport: 'ajax',
                headersProvider: () => ({ 'X-XSRF-TOKEN': csrf() }),
            },
        }),
    });
    echo.private(
        `App.Models.${props.scope === 'user' ? 'User' : 'Admin'}.${account.id}`,
    ).notification((notice: { id: string }) => {
        void load(false, notice.id);
    });
    echo.connector.pusher.connection.bind(
        'state_change',
        (state: { current: string }) => {
            connected.value = state.current === 'connected';
            if (connected.value) void load();
        },
    );
});
onUnmounted(() => {
    disposed = true;
    clearInterval(fallbackTimer);
    document.removeEventListener('visibilitychange', refreshWhenVisible);
    echo?.disconnect();
});
</script>

<template>
    <div class="ml-auto">
        <Popover
            v-model:open="open"
            @update:open="
                (value) => {
                    if (value) void load();
                }
            "
        >
            <PopoverTrigger as-child>
                <Button
                    variant="ghost"
                    size="icon"
                    :aria-label="`Notifications, ${unread} unread`"
                    class="relative rounded-xl border border-transparent transition-colors hover:border-border hover:bg-muted data-[state=open]:border-primary/20 data-[state=open]:bg-primary/10 data-[state=open]:text-primary"
                >
                    <Bell class="size-5" />
                    <span
                        v-if="unread"
                        class="absolute -top-1 -right-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground ring-2 ring-background"
                        >{{ unread > 99 ? '99+' : unread }}</span
                    >
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="end"
                :side-offset="12"
                class="w-[min(400px,calc(100vw-2rem))] overflow-hidden rounded-2xl border-border/80 p-0 shadow-xl"
            >
                <div
                    class="flex items-center gap-3 border-b bg-primary/5 px-4 py-4"
                >
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15"
                        ><BellRing class="size-5"
                    /></span>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-base font-semibold tracking-tight">
                            Notifications
                            <span
                                v-if="!connected"
                                class="ml-1 text-xs font-normal text-muted-foreground"
                                >Periodic updates</span
                            >
                        </h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{
                                unread
                                    ? `${unread} unread update${unread === 1 ? '' : 's'}`
                                    : 'Everything is up to date'
                            }}
                        </p>
                    </div>
                    <Button
                        v-if="unread"
                        variant="ghost"
                        size="icon-sm"
                        class="shrink-0 text-primary"
                        title="Mark all as read"
                        aria-label="Mark all as read"
                        :disabled="busy"
                        @click="markAll"
                        ><CheckCheck class="size-4"
                    /></Button>
                </div>
                <div
                    class="max-h-[min(420px,60dvh)] space-y-1 overflow-y-auto overscroll-contain p-2"
                >
                    <div
                        v-for="notice in notices"
                        :key="notice.id"
                        class="group flex w-full items-start gap-1 rounded-xl border text-left transition-colors"
                        :class="
                            !notice.read_at
                                ? 'border-primary/10 bg-primary/5 hover:bg-primary/10'
                                : 'border-transparent hover:border-border/60 hover:bg-muted/60'
                        "
                    >
                        <button
                            type="button"
                            class="flex min-w-0 flex-1 items-start gap-3 rounded-xl p-3 text-left focus-visible:outline-2 focus-visible:outline-ring disabled:opacity-60"
                            :disabled="busy"
                            :aria-label="`Open ${notice.title}`"
                            title="Open related page"
                            @click="select(notice)"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-full"
                                :class="
                                    !notice.read_at
                                        ? 'bg-primary/10 text-primary'
                                        : 'bg-muted text-muted-foreground'
                                "
                                ><Bell class="size-4"
                            /></span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="flex items-start justify-between gap-2"
                                >
                                    <span
                                        class="text-sm leading-5 break-words"
                                        :class="
                                            !notice.read_at
                                                ? 'font-semibold'
                                                : 'font-medium'
                                        "
                                        >{{ notice.title }}</span
                                    >
                                    <span
                                        v-if="!notice.read_at"
                                        class="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                                        aria-label="Unread"
                                    />
                                </span>
                                <span
                                    class="mt-1 line-clamp-2 block text-xs leading-relaxed break-words text-muted-foreground"
                                    >{{ notice.body }}</span
                                >
                                <span
                                    class="mt-2 flex items-center justify-between gap-2"
                                >
                                    <time
                                        class="text-[11px] text-muted-foreground"
                                        :datetime="notice.created_at"
                                        :title="
                                            new Date(
                                                notice.created_at,
                                            ).toLocaleString()
                                        "
                                        >{{
                                            new Date(
                                                notice.created_at,
                                            ).toLocaleDateString(undefined, {
                                                month: 'short',
                                                day: 'numeric',
                                                hour: 'numeric',
                                                minute: '2-digit',
                                            })
                                        }}</time
                                    >
                                </span>
                            </span>
                        </button>
                        <div class="flex shrink-0 flex-col gap-1 pt-2 pr-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :disabled="busy"
                                title="Open related page"
                                :aria-label="`Open related page: ${notice.title}`"
                                @click="select(notice)"
                                ><ArrowRight class="size-4"
                            /></Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                :disabled="busy"
                                :title="
                                    notice.read_at
                                        ? 'Mark as unread'
                                        : 'Mark as read'
                                "
                                :aria-label="`${notice.read_at ? 'Mark as unread' : 'Mark as read'}: ${notice.title}`"
                                @click="toggleRead(notice)"
                                ><component
                                    :is="notice.read_at ? Mail : Check"
                                    class="size-4"
                            /></Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                :disabled="busy"
                                title="Delete notification"
                                :aria-label="`Delete notification: ${notice.title}`"
                                @click="deleting = notice"
                                ><Trash2 class="size-4"
                            /></Button>
                        </div>
                    </div>
                    <div
                        v-if="!notices.length && !loading && !error"
                        class="flex flex-col items-center px-6 py-10 text-center"
                    >
                        <span
                            class="mb-4 flex size-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground"
                            ><Inbox class="size-6"
                        /></span>
                        <p class="text-sm font-semibold">
                            You're all caught up
                        </p>
                        <p
                            class="mt-1.5 max-w-60 text-xs leading-relaxed text-muted-foreground"
                        >
                            New updates will appear here, so you can keep track
                            of what needs your attention.
                        </p>
                    </div>
                </div>
                <div
                    v-if="error"
                    class="mx-3 mb-3 flex items-center gap-2 rounded-lg bg-destructive/10 p-3 text-xs text-destructive"
                    role="alert"
                >
                    {{ error
                    }}<Button variant="ghost" size="sm" @click="load()"
                        ><RefreshCw class="size-3.5" />Retry</Button
                    >
                </div>
                <p
                    v-if="loading"
                    class="flex items-center justify-center gap-2 border-t p-3 text-xs text-muted-foreground"
                    role="status"
                >
                    <LoaderCircle class="size-4 animate-spin" />Loading updates…
                </p>
                <Button
                    v-else-if="cursor"
                    variant="ghost"
                    class="h-11 w-full rounded-none border-t text-xs font-medium text-muted-foreground hover:text-foreground"
                    @click="load(true)"
                    ><ArrowDown class="size-3.5" />Load older
                    notifications</Button
                >
            </PopoverContent>
        </Popover>
        <AlertDialog
            :open="deleting !== null"
            @update:open="
                (value) => {
                    if (!value && !busy) deleting = null;
                }
            "
        >
            <AlertDialogContent class="sm:max-w-md">
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete notification?</AlertDialogTitle>
                    <AlertDialogDescription
                        >“{{ deleting?.title }}” will be permanently removed
                        from your notifications. The related record will stay
                        unchanged.</AlertDialogDescription
                    >
                </AlertDialogHeader>
                <p v-if="error" class="text-sm text-destructive" role="alert">
                    {{ error }}
                </p>
                <AlertDialogFooter>
                    <AlertDialogCancel :disabled="busy"
                        >Cancel</AlertDialogCancel
                    >
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="busy"
                        @click="deleteNotice"
                        >{{
                            busy ? 'Deleting…' : 'Delete notification'
                        }}</Button
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
