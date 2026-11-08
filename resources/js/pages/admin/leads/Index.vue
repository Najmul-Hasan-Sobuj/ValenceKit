<script setup lang="ts">
import { Head, router, useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { watchDebounced } from '@vueuse/core';
import { toast } from 'vue-sonner';
import {
    Building2,
    CalendarDays,
    ChevronDown,
    ChevronUp,
    Clock,
    ExternalLink,
    Eye,
    Globe,
    Mail,
    MapPin,
    MoreHorizontal,
    Phone,
    Radio,
    Search,
    Star,
    Trash2,
} from '@lucide/vue';
import { decodePaginationLabel } from '@/lib/decodePaginationLabel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { usePermissions } from '@/composables/usePermissions';
import {
    index,
    destroy,
    exportMethod,
} from '@/actions/App/Http/Controllers/Admin/LeadController';
import {
    store as storeToken,
    destroy as destroyToken,
} from '@/actions/App/Http/Controllers/Admin/LeadTokenController';

type Lead = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    website: string | null;
    maps_url: string | null;
    category: string | null;
    rating: number | null;
    review_count: number | null;
    query: string | null;
    created_at: string | null;
    details: {
        description?: string;
        hours?: string[];
        socials?: Record<string, string>;
    } | null;
};
const props = defineProps<{
    leads: {
        data: Lead[];
        meta: {
            current_page: number;
            last_page: number;
            from?: number;
            to?: number;
            total: number;
            links?: Array<{
                label: string;
                url: string | null;
                active: boolean;
            }>;
        };
        links: { prev: string | null; next: string | null };
    };
    filters: { search: string };
    webhookUrl: string;
    tokens: { id: string; name: string; expires_at: string }[];
}>();
defineOptions({ breadcrumbs: [{ title: 'Leads', href: index.url() }] });
const { can } = usePermissions();
const search = ref(props.filters.search);
const connectionOpen = ref(false);
const selected = ref<Lead | null>(null);
const expandedLeadId = ref<number | null>(null);
const deleting = ref<Lead | null>(null);
const revoking = ref<string | null>(null);
const token = ref('');
const processing = ref(false);
const tokenForm = useHttp({ name: 'Valence Scout' });
const paginationLinks = computed(() => {
    if (props.leads.meta.links?.length) {
        return props.leads.meta.links;
    }

    return [
        {
            label: '&laquo; Previous',
            url: props.leads.links.prev,
            active: false,
        },
        {
            label: 'Next &raquo;',
            url: props.leads.links.next,
            active: false,
        },
    ];
});

const firstLead = computed(
    () => props.leads.meta.from ?? (props.leads.data.length ? 1 : 0),
);
const lastLead = computed(
    () =>
        props.leads.meta.to ??
        (props.leads.data.length
            ? firstLead.value + props.leads.data.length - 1
            : 0),
);

function toggleExpanded(leadId: number): void {
    expandedLeadId.value = expandedLeadId.value === leadId ? null : leadId;
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
    }).format(new Date(value));
}

interface DaySchedule {
    day: string;
    hours: string;
    isToday: boolean;
    isOpen24Hours: boolean;
    isClosed: boolean;
}

const currentDayName = computed(() => {
    return new Intl.DateTimeFormat('en-US', { weekday: 'long' }).format(new Date());
});

const parsedSelectedHours = computed<DaySchedule[]>(() => {
    const raw = selected.value?.details?.hours;
    if (!raw || !Array.isArray(raw)) return [];

    const dayOrder = [
        'Sunday',
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
    ];
    const todayName = currentDayName.value.toLowerCase();

    return raw
        .map((entry) => {
            const trimmed = String(entry).trim();
            if (!trimmed || /^\d+$/.test(trimmed)) return null;

            const parts = trimmed.split('·').map((p) => p.trim());
            const day = parts[0] || '';
            let hours = parts.slice(1).join(' · ').trim() || 'Not specified';
            hours = hours
                .replace(/[\u202F\u00A0]/g, ' ')
                .replace(/([AP]M)(?=\d)/gi, '$1, ')
                .replace(/\s+/g, ' ');

            const matchesDay = dayOrder.some((d) =>
                day.toLowerCase().startsWith(d.toLowerCase().slice(0, 3)),
            );
            if (!matchesDay) return null;

            const isToday = day.toLowerCase().startsWith(todayName.slice(0, 3));
            const isClosed = /closed/i.test(hours);
            const isOpen24Hours =
                /open 24/i.test(hours) || /24 hours/i.test(hours);

            return {
                day,
                hours,
                isToday,
                isOpen24Hours,
                isClosed,
            };
        })
        .filter((item): item is DaySchedule => item !== null);
});

