<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Activity,
    BarChart3,
    ChevronRight,
    Code,
    ExternalLink,
    Info,
    ShieldCheck,
} from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { usePermissions } from '@/composables/usePermissions';
import { edit, update } from '@/routes/admin/tracking/settings';
import { home } from '@/routes';

interface TrackingSettings {
    clarity_enabled: boolean;
    clarity_project_id: string | null;
    google_analytics_enabled: boolean;
    google_analytics_id: string | null;
    google_tag_manager_enabled: boolean;
    google_tag_manager_id: string | null;
    facebook_pixel_enabled: boolean;
    facebook_pixel_id: string | null;
    custom_scripts_enabled: boolean;
    custom_head_scripts: string | null;
    custom_body_scripts: string | null;
    exclude_admins: boolean;
}
const props = defineProps<{ settings: TrackingSettings }>();
const { isSuperAdmin } = usePermissions();
function values(settings: TrackingSettings) {
    return {
        clarity_enabled: Boolean(settings.clarity_enabled),
        clarity_project_id: settings.clarity_project_id ?? '',
        google_analytics_enabled: Boolean(settings.google_analytics_enabled),
        google_analytics_id: settings.google_analytics_id ?? '',
        google_tag_manager_enabled: Boolean(
            settings.google_tag_manager_enabled,
        ),
        google_tag_manager_id: settings.google_tag_manager_id ?? '',
        facebook_pixel_enabled: Boolean(settings.facebook_pixel_enabled),
        facebook_pixel_id: settings.facebook_pixel_id ?? '',
        custom_scripts_enabled: Boolean(settings.custom_scripts_enabled),
        custom_head_scripts: settings.custom_head_scripts ?? '',
        custom_body_scripts: settings.custom_body_scripts ?? '',
        exclude_admins: Boolean(settings.exclude_admins),
    };
}
const form = useForm(values(props.settings));
const providers = [
    {
        name: 'Microsoft Clarity',
        enabled: 'clarity_enabled',
        id: 'clarity_project_id',
        label: 'Project ID',
        placeholder: 'e.g. yyvng93thl',
        description: 'Understand clicks, scrolling and session replays.',
        help: 'Find your project ID in Clarity → Settings → Setup.',
        url: 'https://clarity.microsoft.com/',
        icon: Activity,
    },
    {
        name: 'Google Analytics 4',
        enabled: 'google_analytics_enabled',
        id: 'google_analytics_id',
        label: 'Measurement ID',
        placeholder: 'G-XXXXXXXXXX',
        description: 'Measure visitors, pageviews and conversions.',
        help: 'Find your measurement ID in Admin → Data streams → Web stream.',
        url: 'https://analytics.google.com/',
        icon: BarChart3,
    },
    {
        name: 'Google Tag Manager',
        enabled: 'google_tag_manager_enabled',
        id: 'google_tag_manager_id',
        label: 'Container ID',
        placeholder: 'GTM-XXXXXXX',
        description: 'Manage and publish marketing tags in one place.',
        help: 'Use your web container ID, starting with GTM-.',
        url: 'https://tagmanager.google.com/',
        icon: Code,
    },
    {
        name: 'Meta Pixel',
        enabled: 'facebook_pixel_enabled',
        id: 'facebook_pixel_id',
        label: 'Pixel ID',
        placeholder: 'e.g. 123456789012345',
        description: 'Measure campaign traffic and PageView events.',
        help: 'Find your numeric pixel ID in Meta Events Manager → Data sources.',
        url: 'https://business.facebook.com/events_manager/',
        icon: Activity,
    },
] as const;
const savedCount = computed(
    () =>
        providers.filter(
            (p) => props.settings[p.enabled] && props.settings[p.id],
        ).length,
);
function status(provider: (typeof providers)[number]) {
    if (
        form[provider.enabled] !== props.settings[provider.enabled] ||
        form[provider.id] !== (props.settings[provider.id] ?? '')
    )
        return 'Unsaved';
    if (!form[provider.enabled]) return 'Off';
    return form[provider.id].trim() ? 'Enabled' : 'Needs ID';
}
function revealField(field: string) {
    document
        .getElementById(field)
        ?.closest('details')
        ?.setAttribute('open', '');
}
function submit() {
    form.patch(update.url(), {
        preserveScroll: true,
        onError: () => {
            Object.keys(form.errors).forEach(revealField);
        },
        onSuccess: () => {
            form.defaults(values(props.settings));
            form.reset();
        },
    });
}
defineOptions({
    layout: { breadcrumbs: [{ title: 'Tracking settings', href: edit() }] },
});
</script>

