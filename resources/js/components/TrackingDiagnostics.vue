<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import type { TrackingState } from '@/lib/tracking';

const page = usePage<{ tracking: TrackingState }>();
const visible = computed(
    () =>
        new URL(page.url, 'https://local.test').searchParams.get(
            'tracking_debug',
        ) === '1',
);
const resources = ref<string[]>([]);
const leadQueued = ref(false);
const onLeadQueued = () => {
    leadQueued.value = true;
};
const providers = [
    {
        key: 'clarity',
        name: 'Microsoft Clarity',
        category: 'analytics',
        match: 'clarity.ms/tag/',
    },
    {
        key: 'ga4',
        name: 'Google Analytics 4',
        category: 'analytics',
        match: 'googletagmanager.com/gtag/js',
    },
    {
        key: 'gtm',
        name: 'Google Tag Manager',
        category: 'all',
        match: 'googletagmanager.com/gtm.js',
    },
    {
        key: 'meta',
        name: 'Meta Pixel',
        category: 'marketing',
        match: 'connect.facebook.net/en_US/fbevents.js',
    },
    { key: 'custom', name: 'Custom scripts', category: 'all', match: '' },
] as const;
function refresh() {
    resources.value = performance
        .getEntriesByType('resource')
        .map((entry) => entry.name);
}
function status(provider: (typeof providers)[number]) {
    const state = page.props.tracking;
    if (!state.providers[provider.key]) return 'Not configured';
    if (!state.allowed) return 'Excluded administrator visit';
    const consent = state.consent;
    const permitted =
        provider.category === 'all'
            ? consent?.analytics && consent?.marketing
            : consent?.[provider.category];
    if (!permitted) return 'Blocked by cookie preferences';
    if (provider.key === 'custom')
        return 'Allowed; inspect custom code manually';
    return resources.value.some((url) => url.includes(provider.match))
        ? 'Resource request observed'
        : 'Not observed; check blockers or CSP';
}
onMounted(() => {
    refresh();
    window.addEventListener('tracking:lead-queued', onLeadQueued);
});
onUnmounted(() =>
    window.removeEventListener('tracking:lead-queued', onLeadQueued),
);
</script>

<template>
    <section
        v-if="visible"
        aria-labelledby="tracking-diagnostics-title"
        class="border-t bg-muted/30 px-4 py-6 text-foreground sm:px-6"
    >
        <div class="mx-auto max-w-3xl space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2
                    id="tracking-diagnostics-title"
                    class="text-lg font-semibold"
                >
                    Tracking diagnostics
                </h2>
                <Button variant="outline" size="sm" @click="refresh"
                    >Refresh checks</Button
                >
            </div>
            <p class="text-sm text-muted-foreground">
                Checks apply to this browser and its cookie preferences. A
                resource request does not prove event delivery. Confirm events
                in GA4 DebugView, GTM Preview, Clarity, or Meta Test Events.
            </p>
            <p
                v-if="!page.props.tracking.allowed"
                class="rounded-lg border p-3 text-sm"
            >
                Administrator visits are excluded. Copy this page's URL into a
                private browser window to test as a visitor.
            </p>
            <p
                v-if="leadQueued"
                role="status"
                class="rounded-lg border bg-background p-3 text-sm"
            >
                Contact lead event queued in this browser. Confirm receipt in
                your provider's event tools.
            </p>
            <dl class="divide-y rounded-lg border bg-background px-4">
                <div
                    v-for="provider in providers"
                    :key="provider.key"
                    class="flex flex-wrap justify-between gap-2 py-3 text-sm"
                >
                    <dt class="font-medium">{{ provider.name }}</dt>
                    <dd class="text-muted-foreground">
                        {{ status(provider) }}
                    </dd>
                </div>
            </dl>
            <a
                href="https://tagassistant.google.com/"
                target="_blank"
                rel="noopener noreferrer"
                class="text-sm underline underline-offset-4"
                >Open Google Tag Assistant</a
            >
        </div>
    </section>
</template>
