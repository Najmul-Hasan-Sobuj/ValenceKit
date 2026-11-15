import { afterEach, beforeEach, expect, test, vi } from 'vitest';
import AdminNotifications from './AppNotifications.vue';

const mocks = vi.hoisted(() => ({
    info: vi.fn(),
    dismiss: vi.fn(),
    fetch: vi.fn(),
    visit: vi.fn(),
}));
vi.mock('vue-sonner', () => ({
    toast: { info: mocks.info, dismiss: mocks.dismiss },
}));
vi.mock('@inertiajs/vue3', () => ({
    router: { visit: mocks.visit },
    usePage: () => ({ props: { auth: { admin: null }, reverb: null } }),
}));
vi.mock('vue', async (original) => ({
    ...(await original<typeof import('vue')>()),
    onMounted: vi.fn(),
    onUnmounted: vi.fn(),
    useSSRContext: () => ({}),
}));

type Inbox = {
    load: (older?: boolean, id?: string) => Promise<void>;
    notices: { value: { id: string; read_at: string | null }[] };
    toggleRead: (notice: {
        id: string;
        read_at: string | null;
    }) => Promise<void>;
    unread: { value: number };
    busy: { value: boolean };
    error: { value: string };
    select: (notice: {
        id: string;
        read_at: string | null;
        url?: string;
    }) => Promise<void>;
    deleting: { value: { id: string; read_at: string | null } | null };
    deleteNotice: () => Promise<void>;
};
function inbox(scope: 'admin' | 'user' = 'admin'): Inbox {
    return (
        AdminNotifications as unknown as {
            setup: (props: object, context: object) => Inbox;
        }
    ).setup({ scope }, { expose: vi.fn() });
}
function response(ids: string[]) {
    return {
        ok: true,
        json: async () => ({
            data: ids.map((id) => ({
                id,
                title: 'Test notification',
                body: 'Hello',
                url: '/admin/dashboard',
                read_at: null,
                created_at: '2026-11-15T00:00:00Z',
            })),
            next_cursor: null,
            unread_count: ids.length,
        }),
    };
}

beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal('document', { cookie: '' });
    vi.stubGlobal('fetch', mocks.fetch);
});

afterEach(() => vi.unstubAllGlobals());

test('user inbox uses web endpoints for loading and notification actions', async () => {
    const panel = inbox('user');
    mocks.fetch.mockResolvedValueOnce(response(['one']));
    await panel.load();
    expect(mocks.fetch.mock.calls[0][0]).toBe('/notifications');
    mocks.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ read_at: null, unread_count: 1 }),
    });
    await panel.toggleRead({ id: 'one', read_at: null });
    expect(mocks.fetch.mock.calls[1][0]).toBe('/notifications/one/read');
    panel.deleting.value = { id: 'one', read_at: null };
    mocks.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ unread_count: 0 }),
    });
    await panel.deleteNotice();
    expect(mocks.fetch.mock.calls[2][0]).toBe('/notifications/one');
});

test('opening a notification marks it read and visits its destination', async () => {
    mocks.fetch.mockResolvedValueOnce(response(['one']));
    const panel = inbox();
    await panel.load();
    mocks.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
            read_at: '2026-11-15T01:00:00Z',
            unread_count: 0,
        }),
    });
    await panel.select(panel.notices.value[0]);
    expect(mocks.visit).toHaveBeenCalledWith('/admin/dashboard');
    expect(panel.unread.value).toBe(0);
});

test('deleting keeps the selected notification until the request succeeds', async () => {
    mocks.fetch.mockResolvedValueOnce(response(['one']));
    const panel = inbox();
    await panel.load();
    panel.deleting.value = panel.notices.value[0];
    let finish!: (value: object) => void;
    mocks.fetch.mockReturnValueOnce(
        new Promise((resolve) => {
            finish = resolve;
        }),
    );
    const deletion = panel.deleteNotice();
    expect(panel.deleting.value?.id).toBe('one');
    expect(panel.busy.value).toBe(true);
    await panel.deleteNotice();
    expect(mocks.fetch).toHaveBeenCalledTimes(2);
    expect(mocks.fetch.mock.calls[1][1].method).toBe('DELETE');
    finish({ ok: true, json: async () => ({ unread_count: 0 }) });
    await deletion;
    expect(panel.notices.value).toHaveLength(0);
    expect(panel.deleting.value).toBeNull();
    expect(panel.unread.value).toBe(0);
    expect(panel.busy.value).toBe(false);
});

test('failed deletion keeps the dialog and notification available for retry', async () => {
    mocks.fetch.mockResolvedValueOnce(response(['one']));
    const panel = inbox();
    await panel.load();
    panel.deleting.value = panel.notices.value[0];
    mocks.fetch.mockResolvedValueOnce({ ok: false });
    await panel.deleteNotice();
    expect(panel.notices.value).toHaveLength(1);
    expect(panel.deleting.value?.id).toBe('one');
    expect(panel.error.value).toContain('Could not delete');
    expect(panel.busy.value).toBe(false);
});

test('a replayed notification does not show another toast after dismissal', async () => {
    mocks.fetch.mockResolvedValue(response(['one']));
    const panel = inbox();
    await panel.load(false, 'one');
    mocks.dismiss('one');
    await panel.load(false, 'one');
    expect(mocks.info).toHaveBeenCalledTimes(1);
    expect(panel.notices.value.map((notice) => notice.id)).toEqual(['one']);
});

test('notifications received during a pending request each show once', async () => {
    let finish!: (value: ReturnType<typeof response>) => void;
    mocks.fetch
        .mockReturnValueOnce(
            new Promise((resolve) => {
                finish = resolve;
            }),
        )
        .mockResolvedValue(response(['three', 'two', 'one']));
    const panel = inbox();
    const first = panel.load(false, 'one');
    await panel.load(false, 'two');
    await panel.load(false, 'three');
    await panel.load(false, 'two');
    finish(response(['three', 'two', 'one']));
    await first;
    await vi.waitFor(() => expect(mocks.fetch).toHaveBeenCalledTimes(2));
    expect(mocks.info.mock.calls.map((call) => call[1].id)).toEqual([
        'one',
        'two',
        'three',
    ]);
});

test('marking one notification read or unread updates the visible state', async () => {
    mocks.fetch.mockResolvedValueOnce(response(['one']));
    const panel = inbox();
    await panel.load();
    mocks.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
            read_at: '2026-11-15T01:00:00Z',
            unread_count: 0,
        }),
    });
    await panel.toggleRead(panel.notices.value[0]);
    expect(panel.notices.value[0].read_at).not.toBeNull();
    expect(panel.unread.value).toBe(0);
    mocks.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ read_at: null, unread_count: 1 }),
    });
    await panel.toggleRead(panel.notices.value[0]);
    expect(panel.notices.value[0].read_at).toBeNull();
    expect(panel.unread.value).toBe(1);
});

test('a failed read action preserves unread state and releases the processing lock', async () => {
    mocks.fetch.mockResolvedValueOnce(response(['one']));
    const panel = inbox();
    await panel.load();
    mocks.fetch.mockResolvedValueOnce({ ok: false });
    await panel.toggleRead(panel.notices.value[0]);
    expect(panel.notices.value[0].read_at).toBeNull();
    expect(panel.unread.value).toBe(1);
    expect(panel.error.value).toContain('Could not update');
    expect(panel.busy.value).toBe(false);
});
