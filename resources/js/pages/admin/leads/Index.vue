<script setup lang="ts">
import { Head, Link, router, useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';
import { watchDebounced } from '@vueuse/core';
import { toast } from 'vue-sonner';
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
    query: string | null;
    details: {
        description?: string;
        hours?: string[];
        socials?: Record<string, string>;
    } | null;
};
const props = defineProps<{
    leads: {
        data: Lead[];
        meta: { current_page: number; last_page: number; total: number };
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
const deleting = ref<Lead | null>(null);
const revoking = ref<string | null>(null);
const token = ref('');
const processing = ref(false);
const tokenForm = useHttp({ name: 'Valence Scout' });
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
        <Input
            v-model="search"
            placeholder="Search businesses…"
            aria-label="Search businesses"
            class="max-w-sm"
        />
        <div class="overflow-hidden rounded-xl border">
            <Table
                ><TableHeader
                    ><TableRow
                        ><TableHead>Business</TableHead
                        ><TableHead>Contact</TableHead
                        ><TableHead>Address</TableHead
                        ><TableHead class="text-right"
                            >Actions</TableHead
                        ></TableRow
                    ></TableHeader
                ><TableBody>
                    <TableRow v-for="lead in leads.data" :key="lead.id"
                        ><TableCell
                            ><button
                                class="text-left font-medium hover:underline"
                                @click="selected = lead"
                            >
                                {{ lead.name }}
                            </button>
                            <p class="text-xs text-muted-foreground">
                                {{ lead.category || 'Business' }}
                            </p></TableCell
                        ><TableCell
                            ><p>{{ lead.phone || '—' }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ lead.email }}
                            </p></TableCell
                        ><TableCell class="max-w-xs whitespace-normal">{{
                            lead.address || '—'
                        }}</TableCell
                        ><TableCell class="text-right"
                            ><Button
                                variant="ghost"
                                size="sm"
                                @click="selected = lead"
                                >View</Button
                            ><Button
                                v-if="can('leads.delete')"
                                variant="ghost"
                                size="sm"
                                class="text-destructive"
                                @click="deleting = lead"
                                >Delete</Button
                            ></TableCell
                        ></TableRow
                    >
                    <TableRow v-if="!leads.data.length"
                        ><TableCell
                            :colspan="4"
                            class="h-32 text-center text-muted-foreground"
                            >{{
                                search
                                    ? 'No businesses match your search.'
                                    : 'No leads yet. Connect Valence Scout to import your first business.'
                            }}</TableCell
                        ></TableRow
                    >
                </TableBody></Table
            >
        </div>
        <div
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span
                >{{ leads.meta.total }} leads · Page
                {{ leads.meta.current_page }} of
                {{ leads.meta.last_page }}</span
            >
            <div class="flex gap-2">
                <Button
                    v-if="leads.links.prev"
                    variant="outline"
                    size="sm"
                    as-child
                    ><Link :href="leads.links.prev" preserve-state
                        >Previous</Link
                    ></Button
                ><Button
                    v-if="leads.links.next"
                    variant="outline"
                    size="sm"
                    as-child
                    ><Link :href="leads.links.next" preserve-state
                        >Next</Link
                    ></Button
                >
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
                class="inset-x-2 bottom-2 mx-auto max-h-[92vh] md:right-2 md:left-64"
                ><DrawerHeader
                    ><DrawerTitle>Connect Valence Scout</DrawerTitle
                    ><DrawerDescription
                        >Install the unpacked extension from
                        resources/extensions/valence-scout in Chrome →
                        Extensions → Developer mode → Load
                        unpacked.</DrawerDescription
                    ></DrawerHeader
                >
                <div class="space-y-5 overflow-y-auto px-6 pb-6">
                    <div class="space-y-2">
                        <Label>Webhook URL</Label>
                        <div class="flex gap-2">
                            <Input
                                :model-value="webhookUrl"
                                readonly
                                aria-label="Webhook URL"
                            /><Button
                                variant="outline"
                                @click="copy(webhookUrl)"
                                >Copy</Button
                            >
                        </div>
                    </div>
                    <form class="space-y-2" @submit.prevent="createToken">
                        <Label for="token-name">Connection name</Label
                        ><Input
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
                        <Button :disabled="tokenForm.processing"
                            >Generate token</Button
                        >
                    </form>
                    <div v-if="token" class="space-y-2 rounded-lg border p-4">
                        <Label for="new-token"
                            >Copy now — this token is shown only once</Label
                        ><Input
                            id="new-token"
                            :model-value="token"
                            readonly
                            type="password"
                            autocomplete="off"
                        /><Button variant="outline" @click="copy(token)"
                            >Copy token</Button
                        >
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
                            >Revoke</Button
                        >
                    </div>
                </div>
            </DrawerContent>
        </Drawer>
        <Drawer :open="!!selected" @update:open="if (!$event) selected = null;"
            ><DrawerContent
                class="inset-x-2 bottom-2 mx-auto max-h-[92vh] md:right-2 md:left-64"
                ><DrawerHeader
                    ><DrawerTitle>{{ selected?.name }}</DrawerTitle
                    ><DrawerDescription
                        >Collected business details. Verify contact information
                        before using it.</DrawerDescription
                    ></DrawerHeader
                >
                <div
                    v-if="selected"
                    class="space-y-4 overflow-y-auto px-6 pb-6"
                >
                    <Badge v-if="selected.category">{{
                        selected.category
                    }}</Badge>
                    <p>{{ selected.address }}</p>
                    <p>{{ selected.phone }} {{ selected.email }}</p>
                    <p v-if="selected.rating">
                        Google rating: {{ selected.rating }} / 5
                    </p>
                    <p>{{ selected.details?.description }}</p>
                    <p v-for="hour in selected.details?.hours" :key="hour">
                        {{ hour }}
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <a
                            v-if="selected.website"
                            :href="selected.website"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="underline"
                            >Website ↗</a
                        ><a
                            v-if="selected.maps_url"
                            :href="selected.maps_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="underline"
                            >Google Maps ↗</a
                        ><a
                            v-for="(url, network) in selected.details?.socials"
                            :key="network"
                            :href="url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="underline"
                            >{{ network }} ↗</a
                        >
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Search: {{ selected.query || '—' }}
                    </p>
                </div></DrawerContent
            ></Drawer
        >
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
