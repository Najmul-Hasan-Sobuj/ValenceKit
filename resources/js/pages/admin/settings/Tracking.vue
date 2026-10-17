<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    Activity,
    ChevronDown,
    Code,
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

// Accordion open/close state for each provider card
const openSections = ref<Record<string, boolean>>({
    clarity: Boolean(props.settings.clarity_enabled),
    ga4: Boolean(props.settings.google_analytics_enabled),
    gtm: Boolean(props.settings.google_tag_manager_enabled),
    pixel: Boolean(props.settings.facebook_pixel_enabled),
    custom: Boolean(props.settings.custom_scripts_enabled),
});

function toggleSection(key: string) {
    openSections.value[key] = !openSections.value[key];
}

function handleSwitchToggle(key: string, enabled: boolean) {
    if (enabled) {
        openSections.value[key] = true;
    }
}

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

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Tracking & Analytics"
            description="Manage tracking pixels, product analytics, and dynamic custom scripts injected across your site."
        />

        <form @submit.prevent="submit" class="space-y-4">
            <!-- Admin Exclusion Banner -->
            <div
                class="relative flex items-start gap-4 rounded-2xl border border-primary/20 bg-primary/5 p-4 transition-all"
            >
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <ShieldCheck class="size-4.5" />
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

            <!-- Provider 1: Microsoft Clarity Accordion Card -->
            <div
                class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-xs transition-all"
            >
                <div
                    @click="toggleSection('clarity')"
                    class="flex cursor-pointer items-center justify-between p-4.5 hover:bg-muted/30 select-none transition-colors"
                >
                    <div class="flex items-center gap-3.5">
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400"
                        >
                            <Activity class="size-4.5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-foreground">
                                    Microsoft Clarity
                                </h3>
                                <Badge
                                    variant="outline"
                                    class="text-[10px] uppercase font-bold tracking-wider"
                                >
                                    Heatmaps & Session Replays
                                </Badge>
                                <Badge
                                    v-if="form.clarity_enabled"
                                    class="border-emerald-500/20 bg-emerald-500/10 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    Active
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Free behavioral analytics, clicks, scrolling, and session video recordings.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3" @click.stop>
                        <Switch
                            id="clarity_enabled"
                            v-model="form.clarity_enabled"
                            @update:model-value="handleSwitchToggle('clarity', $event)"
                            :disabled="!can('settings.tracking.edit')"
                        />
                        <button
                            type="button"
                            @click="toggleSection('clarity')"
                            class="text-muted-foreground hover:text-foreground p-1 transition-colors"
                            aria-label="Toggle Clarity details"
                        >
                            <ChevronDown
                                class="size-4.5 transition-transform duration-200"
                                :class="{ 'rotate-180': openSections.clarity }"
                            />
                        </button>
                    </div>
                </div>

                <div
                    v-if="openSections.clarity"
                    class="border-t border-border/60 bg-muted/10 p-5 space-y-2.5 animate-in fade-in-50 duration-200"
                >
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
                        class="h-10 rounded-xl font-mono text-sm"
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

            <!-- Provider 2: Google Analytics 4 (GA4) Accordion Card -->
            <div
                class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-xs transition-all"
            >
                <div
                    @click="toggleSection('ga4')"
                    class="flex cursor-pointer items-center justify-between p-4.5 hover:bg-muted/30 select-none transition-colors"
                >
                    <div class="flex items-center gap-3.5">
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400"
                        >
                            <Sparkles class="size-4.5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-foreground">
                                    Google Analytics 4 (GA4)
                                </h3>
                                <Badge
                                    variant="outline"
                                    class="text-[10px] uppercase font-bold tracking-wider"
                                >
                                    Web Metrics
                                </Badge>
                                <Badge
                                    v-if="form.google_analytics_enabled"
                                    class="border-emerald-500/20 bg-emerald-500/10 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    Active
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Track visitors, pageviews, events, and conversions using Google gtag.js.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3" @click.stop>
                        <Switch
                            id="google_analytics_enabled"
                            v-model="form.google_analytics_enabled"
                            @update:model-value="handleSwitchToggle('ga4', $event)"
                            :disabled="!can('settings.tracking.edit')"
                        />
                        <button
                            type="button"
                            @click="toggleSection('ga4')"
                            class="text-muted-foreground hover:text-foreground p-1 transition-colors"
                            aria-label="Toggle GA4 details"
                        >
                            <ChevronDown
                                class="size-4.5 transition-transform duration-200"
                                :class="{ 'rotate-180': openSections.ga4 }"
                            />
                        </button>
                    </div>
                </div>

                <div
                    v-if="openSections.ga4"
                    class="border-t border-border/60 bg-muted/10 p-5 space-y-2.5 animate-in fade-in-50 duration-200"
                >
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
                        class="h-10 rounded-xl font-mono text-sm"
                        :disabled="!can('settings.tracking.edit')"
                    />
                    <p class="text-xs text-muted-foreground">
                        Found in your Google Analytics Admin &gt; Data Streams &gt; Measurement ID.
                    </p>
                    <InputError :message="form.errors.google_analytics_id" />
                </div>
            </div>

            <!-- Provider 3: Google Tag Manager (GTM) Accordion Card -->
            <div
                class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-xs transition-all"
            >
                <div
                    @click="toggleSection('gtm')"
                    class="flex cursor-pointer items-center justify-between p-4.5 hover:bg-muted/30 select-none transition-colors"
                >
                    <div class="flex items-center gap-3.5">
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-cyan-500/10 text-cyan-600 dark:text-cyan-400"
                        >
                            <Code class="size-4.5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-foreground">
                                    Google Tag Manager (GTM)
                                </h3>
                                <Badge
                                    variant="outline"
                                    class="text-[10px] uppercase font-bold tracking-wider"
                                >
                                    Container
                                </Badge>
                                <Badge
                                    v-if="form.google_tag_manager_enabled"
                                    class="border-emerald-500/20 bg-emerald-500/10 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    Active
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Manage all marketing and analytics tags centrally via Google Tag Manager container.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3" @click.stop>
                        <Switch
                            id="google_tag_manager_enabled"
                            v-model="form.google_tag_manager_enabled"
                            @update:model-value="handleSwitchToggle('gtm', $event)"
                            :disabled="!can('settings.tracking.edit')"
                        />
                        <button
                            type="button"
                            @click="toggleSection('gtm')"
                            class="text-muted-foreground hover:text-foreground p-1 transition-colors"
                            aria-label="Toggle GTM details"
                        >
                            <ChevronDown
                                class="size-4.5 transition-transform duration-200"
                                :class="{ 'rotate-180': openSections.gtm }"
                            />
                        </button>
                    </div>
                </div>

                <div
                    v-if="openSections.gtm"
                    class="border-t border-border/60 bg-muted/10 p-5 space-y-2.5 animate-in fade-in-50 duration-200"
                >
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
                        class="h-10 rounded-xl font-mono text-sm"
                        :disabled="!can('settings.tracking.edit')"
                    />
                    <p class="text-xs text-muted-foreground">
                        Both the header script and noscript body iframe are automatically injected.
                    </p>
                    <InputError :message="form.errors.google_tag_manager_id" />
                </div>
            </div>

            <!-- Provider 4: Meta (Facebook) Pixel Accordion Card -->
            <div
                class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-xs transition-all"
            >
                <div
                    @click="toggleSection('pixel')"
                    class="flex cursor-pointer items-center justify-between p-4.5 hover:bg-muted/30 select-none transition-colors"
                >
                    <div class="flex items-center gap-3.5">
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                        >
                            <Activity class="size-4.5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-foreground">
                                    Meta (Facebook) Pixel
                                </h3>
                                <Badge
                                    variant="outline"
                                    class="text-[10px] uppercase font-bold tracking-wider"
                                >
                                    Ad Retargeting
                                </Badge>
                                <Badge
                                    v-if="form.facebook_pixel_enabled"
                                    class="border-emerald-500/20 bg-emerald-500/10 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    Active
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Track conversions and run retargeting campaigns with Meta Ads.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3" @click.stop>
                        <Switch
                            id="facebook_pixel_enabled"
                            v-model="form.facebook_pixel_enabled"
                            @update:model-value="handleSwitchToggle('pixel', $event)"
                            :disabled="!can('settings.tracking.edit')"
                        />
                        <button
                            type="button"
                            @click="toggleSection('pixel')"
                            class="text-muted-foreground hover:text-foreground p-1 transition-colors"
                            aria-label="Toggle Meta Pixel details"
                        >
                            <ChevronDown
                                class="size-4.5 transition-transform duration-200"
                                :class="{ 'rotate-180': openSections.pixel }"
                            />
                        </button>
                    </div>
                </div>

                <div
                    v-if="openSections.pixel"
                    class="border-t border-border/60 bg-muted/10 p-5 space-y-2.5 animate-in fade-in-50 duration-200"
                >
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
                        class="h-10 rounded-xl font-mono text-sm"
                        :disabled="!can('settings.tracking.edit')"
                    />
                    <p class="text-xs text-muted-foreground">
                        Standard PageView event tracking is automatically initialized.
                    </p>
                    <InputError :message="form.errors.facebook_pixel_id" />
                </div>
            </div>

            <!-- Provider 5: Custom Head & Body Scripts Accordion Card -->
            <div
                class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-xs transition-all"
            >
                <div
                    @click="toggleSection('custom')"
                    class="flex cursor-pointer items-center justify-between p-4.5 hover:bg-muted/30 select-none transition-colors"
                >
                    <div class="flex items-center gap-3.5">
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                        >
                            <Code class="size-4.5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-foreground">
                                    Custom Header & Body Scripts
                                </h3>
                                <Badge
                                    variant="outline"
                                    class="text-[10px] uppercase font-bold tracking-wider"
                                >
                                    Raw Code
                                </Badge>
                                <Badge
                                    v-if="form.custom_scripts_enabled"
                                    class="border-emerald-500/20 bg-emerald-500/10 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    Active
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Paste any arbitrary &lt;script&gt; tags, meta tags, or pixels (e.g. TikTok, Twitter, LinkedIn, Hotjar).
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3" @click.stop>
                        <Switch
                            id="custom_scripts_enabled"
                            v-model="form.custom_scripts_enabled"
                            @update:model-value="handleSwitchToggle('custom', $event)"
                            :disabled="!can('settings.tracking.edit')"
                        />
                        <button
                            type="button"
                            @click="toggleSection('custom')"
                            class="text-muted-foreground hover:text-foreground p-1 transition-colors"
                            aria-label="Toggle Custom Scripts details"
                        >
                            <ChevronDown
                                class="size-4.5 transition-transform duration-200"
                                :class="{ 'rotate-180': openSections.custom }"
                            />
                        </button>
                    </div>
                </div>

                <div
                    v-if="openSections.custom"
                    class="border-t border-border/60 bg-muted/10 p-5 space-y-5 animate-in fade-in-50 duration-200"
                >
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
                            rows="4"
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
                            rows="3"
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
