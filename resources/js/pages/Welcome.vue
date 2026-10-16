<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, reactive } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { dashboard, home, login, register, services } from '@/routes';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AppHead from '@/components/AppHead.vue';
import PublicFooter from '@/components/PublicFooter.vue';
import StructuredData from '@/components/StructuredData.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import InputError from '@/components/InputError.vue';
import {
    Sun,
    Moon,
    Send,
    Check,
    Mail,
    Clock,
    ShieldCheck,
    ArrowRight,
    RotateCcw,
    Layers,
    Boxes,
    Activity,
    Database,
    Lock,
} from '@lucide/vue';

const page = usePage<{ name?: string; appUrl: string }>();
const appName = page.props.name || 'ValenceKit';
const appUrl = page.props.appUrl.replace(/\/$/, '');
const pageTitle = 'Laravel & Vue.js Boilerplate';
const pageDescription =
    'ValenceKit is a modern Laravel and Vue.js boilerplate for building fast, secure web applications with Inertia.js, SSR, APIs, and production-ready tools.';

const structuredData = computed(() => [
    {
        '@type': 'Organization',
        '@id': `${appUrl}/#organization`,
        name: appName,
        url: appUrl,
        logo: `${appUrl}/favicon.svg`,
    },
    {
        '@type': 'WebSite',
        '@id': `${appUrl}/#website`,
        name: appName,
        url: appUrl,
        description: pageDescription,
        publisher: {
            '@id': `${appUrl}/#organization`,
        },
    },
    {
        '@type': 'WebPage',
        '@id': `${appUrl}/#webpage`,
        name: pageTitle,
        url: appUrl,
        description: pageDescription,
        isPartOf: {
            '@id': `${appUrl}/#website`,
        },
        about: {
            '@id': `${appUrl}/#organization`,
        },
    },
]);

const { appearance, resolvedAppearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};

const form = reactive({
    name: '',
    email: '',
    phoneNumber: '',
    message: '',
    referralSource: '',
});

const errors = reactive({
    name: '',
    email: '',
    message: '',
});

const isSubmitting = ref(false);
const isSubmitted = ref(false);

const validateForm = () => {
    errors.name = '';
    errors.email = '';
    errors.message = '';

    let isValid = true;

    if (!form.name.trim()) {
        errors.name = 'Please provide your name.';
        isValid = false;
    }

    if (!form.email.trim()) {
        errors.email = 'Please provide your work email.';
        isValid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
        errors.email = 'Please enter a valid email address.';
        isValid = false;
    }

    if (!form.message.trim()) {
        errors.message = 'Please tell us about your project or inquiry.';
        isValid = false;
    }

    return isValid;
};

const handleSubmit = () => {
    if (!validateForm()) {
        return;
    }

    isSubmitting.value = true;

    // Smooth client submission feedback
    setTimeout(() => {
        isSubmitting.value = false;
        isSubmitted.value = true;
    }, 600);
};

const resetForm = () => {
    form.name = '';
    form.email = '';
    form.phoneNumber = '';
    form.message = '';
    form.referralSource = '';
    isSubmitted.value = false;
};
</script>