<template>
    <Head title="Tracking & Analytics" />
    <div class="min-w-0 space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                variant="small"
                title="Tracking & Analytics"
                description="Connect analytics tools and control where tracking runs."
            />
            <Badge variant="secondary"
                >{{ savedCount }} / 4 providers enabled</Badge
            >
        </div>
        <p
            v-if="!isSuperAdmin"
            role="status"
            class="rounded-lg border bg-muted/40 p-3 text-sm text-muted-foreground"
        >
            Read-only access. Only a Super Admin can change tracking and custom
            scripts.
        </p>
        <form class="space-y-4" @submit.prevent="submit">
            <div class="flex items-start gap-3 rounded-xl border bg-card p-4">
                <ShieldCheck class="mt-0.5 size-5 shrink-0 text-primary" />
                <div class="min-w-0 flex-1">
                    <Label for="exclude_admins" class="leading-5"
                        >Exclude administrators from tracking</Label
                    >
                    <p
                        id="exclude-help"
                        class="mt-1 text-xs leading-relaxed text-muted-foreground"
                    >
                        Skip admin pages and visits from signed-in
                        administrators to keep your analytics accurate.
                    </p>
                </div>
                <Switch
                    id="exclude_admins"
                    v-model="form.exclude_admins"
                    aria-label="Exclude administrators from tracking"
                    aria-describedby="exclude-help"
                    :disabled="!isSuperAdmin || form.processing"
                />
            </div>
            <p
                v-if="!form.exclude_admins"
                role="status"
                class="flex items-start gap-2 rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-xs leading-relaxed"
            >
                <Info class="mt-0.5 size-4 shrink-0" /> Administrator activity
                will be included in analytics. Session replay tools may record
                sensitive admin pages.
            </p>
            <div
                v-if="
                    form.google_analytics_enabled &&
                    form.google_tag_manager_enabled
                "
                role="status"
                class="flex items-start gap-2 rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-xs leading-relaxed"
            >
                <Info class="mt-0.5 size-4 shrink-0" /> Using GA4 and GTM
                together? If this same GA4 property is already configured inside
                GTM, turn off the direct GA4 integration here to avoid duplicate
                pageviews.
            </div>
            <div class="grid items-start gap-4 xl:grid-cols-2">
                <div
                    v-for="provider in providers"
                    :key="provider.id"
                    class="relative min-w-0 rounded-xl border bg-card"
                >
                    <details class="group" :open="form[provider.enabled]">
                        <summary
                            class="cursor-pointer list-none rounded-xl p-4 pr-20 focus-visible:outline-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden"
                        >
                            <span class="flex items-start gap-2">
                                <ChevronRight
                                    class="mt-1 size-3 shrink-0 transition-transform group-open:rotate-90"
                                />
                                <component
                                    :is="provider.icon"
                                    class="mt-0.5 size-5 shrink-0 text-primary"
                                />
                                <span class="min-w-0">
                                    <span
                                        class="flex flex-wrap items-center gap-2 text-sm font-semibold"
                                    >
                                        {{ provider.name }}
                                        <Badge
                                            variant="outline"
                                            class="text-[10px]"
                                            >{{ status(provider) }}</Badge
                                        >
                                    </span>
                                    <span
                                        class="mt-1 block text-xs leading-relaxed text-muted-foreground"
                                        >{{ provider.description }}</span
                                    >
                                </span>
                            </span>
                        </summary>
                        <div class="space-y-2 border-t p-4">
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <Label :for="provider.id">{{
                                    provider.label
                                }}</Label>
                                <a
                                    :href="provider.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 text-xs text-primary underline-offset-4 hover:underline"
                                >
                                    Open {{ provider.name }}
                                    <ExternalLink class="size-3" />
                                </a>
                            </div>
                            <Input
                                :id="provider.id"
                                v-model="form[provider.id]"
                                :placeholder="provider.placeholder"
                                :required="form[provider.enabled]"
                                :disabled="!isSuperAdmin || form.processing"
                                :aria-invalid="
                                    Boolean(form.errors[provider.id])
                                "
                                :aria-describedby="provider.id + '-help'"
                                autocomplete="off"
                                :spellcheck="false"
                                class="font-mono text-sm"
                            />
                            <p
                                :id="provider.id + '-help'"
                                class="text-xs leading-relaxed text-muted-foreground"
                            >
                                {{ provider.help }} Paste the ID or full
                                installation snippet; supported snippets are
                                converted to IDs when saved.
                            </p>
                            <InputError :message="form.errors[provider.id]" />
                        </div>
                    </details>
                    <Switch
                        :id="provider.enabled"
                        v-model="form[provider.enabled]"
                        :aria-label="'Enable ' + provider.name"
                        :disabled="!isSuperAdmin || form.processing"
                        class="absolute top-4 right-4"
                    />
                </div>
            </div>
            <div class="relative rounded-xl border bg-card">
                <details :open="form.custom_scripts_enabled">
                    <summary
                        class="cursor-pointer rounded-xl p-4 pr-20 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        Custom scripts
                        <Badge variant="outline" class="ml-2 text-[10px]"
                            >Advanced</Badge
                        >
                        <span
                            class="mt-1 block text-xs leading-relaxed font-normal text-muted-foreground"
                            >Optional HTML and scripts for your site's head and
                            body.</span
                        >
                    </summary>
                    <div class="space-y-4 border-t p-4">
                        <p
                            class="text-xs leading-relaxed text-muted-foreground"
                        >
                            Only add code from trusted sources. External scripts
                            must also be allowed by your site's Content Security
                            Policy; adding a snippet alone does not authorize a
                            new domain.
                        </p>
                        <div class="space-y-2">
                            <Label for="custom_head_scripts"
                                >Head HTML & scripts</Label
                            >
                            <textarea
                                id="custom_head_scripts"
                                v-model="form.custom_head_scripts"
                                rows="5"
                                maxlength="65535"
                                :spellcheck="false"
                                :disabled="!isSuperAdmin || form.processing"
                                class="w-full resize-y rounded-md border bg-background p-3 font-mono text-xs focus-visible:outline-2 focus-visible:outline-ring"
                                placeholder="Paste trusted script or meta tags here"
                            ></textarea>
                            <p class="text-xs text-muted-foreground">
                                Inserted before the closing head tag. Script
                                nonces are added automatically.
                            </p>
                            <InputError
                                :message="form.errors.custom_head_scripts"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="custom_body_scripts"
                                >Body HTML & fallback pixels</Label
                            >
                            <textarea
                                id="custom_body_scripts"
                                v-model="form.custom_body_scripts"
                                rows="4"
                                maxlength="65535"
                                :spellcheck="false"
                                :disabled="!isSuperAdmin || form.processing"
                                class="w-full resize-y rounded-md border bg-background p-3 font-mono text-xs focus-visible:outline-2 focus-visible:outline-ring"
                                placeholder="Paste body or noscript tags here"
                            ></textarea>
                            <p class="text-xs text-muted-foreground">
                                Inserted immediately after the opening body tag.
                            </p>
                            <InputError
                                :message="form.errors.custom_body_scripts"
                            />
                        </div>
                    </div>
                </details>
                <Switch
                    id="custom_scripts_enabled"
                    v-model="form.custom_scripts_enabled"
                    aria-label="Enable custom scripts"
                    :disabled="!isSuperAdmin || form.processing"
                    class="absolute top-4 right-4"
                />
            </div>
            <div
                class="rounded-xl border bg-muted/30 p-4 text-xs leading-relaxed text-muted-foreground"
            >
                <h3 class="mb-1 text-sm font-medium text-foreground">
                    Check your setup
                </h3>
                Save your settings, then open the public site in a private
                browser window. Use GA4 Realtime / DebugView, GTM Preview,
                Clarity, or Meta Test Events to confirm delivery. “Enabled”
                means the integration is configured here; it does not verify the
                provider connection. For GA4, enable history-based pageviews for
                this site's page navigation.
                <p class="mt-3">
                    Visitor consent is required. GA4 and Clarity use analytics
                    consent; Meta uses marketing consent. GTM and custom scripts
                    require both. Successful contact submissions send
                    generate_lead (GA4/GTM) and Lead (Meta) only when permitted,
                    without contact details.
                </p>
                <a
                    :href="home.url({ query: { tracking_debug: 1 } })"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary underline underline-offset-4"
                    >Open visitor diagnostics <ExternalLink class="size-3"
                /></a>
                <p class="mt-2">
                    If administrators are excluded, open the diagnostic URL in a
                    private window. Configure conversion tags in GTM and mark
                    generate_lead as a key event in GA4. Do not install the same
                    conversion tag twice through direct integrations and GTM.
                </p>
            </div>
            <div
                v-if="isSuperAdmin"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-background p-3"
            >
                <p role="status" class="text-xs text-muted-foreground">
                    {{
                        form.isDirty
                            ? 'You have unsaved changes'
                            : form.recentlySuccessful
                              ? 'Settings saved'
                              : 'No unsaved changes'
                    }}
                </p>
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="!form.isDirty || form.processing"
                        @click="
                            form.reset();
                            form.clearErrors();
                        "
                        >Reset changes</Button
                    >
                    <Button
                        type="submit"
                        :disabled="!form.isDirty || form.processing"
                        >{{
                            form.processing ? 'Saving…' : 'Save settings'
                        }}</Button
                    >
                </div>
            </div>
        </form>
    </div>
</template>
