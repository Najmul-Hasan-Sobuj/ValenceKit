<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Activity,
    CheckCircle2,
    Code,
    ExternalLink,
    Info,
    ShieldCheck,
    Sparkles,
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

interface TrackingSettingsProps {
    id: number;
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

const props = defineProps<{
    settings: TrackingSettingsProps;
}>();

const { can } = usePermissions();

const form = useForm({
    clarity_enabled: Boolean(props.settings.clarity_enabled),
    clarity_project_id: props.settings.clarity_project_id || '',
    google_analytics_enabled: Boolean(props.settings.google_analytics_enabled),
    google_analytics_id: props.settings.google_analytics_id || '',
    google_tag_manager_enabled: Boolean(
        props.settings.google_tag_manager_enabled,
    ),
    google_tag_manager_id: props.settings.google_tag_manager_id || '',
    facebook_pixel_enabled: Boolean(props.settings.facebook_pixel_enabled),
    facebook_pixel_id: props.settings.facebook_pixel_id || '',
    custom_scripts_enabled: Boolean(props.settings.custom_scripts_enabled),
    custom_head_scripts: props.settings.custom_head_scripts || '',
    custom_body_scripts: props.settings.custom_body_scripts || '',
    exclude_admins: Boolean(props.settings.exclude_admins),
});

function submit() {
    form.patch(update.url(), {
        preserveScroll: true,
    });
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Tracking settings', href: edit() }] },
});
</script>