const todaySelectedSchedule = computed<DaySchedule | null>(() => {
    return parsedSelectedHours.value.find((item) => item.isToday) ?? null;
});

watchDebounced(
    search,
    (value) =>
        router.get(
            index.url(),
            { search: value || undefined },
            { preserveState: true, replace: true },
        ),
    { debounce: 350 },
);
async function createToken() {
    token.value = '';
    try {
        const data = (await tokenForm.submit(storeToken())) as {
            token: string;
        };
        token.value = data.token;
        router.reload({ only: ['tokens'] });
    } catch {
        toast.error(
            'Could not generate a token. Check the name and try again.',
        );
    }
}
async function copy(value: string) {
    try {
        await navigator.clipboard.writeText(value);
        toast.success('Copied.');
    } catch {
        toast.error(
            'Clipboard unavailable. Select and copy the value manually.',
        );
    }
}
function deleteLead() {
    if (!deleting.value || processing.value) return;
    processing.value = true;
    router.delete(destroy.url(deleting.value.id), {
        onSuccess: () => {
            deleting.value = null;
            selected.value = null;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
function revokeToken() {
    if (!revoking.value || processing.value) return;
    processing.value = true;
    router.delete(destroyToken.url(revoking.value), {
        onSuccess: () => {
            revoking.value = null;
            token.value = '';
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <Head title="Leads" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Leads</h1>
                <p class="text-sm text-muted-foreground">
                    Business contacts collected with Valence Scout. Shared with
                    authorized teammates.
                </p>
            </div>
            <div class="flex gap-2">
                <Button v-if="can('leads.export')" variant="outline" as-child
                    ><a :href="exportMethod.url()">Export CSV</a></Button
                ><Button
                    v-if="can('leads.import')"
                    @click="connectionOpen = true"
                    >Connect Scout</Button
                >
            </div>
        </div>
        <div
            class="flex flex-col gap-3 rounded-xl border bg-card p-3 shadow-xs sm:flex-row sm:items-center"
        >
            <div class="relative min-w-0 flex-1 sm:max-w-md">
                <Search
                    class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Search businesses..."
                    aria-label="Search businesses"
                    class="pl-9"
                />
            </div>
            <p class="text-sm text-muted-foreground">
                {{ leads.meta.total }} total leads
            </p>
        </div>
        <div class="overflow-x-auto rounded-xl border bg-card">
            <Table class="min-w-[760px]">
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-12" />
                        <TableHead>Business</TableHead>
                        <TableHead>Contact</TableHead>
                        <TableHead>Location</TableHead>
                        <TableHead>Rating</TableHead>
                        <TableHead class="w-12 pr-5 text-right">
                            <span class="sr-only">Actions</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-for="lead in leads.data" :key="lead.id">
                        <TableRow
                            :class="
                                expandedLeadId === lead.id
                                    ? 'bg-muted/30'
                                    : undefined
                            "
                        >
                            <TableCell class="align-top">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    :aria-label="`${
                                        expandedLeadId === lead.id
                                            ? 'Collapse'
                                            : 'Expand'
                                    } ${lead.name}`"
                                    :aria-expanded="expandedLeadId === lead.id"
                                    @click="toggleExpanded(lead.id)"
                                >
                                    <ChevronUp
                                        v-if="expandedLeadId === lead.id"
                                        class="size-4"
                                    />
                                    <ChevronDown v-else class="size-4" />
                                </Button>
                            </TableCell>
                            <TableCell
                                class="max-w-[260px] align-top whitespace-normal"
                            >
                                <button
                                    class="line-clamp-2 block w-full text-left font-medium break-words hover:underline"
                                    @click="selected = lead"
                                >
                                    {{ lead.name }}
                                </button>
                                <Badge
                                    v-if="lead.category"
                                    variant="secondary"
                                    class="mt-1"
                                >
                                    {{ lead.category }}
                                </Badge>
                            </TableCell>
                            <TableCell class="align-top">
                                <a
                                    v-if="lead.phone"
                                    :href="`tel:${lead.phone}`"
                                    class="flex items-center gap-1.5 whitespace-nowrap hover:underline"
                                >
                                    <Phone
                                        class="size-3.5 text-muted-foreground"
                                    />
                                    {{ lead.phone }}
                                </a>
                                <a
                                    v-if="lead.email"
                                    :href="`mailto:${lead.email}`"
                                    class="mt-1 flex max-w-[190px] items-center gap-1.5 truncate text-sm text-muted-foreground hover:underline"
                                >
                                    <Mail class="size-3.5 shrink-0" />
                                    <span class="truncate">{{
                                        lead.email
                                    }}</span>
                                </a>
                                <span v-if="!lead.phone && !lead.email">—</span>
                            </TableCell>
                            <TableCell
                                class="max-w-[260px] align-top whitespace-normal"
                            >
                                <span
                                    class="line-clamp-2 block w-full text-sm break-words text-muted-foreground"
                                >
                                    {{ lead.address || '—' }}
                                </span>
                            </TableCell>
                            <TableCell class="align-top">
                                <span
                                    v-if="lead.rating"
                                    class="flex items-center gap-1 whitespace-nowrap"
                                >
                                    <Star
                                        class="size-3.5 fill-amber-400 text-amber-400"
                                    />
                                    {{ lead.rating.toFixed(1) }}
                                    <span
                                        v-if="lead.review_count"
                                        class="text-xs text-muted-foreground"
                                    >
                                        ({{ lead.review_count }})
                                    </span>
                                </span>
                                <span v-else>—</span>
                            </TableCell>
                            <TableCell class="pr-5 text-right align-top" @click.stop>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label="Open actions"
                                        >
                                            <MoreHorizontal class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-36">
                                        <DropdownMenuItem
                                            class="cursor-pointer gap-2"
                                            @click="selected = lead"
                                        >
                                            <Eye
                                                class="size-4 text-muted-foreground"
                                            />
                                            <span>View</span>
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator
                                            v-if="can('leads.delete')"
                                        />
                                        <DropdownMenuItem
                                            v-if="can('leads.delete')"
                                            variant="destructive"
                                            class="cursor-pointer gap-2"
                                            @click="deleting = lead"
                                        >
                                            <Trash2 class="size-4" />
                                            <span>Delete</span>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                        <TableRow
                            v-if="expandedLeadId === lead.id"
                            class="bg-muted/20 hover:bg-muted/20"
                        >
                            <TableCell :colspan="6" class="p-0 whitespace-normal">
                                <div
                                    class="grid gap-4 border-t px-5 py-4 md:grid-cols-2 xl:grid-cols-4"
                                >
                                    <div class="min-w-0 space-y-1">
                                        <p
                                            class="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            <MapPin class="size-3.5" />
                                            Address
                                        </p>
                                        <p class="text-sm break-words">
                                            {{ lead.address || 'Not provided' }}
                                        </p>
                                    </div>
                                    <div class="min-w-0 space-y-1">
                                        <p
                                            class="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            <Globe class="size-3.5" />
                                            Online
                                        </p>
                                        <div
                                            class="flex flex-wrap gap-x-3 gap-y-1 text-sm"
                                        >
                                            <a
                                                v-if="lead.website"
                                                :href="lead.website"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex items-center gap-1 hover:underline"
                                            >
                                                Website
                                                <ExternalLink class="size-3" />
                                            </a>
                                            <a
                                                v-if="lead.maps_url"
                                                :href="lead.maps_url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex items-center gap-1 hover:underline"
                                            >
                                                Maps
                                                <ExternalLink class="size-3" />
                                            </a>
                                            <span
                                                v-if="
                                                    !lead.website &&
                                                    !lead.maps_url
                                                "
                                            >
                                                Not provided
                                            </span>
                                        </div>
                                    </div>
                                    <div class="min-w-0 space-y-1">
                                        <p
                                            class="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            <CalendarDays class="size-3.5" />
                                            Collected
                                        </p>
                                        <p class="text-sm">
                                            {{ formatDate(lead.created_at) }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="
                                            lead.details?.description ||
                                            lead.query
                                        "
                                        class="min-w-0 space-y-1"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Notes
                                        </p>
                                        <p
                                            v-if="lead.details?.description"
                                            class="line-clamp-3 text-sm break-words"
                                        >
                                            {{ lead.details.description }}
                                        </p>
                                        <p
                                            v-if="lead.query"
                                            class="text-xs text-muted-foreground break-words"
                                        >
                                            Search: {{ lead.query }}
                                        </p>
                                    </div>
                                </div>
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-if="!leads.data.length">
                        <TableCell
                            :colspan="6"
                            class="h-32 text-center text-muted-foreground"
                        >
                            {{
                                search
                                    ? 'No businesses match your search.'
                                    : 'No leads yet. Connect Valence Scout to import your first business.'
                            }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <div
            class="flex flex-col justify-between gap-3 text-sm sm:flex-row sm:items-center"
        >
            <p class="text-muted-foreground">
                Showing {{ firstLead }} to {{ lastLead }} of
                {{ leads.meta.total }} leads
            </p>
            <div class="flex flex-wrap items-center gap-1">
                <Button
                    v-for="link in paginationLinks"
                    :key="link.label"
                    variant="outline"
                    size="sm"
                    :disabled="!link.url"
                    :class="{
                        'border-primary/20 bg-primary/10 text-primary':
                            link.active,
                    }"
                    @click="
                        link.url
                            ? router.get(
                                  link.url,
                                  {},
                                  {
                                      preserveState: true,
                                      preserveScroll: true,
                                  },
                              )
                            : null
                    "
                >
                    <span>{{ decodePaginationLabel(link.label) }}</span>
                </Button>
            </div>
        </div>
        <Drawer
            :open="connectionOpen"
            @update:open="
                connectionOpen = $event;
                if (!$event) token = '';
            "
        >
            <DrawerContent
                class="max-h-[92vh] overflow-y-auto md:inset-x-auto md:right-2 md:left-64"
            >
                <DrawerHeader class="border-b border-border/40 pb-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/20"
                        >
                            <Radio class="size-5" />
                        </div>
                        <div>
                            <DrawerTitle>Connect Valence Scout</DrawerTitle>
                            <DrawerDescription>
                                Install the unpacked extension from
                                resources/extensions/valence-scout in Chrome →
                                Extensions → Developer mode → Load unpacked.
                            </DrawerDescription>
                        </div>
                    </div>
                </DrawerHeader>
                <div class="space-y-5 px-6 py-5">
                    <div class="space-y-2">
                        <Label>Webhook URL</Label>
                        <div class="flex gap-2">
                            <Input
                                :model-value="webhookUrl"
                                readonly
                                aria-label="Webhook URL"
                            />
                            <Button
                                variant="outline"
                                @click="copy(webhookUrl)"
                            >
                                Copy
                            </Button>
                        </div>
                    </div>
                    <form class="space-y-2" @submit.prevent="createToken">
                        <Label for="token-name">Connection name</Label>
                        <Input
                            id="token-name"
                            v-model="tokenForm.name"
                            maxlength="80"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            Tokens can import leads for 30 days and require your
                            current import permission. Copy the token into the
                            extension's Connect form.
                        </p>
                        <Button :disabled="tokenForm.processing">
                            Generate token
                        </Button>
                    </form>
                    <div v-if="token" class="space-y-2 rounded-lg border p-4">
                        <Label for="new-token">
                            Copy now — this token is shown only once
                        </Label>
                        <Input
                            id="new-token"
                            :model-value="token"
                            readonly
                            type="password"
                            autocomplete="off"
                        />
                        <Button variant="outline" @click="copy(token)">
                            Copy token
                        </Button>
                    </div>
                    <div
                        v-for="item in tokens"
                        :key="item.id"
                        class="flex items-center justify-between gap-4 rounded-lg border p-3"
                    >
                        <div>
                            <p class="font-medium">{{ item.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                Expires
                                {{
                                    new Date(
                                        item.expires_at,
                                    ).toLocaleDateString()
                                }}
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="revoking = item.id"
                        >
                            Revoke
                        </Button>
                    </div>
                </div>
            </DrawerContent>
        </Drawer>
        <Drawer :open="!!selected" @update:open="if (!$event) selected = null;">
            <DrawerContent
                class="max-h-[92vh] overflow-y-auto md:inset-x-auto md:right-2 md:left-64"
            >
                <DrawerHeader class="border-b border-border/40 pb-4">
                    <div class="flex items-start gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/20"
                        >
                            <Building2 class="size-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <DrawerTitle class="text-base font-semibold">
                                    {{ selected?.name }}
                                </DrawerTitle>
                                <Badge v-if="selected?.category" variant="secondary">
                                    {{ selected.category }}
                                </Badge>
                            </div>
                            <DrawerDescription class="text-xs text-muted-foreground">
                                Collected business contact details. Verify contact information before using it.
                            </DrawerDescription>
                        </div>
                    </div>
                </DrawerHeader>
                <div v-if="selected" class="space-y-5 px-6 py-5">
                    <!-- Quick Highlights Grid -->
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-lg border bg-muted/20 p-3">
                            <span class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground uppercase">
                                <Phone class="size-3.5" /> Phone
                            </span>
                            <p class="mt-1 text-sm font-medium">
                                <a
                                    v-if="selected.phone"
                                    :href="`tel:${selected.phone}`"
                                    class="hover:underline"
                                >
                                    {{ selected.phone }}
                                </a>
                                <span v-else class="text-muted-foreground">Not provided</span>
                            </p>
                        </div>

                        <div class="rounded-lg border bg-muted/20 p-3">
                            <span class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground uppercase">
                                <Mail class="size-3.5" /> Email
                            </span>
                            <p class="mt-1 text-sm font-medium truncate">
                                <a
                                    v-if="selected.email"
                                    :href="`mailto:${selected.email}`"
                                    class="hover:underline"
                                >
                                    {{ selected.email }}
                                </a>
                                <span v-else class="text-muted-foreground">Not provided</span>
                            </p>
                        </div>

                        <div class="rounded-lg border bg-muted/20 p-3">
                            <span class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground uppercase">
                                <Star class="size-3.5 fill-amber-400 text-amber-400" /> Rating
                            </span>
                            <p class="mt-1 text-sm font-medium">
                                <span v-if="selected.rating">{{ selected.rating.toFixed(1) }} / 5.0</span>
                                <span v-else class="text-muted-foreground">Not rated</span>
                            </p>
                        </div>

                        <div class="rounded-lg border bg-muted/20 p-3">
                            <span class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground uppercase">
                                <Search class="size-3.5" /> Search Query
                            </span>
                            <p class="mt-1 text-sm font-medium truncate text-muted-foreground">
                                {{ selected.query || '—' }}
                            </p>
                        </div>
                    </div>

                    <!-- Location & Links Section -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border bg-card p-4 space-y-2">
                            <h4 class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                <MapPin class="size-3.5" /> Address
                            </h4>
                            <p class="text-sm leading-relaxed">{{ selected.address || 'Address not provided' }}</p>
                        </div>

                        <div class="rounded-lg border bg-card p-4 space-y-2">
                            <h4 class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                <Globe class="size-3.5" /> Links & Socials
                            </h4>
                            <div class="flex flex-wrap gap-2 pt-1">
                                <Button v-if="selected.website" variant="outline" size="sm" as-child>
                                    <a :href="selected.website" target="_blank" rel="noopener noreferrer">
                                        Website <ExternalLink class="ml-1.5 size-3" />
                                    </a>
                                </Button>
                                <Button v-if="selected.maps_url" variant="outline" size="sm" as-child>
                                    <a :href="selected.maps_url" target="_blank" rel="noopener noreferrer">
                                        Google Maps <ExternalLink class="ml-1.5 size-3" />
                                    </a>
                                </Button>
                                <Button
                                    v-for="(url, network) in selected.details?.socials"
                                    :key="network"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <a :href="url" target="_blank" rel="noopener noreferrer" class="capitalize">
                                        {{ network }} <ExternalLink class="ml-1.5 size-3" />
                                    </a>
                                </Button>
                                <span
                                    v-if="!selected.website && !selected.maps_url && !selected.details?.socials"
                                    class="text-sm text-muted-foreground"
                                >
                                    No online links available.
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div v-if="selected.details?.description" class="rounded-lg border bg-card p-4 space-y-1.5">
                        <h4 class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            About / Description
                        </h4>
                        <p class="text-sm text-muted-foreground leading-relaxed">
                            {{ selected.details.description }}
                        </p>
                    </div>

                    <!-- Opening Hours -->
                    <div
                        v-if="parsedSelectedHours.length"
                        class="space-y-3 rounded-lg border bg-card p-4"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 border-b border-border/40 pb-3"
                        >
                            <h4
                                class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-foreground uppercase"
                            >
                                <Clock class="size-3.5 text-primary" /> Opening
                                Hours
                            </h4>
                            <div v-if="todaySelectedSchedule">
                                <Badge
                                    v-if="todaySelectedSchedule.isOpen24Hours"
                                    variant="outline"
                                    class="gap-1.5 border-emerald-500/30 bg-emerald-500/10 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                                >
                                    <span
                                        class="size-1.5 animate-pulse rounded-full bg-emerald-500"
                                    />
                                    Open 24 Hours Today
                                </Badge>
                                <Badge
                                    v-else-if="todaySelectedSchedule.isClosed"
                                    variant="outline"
                                    class="gap-1.5 border-destructive/30 bg-destructive/10 text-xs font-medium text-destructive"
                                >
                                    <span
                                        class="size-1.5 rounded-full bg-destructive"
                                    />
                                    Closed Today
                                </Badge>
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="gap-1.5 border-primary/30 bg-primary/10 text-xs font-medium text-primary"
                                >
                                    <span
                                        class="size-1.5 rounded-full bg-primary"
                                    />
                                    Today: {{ todaySelectedSchedule.hours }}
                                </Badge>
                            </div>
                        </div>

                        <!-- 7-Day Timetable Grid -->
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-7">
                            <div
                                v-for="item in parsedSelectedHours"
                                :key="item.day"
                                class="flex flex-col justify-between rounded-lg border p-2.5 transition-colors"
                                :class="[
                                    item.isToday
                                        ? 'border-primary/50 bg-primary/10 ring-1 ring-primary/30'
                                        : 'border-border/60 bg-muted/20 hover:bg-muted/30',
                                ]"
                            >
                                <div
                                    class="flex items-center justify-between gap-1"
                                >
                                    <span
                                        class="text-xs font-semibold"
                                        :class="
                                            item.isToday
                                                ? 'text-primary'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{ item.day }}
                                    </span>
                                    <span
                                        v-if="item.isToday"
                                        class="rounded-full bg-primary/20 px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-primary uppercase"
                                    >
                                        Today
                                    </span>
                                </div>
                                <div class="mt-2 text-xs font-medium">
                                    <span
                                        v-if="item.isClosed"
                                        class="inline-flex items-center rounded bg-muted/50 px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground line-through"
                                    >
                                        Closed
                                    </span>
                                    <span
                                        v-else-if="item.isOpen24Hours"
                                        class="inline-flex items-center rounded bg-emerald-500/10 px-1.5 py-0.5 text-[11px] font-medium text-emerald-600 dark:text-emerald-400"
                                    >
                                        Open 24h
                                    </span>
                                    <span
                                        v-else
                                        class="font-medium text-foreground/90 leading-tight"
                                    >
                                        {{ item.hours }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Action Bar -->
                    <div class="flex items-center justify-between border-t border-border/40 pt-4">
                        <Button variant="outline" @click="selected = null">Close</Button>
                        <Button
                            v-if="can('leads.delete')"
                            variant="destructive"
                            @click="deleting = selected; selected = null;"
                        >
                            <Trash2 class="mr-1.5 size-4" /> Delete lead
                        </Button>
                    </div>
                </div>
            </DrawerContent>
        </Drawer>
        <AlertDialog
            :open="!!deleting"
            @update:open="if (!$event) deleting = null;"
            ><AlertDialogContent
                ><AlertDialogHeader
                    ><AlertDialogTitle>Delete this lead?</AlertDialogTitle
                    ><AlertDialogDescription
                        >{{ deleting?.name }} will be removed from this
                        workspace. This cannot be
                        undone.</AlertDialogDescription
                    ></AlertDialogHeader
                ><AlertDialogFooter
                    ><AlertDialogCancel>Cancel</AlertDialogCancel
                    ><Button
                        variant="destructive"
                        :disabled="processing"
                        @click="deleteLead"
                        >Delete lead</Button
                    ></AlertDialogFooter
                ></AlertDialogContent
            ></AlertDialog
        >
        <AlertDialog
            :open="!!revoking"
            @update:open="if (!$event) revoking = null;"
            ><AlertDialogContent
                ><AlertDialogHeader
                    ><AlertDialogTitle>Revoke this token?</AlertDialogTitle
                    ><AlertDialogDescription
                        >Extensions using this token will immediately lose
                        import access.</AlertDialogDescription
                    ></AlertDialogHeader
                ><AlertDialogFooter
                    ><AlertDialogCancel>Cancel</AlertDialogCancel
                    ><Button
                        variant="destructive"
                        :disabled="processing"
                        @click="revokeToken"
                        >Revoke token</Button
                    ></AlertDialogFooter
                ></AlertDialogContent
            ></AlertDialog
        >
    </div>
</template>