<template>
    <div
        class="min-h-screen bg-[#FDFDFC] text-[#1b1b18] antialiased selection:bg-primary selection:text-primary-foreground dark:bg-[#0a0a0a] dark:text-[#EDEDEC]"
    >
        <AppHead
            :title="pageTitle"
            :description="pageDescription"
        />
        <StructuredData :entities="structuredData" />

        <!-- Top Header Navigation -->
        <header
            class="sticky top-0 z-40 w-full border-b border-border/80 bg-background/80 backdrop-blur-md transition-colors"
        >
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8"
            >
                <!-- App Logo -->
                <Link
                    :href="home()"
                    class="flex items-center gap-2 outline-none"
                    :aria-label="`${appName} home`"
                >
                    <div
                        class="flex aspect-square size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon
                            class="size-5 fill-current text-white dark:text-black"
                        />
                    </div>
                    <span
                        class="hidden text-base font-semibold tracking-tight sm:inline"
                        >{{ appName }}</span
                    >
                </Link>

                <!-- Navigation Links -->
                <nav class="flex items-center gap-4 text-sm sm:gap-6">
                    <Link
                        :href="services()"
                        class="font-medium text-muted-foreground transition-colors hover:text-foreground"
                    >
                        Services
                    </Link>
                    <Link
                        :href="home()"
                        class="font-semibold text-foreground transition-colors"
                    >
                        Contact
                    </Link>
                </nav>

                <!-- Header Actions -->
                <div class="flex items-center gap-2 text-sm sm:gap-3">
                    <!-- Theme Toggle -->
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="toggleTheme"
                        class="size-8 rounded-md text-muted-foreground hover:text-foreground sm:size-9"
                        :title="
                            resolvedAppearance === 'dark'
                                ? 'Switch to light mode'
                                : 'Switch to dark mode'
                        "
                        type="button"
                    >
                        <Sun
                            v-if="resolvedAppearance === 'dark'"
                            class="size-4"
                        />
                        <Moon v-else class="size-4" />
                        <span class="sr-only">Toggle theme</span>
                    </Button>

                    <!-- Auth Links -->
                    <template v-if="$page.props.auth?.user">
                        <Link
                            :href="dashboard()"
                            class="inline-flex h-8 items-center justify-center rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground shadow-xs transition-colors hover:bg-primary/90 sm:h-9 sm:px-4 sm:text-sm"
                        >
                            Dashboard
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-flex h-8 items-center justify-center rounded-md px-2.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground sm:h-9 sm:px-3 sm:text-sm"
                        >
                            Log in
                        </Link>
                        <Link
                            :href="register()"
                            class="inline-flex h-8 items-center justify-center rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground shadow-xs transition-colors hover:bg-primary/90 sm:h-9 sm:px-4 sm:text-sm"
                        >
                            Register
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Main Contact Section (UI reference from Bejamas get-in-touch, styled with project design & classes) -->
        <main class="mx-auto max-w-6xl px-6 py-12 lg:px-8 lg:py-20">
            <div
                class="grid grid-cols-1 items-start gap-12 lg:grid-cols-12 lg:gap-16"
            >
                <!-- Left Column: Header & Context Details -->
                <div class="flex flex-col gap-6 lg:col-span-5 lg:pt-4">
                    <div class="space-y-4">
                        <p
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Get In Touch
                        </p>
                        <h1
                            class="text-4xl font-semibold tracking-tight text-foreground sm:text-5xl lg:text-6xl"
                        >
                            Tell us about your project.
                        </h1>
                        <p
                            class="text-base leading-relaxed text-muted-foreground sm:text-lg"
                        >
                            Tell us what you're working with, what's slowing
                            your team down, and what needs to change. We'll
                            reply with a practical next step. If a quick call
                            makes more sense, we'll suggest one.
                        </p>
                    </div>

                    <!-- Informational Highlights -->
                    <div
                        class="mt-4 flex flex-col gap-5 border-t border-border pt-6 text-sm text-muted-foreground"
                    >
                        <div class="flex items-start gap-3">
                            <Clock
                                class="mt-0.5 size-5 shrink-0 text-foreground/80"
                            />
                            <div>
                                <p class="font-medium text-foreground">
                                    Fast Turnaround
                                </p>
                                <p>
                                    We typically review and reply within 24
                                    hours on business days.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <ShieldCheck
                                class="mt-0.5 size-5 shrink-0 text-foreground/80"
                            />
                            <div>
                                <p class="font-medium text-foreground">
                                    Security & Confidentiality
                                </p>
                                <p>
                                    We are comfortable with DPAs, standard NDAs,
                                    and formal RFP evaluations.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <Mail
                                class="mt-0.5 size-5 shrink-0 text-foreground/80"
                            />
                            <div>
                                <p class="font-medium text-foreground">
                                    Prefer direct email?
                                </p>
                                <p>
                                    Write to
                                    <a
                                        href="mailto:hello@valencekit.test"
                                        class="font-medium text-foreground underline underline-offset-4 hover:text-primary"
                                    >
                                        hello@valencekit.test
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Contact Form (Card using existing project design and UI classes) -->
                <div class="lg:col-span-7">
                    <Card class="rounded-xl border-border bg-card shadow-sm">
                        <!-- Submission Success View -->
                        <div
                            v-if="isSubmitted"
                            class="flex flex-col items-center p-8 text-center sm:p-12"
                        >
                            <div
                                class="mb-6 flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary"
                            >
                                <Check class="size-7" />
                            </div>
                            <h2
                                class="mb-3 text-2xl font-semibold tracking-tight text-foreground sm:text-3xl"
                            >
                                Message sent successfully!
                            </h2>
                            <p
                                class="mb-8 max-w-md text-base text-muted-foreground"
                            >
                                Thank you for reaching out, {{ form.name }}.
                                We've received your inquiry and our team will
                                get back to you at
                                <span class="font-medium text-foreground">{{
                                    form.email
                                }}</span>
                                shortly.
                            </p>
                            <Button
                                @click="resetForm"
                                variant="outline"
                                class="gap-2"
                            >
                                <RotateCcw class="size-4" />
                                Send another message
                            </Button>
                        </div>

                        <!-- Active Form View -->
                        <template v-else>
                            <CardHeader class="pb-4">
                                <CardTitle
                                    as="h2"
                                    class="text-2xl font-semibold tracking-tight"
                                >
                                    Send us a message
                                </CardTitle>
                                <CardDescription>
                                    Fill out the form below and we'll get back
                                    to you with actionable recommendations.
                                </CardDescription>
                            </CardHeader>

                            <CardContent>
                                <form
                                    @submit.prevent="handleSubmit"
                                    class="space-y-6"
                                >
                                    <!-- Full Name Field -->
                                    <div class="grid gap-2">
                                        <Label
                                            for="full-name"
                                            class="text-sm font-medium"
                                        >
                                            Your name
                                            <span class="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="full-name"
                                            v-model="form.name"
                                            placeholder="First and last name"
                                            required
                                            autocomplete="name"
                                            class="w-full"
                                            :aria-invalid="!!errors.name"
                                        />
                                        <InputError :message="errors.name" />
                                    </div>

                                    <!-- Work Email Field -->
                                    <div class="grid gap-2">
                                        <Label
                                            for="work-email"
                                            class="text-sm font-medium"
                                        >
                                            Work email
                                            <span class="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="work-email"
                                            v-model="form.email"
                                            type="email"
                                            placeholder="you@company.com"
                                            required
                                            autocomplete="email"
                                            class="w-full"
                                            :aria-invalid="!!errors.email"
                                        />
                                        <InputError :message="errors.email" />
                                    </div>

                                    <!-- Phone Number Field (Optional) -->
                                    <div class="grid gap-2">
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <Label
                                                for="phone-number"
                                                class="text-sm font-medium"
                                            >
                                                Phone
                                            </Label>
                                            <span
                                                class="text-xs text-muted-foreground"
                                                >(optional)</span
                                            >
                                        </div>
                                        <Input
                                            id="phone-number"
                                            v-model="form.phoneNumber"
                                            type="tel"
                                            placeholder="Include your country code"
                                            autocomplete="tel"
                                            class="w-full"
                                        />
                                    </div>

                                    <!-- Project Message Field -->
                                    <div class="grid gap-2">
                                        <Label
                                            for="project-message"
                                            class="text-sm font-medium"
                                        >
                                            Tell us about your project
                                            <span class="text-red-500">*</span>
                                        </Label>
                                        <textarea
                                            id="project-message"
                                            v-model="form.message"
                                            rows="4"
                                            required
                                            placeholder="What are you looking to migrate or improve?"
                                            class="w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:bg-input/30 dark:aria-invalid:ring-destructive/40"
                                            :aria-invalid="!!errors.message"
                                        ></textarea>
                                        <InputError :message="errors.message" />
                                    </div>

                                    <!-- Referral Source Field (Optional) -->
                                    <div class="grid gap-2">
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <Label
                                                for="referral-source"
                                                class="text-sm font-medium"
                                            >
                                                How did you hear about us?
                                            </Label>
                                            <span
                                                class="text-xs text-muted-foreground"
                                                >(optional)</span
                                            >
                                        </div>
                                        <Input
                                            id="referral-source"
                                            v-model="form.referralSource"
                                            placeholder="Google, LinkedIn, a recommendation…"
                                            class="w-full"
                                        />
                                    </div>

                                    <!-- Submit Button & Submission Status -->
                                    <div
                                        class="flex flex-col gap-4 pt-2 sm:flex-row sm:items-center"
                                    >
                                        <Button
                                            type="submit"
                                            size="lg"
                                            :disabled="isSubmitting"
                                            class="w-full gap-2 font-medium sm:w-auto sm:min-w-44"
                                        >
                                            <span
                                                v-if="isSubmitting"
                                                class="flex items-center gap-2"
                                            >
                                                <svg
                                                    class="size-4 animate-spin text-current"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                >
                                                    <circle
                                                        class="opacity-25"
                                                        cx="12"
                                                        cy="12"
                                                        r="10"
                                                        stroke="currentColor"
                                                        stroke-width="4"
                                                    ></circle>
                                                    <path
                                                        class="opacity-75"
                                                        fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                                    ></path>
                                                </svg>
                                                Sending...
                                            </span>
                                            <span
                                                v-else
                                                class="flex items-center gap-2"
                                            >
                                                Send it over
                                                <ArrowRight class="size-4" />
                                            </span>
                                        </Button>

                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            No spam. We only use your details to
                                            reply.
                                        </p>
                                    </div>

                                    <!-- Bottom Procurement & RFP Note -->
                                    <div
                                        class="mt-6 space-y-1.5 border-t border-border pt-4 text-xs text-muted-foreground"
                                    >
                                        <p>
                                            Running an RFP or working through
                                            procurement? Send it over. We're
                                            used to security reviews, DPAs, and
                                            formal evaluation processes.
                                        </p>
                                    </div>
                                </form>
                            </CardContent>
                        </template>
                    </Card>
                </div>
            </div>

            <!-- Platform Interface & Architecture Preview -->
            <section class="mt-16 border-t border-border pt-12 sm:mt-24 sm:pt-16">
                <!-- Section Header with eyebrow badge matching Services page & design tokens -->
                <div class="mb-8 max-w-2xl">
                    <div class="flex items-center gap-2">
                        <span
                            class="flex size-6 items-center justify-center rounded-md border border-border/60 bg-muted text-foreground"
                        >
                            <Layers class="size-3.5" aria-hidden="true" />
                        </span>
                        <p
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Interface & Architecture
                        </p>
                    </div>
                    <h2
                        class="mt-3 text-2xl font-semibold tracking-tight text-foreground sm:text-3xl lg:text-4xl"
                    >
                        Built for developer velocity and operational clarity.
                    </h2>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground sm:text-base">
                        ValenceKit provides structured administrative interfaces,
                        real-time telemetry, finance tracking, and least-privilege MCP integrations out of the box.
                    </p>
                </div>

                <!-- Showcase Frame: App Window with Chrome & Status Bar -->
                <div
                    class="overflow-hidden rounded-xl border border-border bg-card shadow-sm sm:rounded-2xl"
                >
                    <!-- macOS-style App Window Header -->
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-border/80 bg-muted/40 px-4 py-3 sm:px-6"
                    >
                        <div class="flex items-center gap-2">
                            <span class="size-2.5 rounded-full bg-red-500/80 sm:size-3" />
                            <span class="size-2.5 rounded-full bg-yellow-500/80 sm:size-3" />
                            <span class="size-2.5 rounded-full bg-emerald-500/80 sm:size-3" />
                            <span
                                class="ml-2 hidden font-mono text-xs text-muted-foreground md:inline-block"
                            >
                                valencekit-admin
                            </span>
                        </div>

                        <!-- Center URL Pill / Secure Location -->
                        <div
                            class="flex items-center gap-1.5 rounded-md border border-border/70 bg-background/90 px-3 py-1 font-mono text-xs text-muted-foreground shadow-2xs"
                        >
                            <Lock class="size-3 text-emerald-500" />
                            <span class="text-foreground">valencekit.test</span>
                            <span class="text-muted-foreground/60">/admin/dashboard</span>
                        </div>

                        <!-- Right Live System Status Indicator -->
                        <div class="flex items-center gap-2">
                            <span class="relative flex size-2">
                                <span
                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                                />
                                <span
                                    class="relative inline-flex size-2 rounded-full bg-emerald-500"
                                />
                            </span>
                            <span class="font-mono text-xs text-muted-foreground">
                                Telemetry Active
                            </span>
                        </div>
                    </div>

                    <!-- Image Viewport -->
                    <div class="aspect-video w-full overflow-hidden bg-muted">
                        <img
                            src="/static/welcome/dashboard-preview.webp"
                            alt="ValenceKit administrative dashboard showing financial analytics, user management, and MCP telemetry"
                            width="1376"
                            height="768"
                            loading="lazy"
                            decoding="async"
                            class="h-full w-full object-cover transition-transform duration-500 hover:scale-[1.01]"
                        />
                    </div>
                </div>

                <!-- Architecture Capabilities Bento Grid -->
                <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        class="flex flex-col justify-between rounded-xl border border-border/70 bg-card/60 p-5 transition-colors hover:border-border hover:bg-card"
                    >
                        <div class="space-y-3">
                            <div
                                class="flex size-8 items-center justify-center rounded-lg border border-border/80 bg-muted/60 text-foreground"
                            >
                                <Boxes class="size-4" aria-hidden="true" />
                            </div>
                            <h3 class="text-sm font-semibold text-foreground">
                                MCP Protocol Native
                            </h3>
                            <p class="text-xs leading-relaxed text-muted-foreground">
                                Direct integration with AI agents via Model Context Protocol tools, resources, and live prompts in routes/ai.php.
                            </p>
                        </div>
                        <div class="mt-4 border-t border-border/50 pt-3">
                            <span class="font-mono text-[11px] text-muted-foreground">
                                SSE Transport · Read/Write
                            </span>
                        </div>
                    </div>

                    <div
                        class="flex flex-col justify-between rounded-xl border border-border/70 bg-card/60 p-5 transition-colors hover:border-border hover:bg-card"
                    >
                        <div class="space-y-3">
                            <div
                                class="flex size-8 items-center justify-center rounded-lg border border-border/80 bg-muted/60 text-foreground"
                            >
                                <Activity class="size-4" aria-hidden="true" />
                            </div>
                            <h3 class="text-sm font-semibold text-foreground">
                                Real-Time Telemetry
                            </h3>
                            <p class="text-xs leading-relaxed text-muted-foreground">
                                Comprehensive audit trail logs, query profiling, security event monitoring, and automatic sensitive data redaction.
                            </p>
                        </div>
                        <div class="mt-4 border-t border-border/50 pt-3">
                            <span class="font-mono text-[11px] text-muted-foreground">
                                Sub-ms Latency · Sentry Ready
                            </span>
                        </div>
                    </div>

                    <div
                        class="flex flex-col justify-between rounded-xl border border-border/70 bg-card/60 p-5 transition-colors hover:border-border hover:bg-card"
                    >
                        <div class="space-y-3">
                            <div
                                class="flex size-8 items-center justify-center rounded-lg border border-border/80 bg-muted/60 text-foreground"
                            >
                                <ShieldCheck class="size-4" aria-hidden="true" />
                            </div>
                            <h3 class="text-sm font-semibold text-foreground">
                                Fortify & Passkeys
                            </h3>
                            <p class="text-xs leading-relaxed text-muted-foreground">
                                Complete authentication suite with WebAuthn biometric passkeys, TOTP two-factor auth, and granular Spatie permissions.
                            </p>
                        </div>
                        <div class="mt-4 border-t border-border/50 pt-3">
                            <span class="font-mono text-[11px] text-muted-foreground">
                                FIDO2 · Role-Based Access
                            </span>
                        </div>
                    </div>

                    <div
                        class="flex flex-col justify-between rounded-xl border border-border/70 bg-card/60 p-5 transition-colors hover:border-border hover:bg-card"
                    >
                        <div class="space-y-3">
                            <div
                                class="flex size-8 items-center justify-center rounded-lg border border-border/80 bg-muted/60 text-foreground"
                            >
                                <Database class="size-4" aria-hidden="true" />
                            </div>
                            <h3 class="text-sm font-semibold text-foreground">
                                Financial Ledgers
                            </h3>
                            <p class="text-xs leading-relaxed text-muted-foreground">
                                Relational transactional ledger architecture with category hierarchies, soft deletes, and idempotent payment webhooks.
                            </p>
                        </div>
                        <div class="mt-4 border-t border-border/50 pt-3">
                            <span class="font-mono text-[11px] text-muted-foreground">
                                ACID Safe · SSLCommerz/bKash
                            </span>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <PublicFooter />
    </div>
</template>