<template>
    <Head title="Tracking & Analytics Settings" />

    <div class="flex flex-col space-y-8">
        <Heading
            variant="small"
            title="Tracking & Analytics"
            description="Manage tracking pixels, product analytics, and dynamic custom scripts injected across your site."
        />

        <form @submit.prevent="submit" class="space-y-8">
            <!-- Admin Exclusion Banner -->
            <div
                class="relative flex items-start gap-4 rounded-2xl border border-primary/20 bg-primary/5 p-5 transition-all"
            >
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <ShieldCheck class="size-5" />
                </div>
                <div class="flex-1 space-y-1">
                    <div class="flex items-center justify-between">
                        <Label
                            for="exclude_admins"
                            class="text-sm font-semibold text-foreground cursor-pointer"
                        >
                            Exclude Authenticated Admins & Admin Routes
                        </Label>
                        <Switch
                            id="exclude_admins"
                            v-model="form.exclude_admins"
                            :disabled="!can('settings.tracking.edit')"
                        />
                    </div>
                    <p class="text-xs leading-relaxed text-muted-foreground">
                        When enabled, all tracking pixels and analytics tags are
                        automatically suppressed for logged-in administrators
                        and admin panel visits to prevent polluting production
                        data.
                    </p>
                </div>
            </div>

            <!-- Provider 1: Microsoft Clarity -->
            <div
                class="rounded-2xl border border-border/80 bg-card p-6 shadow-xs space-y-5"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400"
                        >
                            <Activity class="size-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3
                                    class="text-base font-semibold text-foreground"
                                >
                                    Microsoft Clarity
                                </h3>
                                <Badge
                                    variant="outline"
                                    class="text-[10px] uppercase font-bold tracking-wider"
                                >
                                    Heatmaps & Session Replays
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Free behavioral analytics, clicks, scrolling,
                                and session video recordings.
                            </p>
                        </div>
                    </div>
                    <Switch
                        id="clarity_enabled"
                        v-model="form.clarity_enabled"
                        :disabled="!can('settings.tracking.edit')"
                    />
                </div>

                <div v-if="form.clarity_enabled" class="space-y-2 pt-2">
                    <Label
                        for="clarity_project_id"
                        class="text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                    >
                        Clarity Project ID or Snippet
                    </Label>
                    <Input
                        id="clarity_project_id"
                        v-model="form.clarity_project_id"
                        type="text"
                        placeholder="e.g. yyvng93thl"
                        class="h-11 rounded-xl font-mono text-sm"
                        :disabled="!can('settings.tracking.edit')"
                    />
                    <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <Info class="size-3.5 shrink-0" />
                        <span>
                            You can enter the Project ID directly (e.g. <code>yyvng93thl</code>) or paste the full tracking code — the ID will be automatically extracted.
                        </span>
                    </p>
                    <InputError :message="form.errors.clarity_project_id" />
                </div>
            </div>

            <!-- Provider 2: Google Analytics 4 (GA4) -->
            <div
                class="rounded-2xl border border-border/80 bg-card p-6 shadow-xs space-y-5"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400"
                        >
                            <Sparkles class="size-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3
                                    class="text-base font-semibold text-foreground"
                                >
                                    Google Analytics 4 (GA4)
                                </h3>
                                <Badge
                                    variant="outline"
                                    class="text-[10px] uppercase font-bold tracking-wider"
                                >
                                    Web Metrics
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Track visitors, pageviews, events, and conversions using Google gtag.js.
                            </p>
                        </div>
                    </div>
                    <Switch
                        id="google_analytics_enabled"
                        v-model="form.google_analytics_enabled"
                        :disabled="!can('settings.tracking.edit')"
                    />
                </div>

                <div v-if="form.google_analytics_enabled" class="space-y-2 pt-2">
                    <Label
                        for="google_analytics_id"
                        class="text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                    >
                        GA4 Measurement ID
                    </Label>
                    <Input
                        id="google_analytics_id"
                        v-model="form.google_analytics_id"
                        type="text"
                        placeholder="e.g. G-XXXXXXXXXX"
                        class="h-11 rounded-xl font-mono text-sm"
                        :disabled="!can('settings.tracking.edit')"
                    />
                    <p class="text-xs text-muted-foreground">
                        Found in your Google Analytics Admin &gt; Data Streams &gt; Measurement ID.
                    </p>
                    <InputError :message="form.errors.google_analytics_id" />
                </div>
            </div>

            <!-- Provider 3: Google Tag Manager (GTM) -->
            <div
                class="rounded-2xl border border-border/80 bg-card p-6 shadow-xs space-y-5"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-cyan-500/10 text-cyan-600 dark:text-cyan-400"
                        >
                            <Code class="size-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3
                                    class="text-base font-semibold text-foreground"
                                >
                                    Google Tag Manager (GTM)
                                </h3>
                                <Badge
                                    variant="outline"
                                    class="text-[10px] uppercase font-bold tracking-wider"
                                >
                                    Container
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Manage all marketing and analytics tags centrally via Google Tag Manager container.
                            </p>
                        </div>
                    </div>
                    <Switch
                        id="google_tag_manager_enabled"
                        v-model="form.google_tag_manager_enabled"
                        :disabled="!can('settings.tracking.edit')"
                    />
                </div>

                <div v-if="form.google_tag_manager_enabled" class="space-y-2 pt-2">
                    <Label
                        for="google_tag_manager_id"
                        class="text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                    >
                        GTM Container ID
                    </Label>
                    <Input
                        id="google_tag_manager_id"
                        v-model="form.google_tag_manager_id"
                        type="text"
                        placeholder="e.g. GTM-XXXXXXX"
                        class="h-11 rounded-xl font-mono text-sm"
                        :disabled="!can('settings.tracking.edit')"
                    />
                    <p class="text-xs text-muted-foreground">
                        Both the header script and noscript body iframe are automatically injected.
                    </p>
                    <InputError :message="form.errors.google_tag_manager_id" />
                </div>
            </div>

            <!-- Provider 4: Meta (Facebook) Pixel -->
            <div
                class="rounded-2xl border border-border/80 bg-card p-6 shadow-xs space-y-5"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                        >
                            <Activity class="size-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3
                                    class="text-base font-semibold text-foreground"
                                >
                                    Meta (Facebook) Pixel
                                </h3>
                                <Badge
                                    variant="outline"
                                    class="text-[10px] uppercase font-bold tracking-wider"
                                >
                                    Ad Retargeting
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Track conversions and run retargeting campaigns with Meta Ads.
                            </p>
                        </div>
                    </div>
                    <Switch
                        id="facebook_pixel_enabled"
                        v-model="form.facebook_pixel_enabled"
                        :disabled="!can('settings.tracking.edit')"
                    />
                </div>

                <div v-if="form.facebook_pixel_enabled" class="space-y-2 pt-2">
                    <Label
                        for="facebook_pixel_id"
                        class="text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                    >
                        Meta Pixel ID
                    </Label>
                    <Input
                        id="facebook_pixel_id"
                        v-model="form.facebook_pixel_id"
                        type="text"
                        placeholder="e.g. 123456789012345"
                        class="h-11 rounded-xl font-mono text-sm"
                        :disabled="!can('settings.tracking.edit')"
                    />
                    <p class="text-xs text-muted-foreground">
                        Standard PageView event tracking is automatically initialized.
                    </p>
                    <InputError :message="form.errors.facebook_pixel_id" />
                </div>
            </div>

            <!-- Custom Head & Body Scripts -->
            <div
                class="rounded-2xl border border-border/80 bg-card p-6 shadow-xs space-y-6"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                        >
                            <Code class="size-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3
                                    class="text-base font-semibold text-foreground"
                                >
                                    Custom Header & Body Scripts
                                </h3>
                                <Badge
                                    variant="outline"
                                    class="text-[10px] uppercase font-bold tracking-wider"
                                >
                                    Raw Code
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Paste any arbitrary &lt;script&gt; tags, meta tags, or pixels (e.g. TikTok, Twitter, LinkedIn, Hotjar).
                            </p>
                        </div>
                    </div>
                    <Switch
                        id="custom_scripts_enabled"
                        v-model="form.custom_scripts_enabled"
                        :disabled="!can('settings.tracking.edit')"
                    />
                </div>

                <div v-if="form.custom_scripts_enabled" class="space-y-6 pt-2">
                    <!-- Head Scripts -->
                    <div class="space-y-2">
                        <Label
                            for="custom_head_scripts"
                            class="text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                        >
                            Custom &lt;head&gt; Scripts
                        </Label>
                        <textarea
                            id="custom_head_scripts"
                            v-model="form.custom_head_scripts"
                            rows="5"
                            placeholder="<!-- Paste any tracking script tags for the <head> section -->&#10;<script>&#10;  // custom analytics code&#10;<\/script>"
                            class="w-full rounded-xl border border-border/80 bg-background p-3.5 font-mono text-xs leading-relaxed focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            :disabled="!can('settings.tracking.edit')"
                        ></textarea>
                        <p class="text-xs text-muted-foreground">
                            Injected right before <code>&lt;/head&gt;</code>. Dynamic CSP nonces are automatically attached to any <code>&lt;script&gt;</code> tags.
                        </p>
                        <InputError :message="form.errors.custom_head_scripts" />
                    </div>

                    <!-- Body Scripts -->
                    <div class="space-y-2">
                        <Label
                            for="custom_body_scripts"
                            class="text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                        >
                            Custom &lt;body&gt; Scripts (e.g. &lt;noscript&gt; pixels)
                        </Label>
                        <textarea
                            id="custom_body_scripts"
                            v-model="form.custom_body_scripts"
                            rows="4"
                            placeholder="<!-- Paste any fallback tracking pixels or body tags -->&#10;<noscript><img src='...' /></noscript>"
                            class="w-full rounded-xl border border-border/80 bg-background p-3.5 font-mono text-xs leading-relaxed focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            :disabled="!can('settings.tracking.edit')"
                        ></textarea>
                        <p class="text-xs text-muted-foreground">
                            Injected immediately after the opening <code>&lt;body&gt;</code> tag.
                        </p>
                        <InputError :message="form.errors.custom_body_scripts" />
                    </div>
                </div>
            </div>

            <!-- Save Action Button -->
            <div class="flex items-center justify-between pt-2">
                <Button
                    type="submit"
                    :disabled="form.processing || !can('settings.tracking.edit')"
                    class="rounded-xl px-6 py-2.5 font-semibold"
                >
                    {{ form.processing ? 'Saving...' : 'Save Tracking Settings' }}
                </Button>
            </div>
        </form>
    </div>
</template>
