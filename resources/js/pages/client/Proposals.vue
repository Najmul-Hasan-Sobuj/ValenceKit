<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { watchDebounced } from '@vueuse/core';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { index } from '@/routes/client/proposals';
import { show } from '@/routes/proposals/public';
import { decodePaginationLabel } from '@/lib/decodePaginationLabel';

defineOptions({
    layout: { breadcrumbs: [{ title: 'My documents', href: index() }] },
});
const props = defineProps<{
    filters: { search: string; type: string; status: string };
    summary: { pending: number; expiring: number; completed: number };
    documents: {
        data: {
            public_id: string;
            number: string;
            title: string;
            type: string;
            status: string;
            total_minor: number;
            currency: string;
            valid_until: string;
        }[];
        total: number;
        from: number | null;
        to: number | null;
        last_page: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}>();
const search = ref(props.filters.search);
const type = ref(props.filters.type);
const status = ref(props.filters.status);
watchDebounced(
    [search, type, status],
    () =>
        router.get(
            index.url(),
            {
                search: search.value || undefined,
                type: type.value || undefined,
                status: status.value || undefined,
            },
            { preserveState: true, replace: true },
        ),
    { debounce: 300 },
);
const money = (amount: number, currency: string) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(
        amount / 100,
    );
</script>

<template>
    <div class="space-y-6 p-4 md:p-6">
        <Head title="My documents" />
        <header>
            <h1 class="text-2xl font-semibold">My documents</h1>
            <p class="text-sm text-muted-foreground">
                Review proposals, quotations and contracts addressed to your
                client account.
            </p>
        </header>
        <div class="grid grid-cols-3 gap-3">
            <button
                v-for="(count, key) in summary"
                :key="key"
                class="rounded-xl border bg-card p-4 text-left"
                @click="status = key"
            >
                <span class="block text-2xl font-semibold">{{ count }}</span
                ><span class="text-sm text-muted-foreground capitalize">{{
                    key
                }}</span>
            </button>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <select
                v-model="type"
                aria-label="Document type"
                class="h-9 rounded-md border bg-background px-3 text-sm"
            >
                <option value="">All types</option>
                <option value="proposal">Proposals</option>
                <option value="quotation">Quotations</option>
                <option value="contract">Contracts</option></select
            ><select
                v-model="status"
                aria-label="Document status"
                class="h-9 rounded-md border bg-background px-3 text-sm"
            >
                <option value="">All statuses</option>
                <option value="pending">Pending action</option>
                <option value="expiring">Expiring within 3 days</option>
                <option value="completed">Completed</option>
            </select>
        </div>
        <Input
            v-model="search"
            class="sm:max-w-sm"
            aria-label="Search my documents"
            placeholder="Search document title or number"
        />
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="document in documents.data"
                :key="document.public_id"
                class="flex min-w-0 flex-col gap-4 rounded-xl border bg-card p-5"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-xs break-all text-muted-foreground"
                        >{{ document.number }} · {{ document.type }}</span
                    ><Badge variant="outline" class="capitalize">{{
                        document.status
                    }}</Badge>
                </div>
                <h2 class="text-lg font-semibold break-words">
                    {{ document.title }}
                </h2>
                <p class="font-medium">
                    {{ money(document.total_minor, document.currency) }}
                </p>
                <p class="text-sm text-muted-foreground">
                    Valid until {{ document.valid_until }}
                </p>
                <Button as-child class="mt-auto"
                    ><Link :href="show(document.public_id)"
                        >View document</Link
                    ></Button
                >
            </article>
        </div>
        <p
            v-if="!documents.total"
            class="rounded-xl border p-8 text-center text-muted-foreground"
        >
            No documents found. Documents appear here when they are sent to your
            account email.
        </p>
        <div v-else class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-muted-foreground">
                Showing {{ documents.from }}–{{ documents.to }} of
                {{ documents.total }}
            </p>
            <nav
                v-if="documents.last_page > 1"
                class="flex flex-wrap gap-1"
                aria-label="Document pages"
            >
                <template v-for="link in documents.links" :key="link.label"
                    ><Button
                        v-if="link.url"
                        as-child
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                        ><Link :href="link.url" preserve-state>{{
                            decodePaginationLabel(link.label)
                        }}</Link></Button
                    ><Button v-else disabled size="sm" variant="outline">{{
                        decodePaginationLabel(link.label)
                    }}</Button></template
                >
            </nav>
        </div>
    </div>
</template>
