<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import AppHead from '@/components/AppHead.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import PublicFooter from '@/components/PublicFooter.vue';
import StructuredData from '@/components/StructuredData.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { home, privacyPolicy, terms } from '@/routes';
import ScrollProgress from '@/components/ScrollProgress.vue';
import ScrollToTop from '@/components/ScrollToTop.vue';
import CardSpotlight from '@/components/CardSpotlight.vue';
import { useScrollReveal } from '@/composables/useScrollReveal';
import {
    Scale,
    Shield,
    KeyRound,
    ShieldAlert,
    CheckCircle2,
    Clock,
    BookOpen,
    FileText,
    Hash,
    Check,
    ArrowUp,
    ArrowRight,
    Mail,
    RefreshCw,
    Layers,
    Info,
    AlertCircle,
    UserCheck,
} from '@lucide/vue';

const page = usePage<{ name?: string; appUrl: string }>();
const appName = page.props.name || 'ValenceKit';
const appUrl = page.props.appUrl.replace(/\/$/, '');
const pageTitle = 'Terms of Service';
const pageDescription = `Terms for using the ${appName} public site and application.`;
const pageUrl = `${appUrl}/terms`;

const breadcrumbs = [
    { title: 'Home', href: home().url },
    { title: 'Legal', href: terms().url },
    { title: pageTitle, href: pageUrl },
];

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
        publisher: { '@id': `${appUrl}/#organization` },
    },
    {
        '@type': 'WebPage',
        '@id': `${pageUrl}#webpage`,
        name: pageTitle,
        url: pageUrl,
        description: pageDescription,
        dateModified: '2026-10-12',
        isPartOf: { '@id': `${appUrl}/#website` },
    },
    {
        '@type': 'BreadcrumbList',
        '@id': `${pageUrl}#breadcrumb`,
        itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'Home', item: appUrl },
            { '@type': 'ListItem', position: 2, name: pageTitle, item: pageUrl },
        ],
    },
]);

// Summary highlight cards (Executive Summary / At a Glance)
const highlights = [
    {
        icon: CheckCircle2,
        title: 'Lawful & Fair Use',
        description:
            `Use ${appName} responsibly and in full compliance with applicable laws without disrupting other users.`,
    },
    {
        icon: KeyRound,
        title: 'Credential Custody',
        description:
            'Maintain strict confidentiality over passwords, passkey authentication devices, and active sessions.',
    },
    {
        icon: ShieldAlert,
        title: 'Zero Abuse Policy',
        description:
            'Strict prohibition against security testing without consent, malicious code, and data scraping.',
    },
    {
        icon: RefreshCw,
        title: 'Transparent Updates',
        description:
            'Features evolve continuously for optimal security and reliability, with clear notice of major updates.',
    },
];

// Navigation sections
const sections = [
    {
        id: 'agreement',
        number: '01',
        title: 'Agreement to terms',
        icon: Scale,
    },
    {
        id: 'accounts',
        number: '02',
        title: 'Accounts and security',
        icon: KeyRound,
    },
    {
        id: 'acceptable-use',
        number: '03',
        title: 'Acceptable use standards',
        icon: ShieldAlert,
    },
    {
        id: 'availability',
        number: '04',
        title: 'Availability and changes',
        icon: Layers,
    },
    {
        id: 'contact',
        number: '05',
        title: 'Contact and jurisdiction',
        icon: Mail,
    },
];

const activeSection = ref(sections[0].id);
const copiedSection = ref<string | null>(null);

let observer: IntersectionObserver | null = null;

onMounted(() => {
    if (typeof window !== 'undefined' && 'IntersectionObserver' in window) {
        observer = new IntersectionObserver(
            (entries) => {
                const intersecting = entries.find((e) => e.isIntersecting);
                if (intersecting) {
                    activeSection.value = intersecting.target.id;
                }
            },
            {
                rootMargin: '-20% 0px -65% 0px',
                threshold: 0,
            },
        );

        sections.forEach((sec) => {
            const el = document.getElementById(sec.id);
            if (el && observer) {
                observer.observe(el);
            }
        });
    }
});

onUnmounted(() => {
    if (observer) {
        observer.disconnect();
    }
});

const scrollToSection = (id: string) => {
    const el = document.getElementById(id);
    if (el) {
        activeSection.value = id;
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        if (typeof window !== 'undefined') {
            window.history.pushState(null, '', `#${id}`);
        }
    }
};

const copySectionLink = (id: string) => {
    if (typeof window !== 'undefined') {
        const url = `${window.location.origin}${window.location.pathname}#${id}`;
        navigator.clipboard?.writeText(url);
        copiedSection.value = id;
        setTimeout(() => {
            if (copiedSection.value === id) {
                copiedSection.value = null;
            }
        }, 2000);
    }
};

const scrollToTop = () => {
    if (typeof window !== 'undefined') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

useScrollReveal();
</script>

<template>
    <ScrollProgress />
    <div
        class="min-h-screen bg-[#FDFDFC] text-[#1b1b18] antialiased selection:bg-primary selection:text-primary-foreground dark:bg-[#0a0a0a] dark:text-[#EDEDEC]"
    >
        <AppHead :title="pageTitle" :description="pageDescription" />
        <StructuredData :entities="structuredData" />
        <PublicHeader />

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-16">
            <!-- Breadcrumbs -->
            <div class="mb-6">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>

            <!-- Hero Section -->
            <header class="space-y-6 pb-12 border-b border-border">
                <div class="flex flex-wrap items-center gap-3">
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-border/80 bg-background/90 px-3.5 py-1 text-xs font-semibold tracking-wide text-foreground shadow-xs"
                    >
                        <Scale class="size-3.5 text-primary" />
                        <span>Legal & Terms</span>
                    </div>

                    <div
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <Clock class="size-3.5" />
                        <span>Last updated: October 12, 2026</span>
                    </div>

                    <span class="text-muted-foreground/40 hidden sm:inline"
                        >•</span
                    >

                    <div
                        class="hidden sm:flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <BookOpen class="size-3.5" />
                        <span>~3 min read</span>
                    </div>
                </div>

                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div class="max-w-3xl space-y-3">
                        <h1
                            class="text-4xl font-semibold tracking-tight text-foreground sm:text-5xl lg:text-6xl"
                        >
                            {{ pageTitle }}
                        </h1>
                        <p
                            class="text-base leading-relaxed text-muted-foreground sm:text-lg"
                        >
                            {{ pageDescription }} Please read these conditions
                            carefully to understand mutual responsibilities,
                            acceptable usage rules, and account protections.
                        </p>
                    </div>

                    <!-- Document switcher & actions -->
                    <div class="flex flex-wrap items-center gap-2.5 pt-2 lg:pt-0">
                        <div
                            class="inline-flex rounded-lg border border-border bg-muted/40 p-1"
                        >
                            <Link
                                :href="privacyPolicy()"
                                class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                            >
                                <FileText class="size-3.5" />
                                Privacy Policy
                            </Link>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-md bg-background px-3 py-1.5 text-xs font-medium text-foreground shadow-xs"
                            >
                                <Scale class="size-3.5 text-primary" />
                                Terms of Service
                            </span>
                        </div>
                    </div>
                </div>

                <!-- At a Glance / Bento Summary Grid -->
                <div class="pt-6">
                    <p
                        class="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        At a Glance
                    </p>
                    <div
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <CardSpotlight
                            v-for="(item, index) in highlights"
                            :key="index"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-border/70 bg-background text-primary shadow-2xs"
                                >
                                    <component
                                        :is="item.icon"
                                        class="size-4"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <h2
                                        class="text-xs font-semibold text-foreground tracking-tight"
                                    >
                                        {{ item.title }}
                                    </h2>
                                    <p
                                        class="text-xs leading-relaxed text-muted-foreground"
                                    >
                                        {{ item.description }}
                                    </p>
                                </div>
                            </div>
                        </CardSpotlight>
                    </div>
                </div>
            </header>

            <!-- Main Layout: Sidebar Navigation + Detailed Content -->
            <div
                class="mt-12 grid grid-cols-1 items-start gap-12 lg:grid-cols-12 lg:gap-16"
            >
                <!-- Sticky Sidebar Table of Contents (Desktop) -->
                <aside
                    class="lg:sticky lg:top-24 lg:col-span-4 space-y-6 self-start"
                >
                    <div
                        class="rounded-xl border border-border/80 bg-card p-5 shadow-xs"
                    >
                        <div class="flex items-center justify-between pb-3 mb-2">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex size-6 items-center justify-center rounded-md border border-border/60 bg-muted text-foreground"
                                >
                                    <Hash class="size-3.5" />
                                </span>
                                <h3
                                    class="text-xs font-semibold tracking-wider text-foreground uppercase"
                                >
                                    Table of Contents
                                </h3>
                            </div>
                            <span class="text-xs text-muted-foreground">
                                {{ sections.length }} sections
                            </span>
                        </div>

                        <Separator class="mb-3" />

                        <nav
                            class="space-y-1"
                            aria-label="Terms of Service sections"
                        >
                            <button
                                v-for="sec in sections"
                                :key="sec.id"
                                type="button"
                                @click="scrollToSection(sec.id)"
                                class="group flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-xs font-medium transition-all"
                                :class="[
                                    activeSection === sec.id
                                        ? 'bg-primary/10 text-primary font-semibold'
                                        : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                                ]"
                            >
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="font-mono text-[11px] opacity-70"
                                        :class="{
                                            'text-primary font-bold':
                                                activeSection === sec.id,
                                        }"
                                    >
                                        {{ sec.number }}
                                    </span>
                                    <span>{{ sec.title }}</span>
                                </div>
                                <span
                                    v-if="activeSection === sec.id"
                                    class="size-1.5 rounded-full bg-primary"
                                />
                            </button>
                        </nav>

                        <!-- Support Card inside Sidebar -->
                        <div
                            class="mt-6 rounded-lg border border-border/70 bg-muted/30 p-4 text-xs"
                        >
                            <div class="flex items-start gap-2.5">
                                <Mail
                                    class="mt-0.5 size-4 text-foreground/80 shrink-0"
                                />
                                <div class="space-y-1">
                                    <p class="font-medium text-foreground">
                                        Questions about terms?
                                    </p>
                                    <p class="text-muted-foreground">
                                        Send inquiries to
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
                </aside>

                <!-- Detailed Terms Content -->
                <div class="lg:col-span-8 space-y-8">
                    <!-- Section 01: Agreement -->
                    <section
                        id="agreement"
                        class="scroll-mt-28 rounded-xl border border-border/80 bg-card p-6 sm:p-8 shadow-xs transition-colors reveal-on-scroll"
                    >
                        <header
                            class="flex items-start justify-between gap-4 pb-4 border-b border-border/50"
                        >
                            <div class="space-y-1">
                                <span
                                    class="inline-block text-xs font-mono font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Section 01
                                </span>
                                <h2
                                    class="text-xl sm:text-2xl font-semibold tracking-tight text-foreground"
                                >
                                    Agreement
                                </h2>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                type="button"
                                class="size-8 text-muted-foreground hover:text-foreground"
                                title="Copy link to this section"
                                @click="copySectionLink('agreement')"
                            >
                                <Check
                                    v-if="copiedSection === 'agreement'"
                                    class="size-4 text-primary"
                                />
                                <Hash v-else class="size-4" />
                            </Button>
                        </header>

                        <div
                            class="mt-5 space-y-4 text-sm leading-relaxed text-muted-foreground sm:text-base"
                        >
                            <p>
                                By using {{ appName }}, you agree to use the
                                site and application lawfully, responsibly, and
                                in a way that does not interfere with other
                                users or the service.
                            </p>
                            <div
                                class="rounded-lg border border-border/60 bg-muted/40 p-4 text-sm text-foreground/90 flex items-start gap-3"
                            >
                                <Info
                                    class="size-5 shrink-0 text-primary mt-0.5"
                                />
                                <p>
                                    Your continued use of any aspect of the
                                    platform constitutes acknowledgement and
                                    acceptance of these binding conditions.
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Section 02: Accounts and security -->
                    <section
                        id="accounts"
                        class="scroll-mt-28 rounded-xl border border-border/80 bg-card p-6 sm:p-8 shadow-xs transition-colors reveal-on-scroll"
                    >
                        <header
                            class="flex items-start justify-between gap-4 pb-4 border-b border-border/50"
                        >
                            <div class="space-y-1">
                                <span
                                    class="inline-block text-xs font-mono font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Section 02
                                </span>
                                <h2
                                    class="text-xl sm:text-2xl font-semibold tracking-tight text-foreground"
                                >
                                    Accounts and security
                                </h2>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                type="button"
                                class="size-8 text-muted-foreground hover:text-foreground"
                                title="Copy link to this section"
                                @click="copySectionLink('accounts')"
                            >
                                <Check
                                    v-if="copiedSection === 'accounts'"
                                    class="size-4 text-primary"
                                />
                                <Hash v-else class="size-4" />
                            </Button>
                        </header>

                        <div
                            class="mt-5 space-y-4 text-sm leading-relaxed text-muted-foreground sm:text-base"
                        >
                            <p>
                                Keep account credentials and authentication
                                devices secure. You are responsible for activity
                                performed through your account and should report
                                suspected unauthorized access promptly.
                            </p>

                            <!-- Security guidelines box -->
                            <div
                                class="rounded-lg border border-border/70 bg-background p-4 shadow-2xs space-y-2"
                            >
                                <div class="flex items-center gap-2">
                                    <KeyRound class="size-4 text-primary" />
                                    <span
                                        class="font-semibold text-foreground text-sm"
                                    >
                                        Account Custody Responsibilities
                                    </span>
                                </div>
                                <ul
                                    class="space-y-1.5 text-xs sm:text-sm text-muted-foreground pl-1"
                                >
                                    <li class="flex items-center gap-2">
                                        <CheckCircle2
                                            class="size-3.5 text-primary shrink-0"
                                        />
                                        <span
                                            >Use robust passwords or passkeys
                                            registered with your biometric
                                            authenticator.</span
                                        >
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <CheckCircle2
                                            class="size-3.5 text-primary shrink-0"
                                        />
                                        <span
                                            >Keep your registered recovery codes
                                            and email accounts protected.</span
                                        >
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <CheckCircle2
                                            class="size-3.5 text-primary shrink-0"
                                        />
                                        <span
                                            >Notify administrators immediately
                                            if device or token compromise is
                                            suspected.</span
                                        >
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <!-- Section 03: Acceptable use -->
                    <section
                        id="acceptable-use"
                        class="scroll-mt-28 rounded-xl border border-border/80 bg-card p-6 sm:p-8 shadow-xs transition-colors reveal-on-scroll"
                    >
                        <header
                            class="flex items-start justify-between gap-4 pb-4 border-b border-border/50"
                        >
                            <div class="space-y-1">
                                <span
                                    class="inline-block text-xs font-mono font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Section 03
                                </span>
                                <h2
                                    class="text-xl sm:text-2xl font-semibold tracking-tight text-foreground"
                                >
                                    Acceptable use
                                </h2>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                type="button"
                                class="size-8 text-muted-foreground hover:text-foreground"
                                title="Copy link to this section"
                                @click="copySectionLink('acceptable-use')"
                            >
                                <Check
                                    v-if="copiedSection === 'acceptable-use'"
                                    class="size-4 text-primary"
                                />
                                <Hash v-else class="size-4" />
                            </Button>
                        </header>

                        <div
                            class="mt-5 space-y-4 text-sm leading-relaxed text-muted-foreground sm:text-base"
                        >
                            <p>
                                Do not use the service to break the law, abuse
                                infrastructure, bypass security controls,
                                upload malicious code, or access data that does
                                not belong to you.
                            </p>

                            <!-- Prohibited activities warning card -->
                            <div
                                class="rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-xs sm:text-sm text-foreground/90 space-y-2"
                            >
                                <div class="flex items-center gap-2 text-destructive font-semibold">
                                    <AlertCircle class="size-4 shrink-0" />
                                    <span>Explicitly Prohibited Behaviors</span>
                                </div>
                                <ul class="space-y-1.5 text-muted-foreground pl-1">
                                    <li class="flex items-start gap-2">
                                        <span class="text-destructive font-bold">•</span>
                                        <span
                                            >Unauthorized automated scanning,
                                            fuzzing, or probing of infrastructure.</span
                                        >
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="text-destructive font-bold">•</span>
                                        <span
                                            >Attempting to harvest, intercept,
                                            or inspect data of other tenants or
                                            users.</span
                                        >
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="text-destructive font-bold">•</span>
                                        <span
                                            >Introducing worms, trojans, or
                                            malicious logic designed to
                                            destabilize services.</span
                                        >
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="text-destructive font-bold">•</span>
                                        <span
                                            >Reselling or abusing system compute
                                            resources outside agreed service
                                            tiers.</span
                                        >
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <!-- Section 04: Availability and changes -->
                    <section
                        id="availability"
                        class="scroll-mt-28 rounded-xl border border-border/80 bg-card p-6 sm:p-8 shadow-xs transition-colors reveal-on-scroll"
                    >
                        <header
                            class="flex items-start justify-between gap-4 pb-4 border-b border-border/50"
                        >
                            <div class="space-y-1">
                                <span
                                    class="inline-block text-xs font-mono font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Section 04
                                </span>
                                <h2
                                    class="text-xl sm:text-2xl font-semibold tracking-tight text-foreground"
                                >
                                    Availability and changes
                                </h2>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                type="button"
                                class="size-8 text-muted-foreground hover:text-foreground"
                                title="Copy link to this section"
                                @click="copySectionLink('availability')"
                            >
                                <Check
                                    v-if="copiedSection === 'availability'"
                                    class="size-4 text-primary"
                                />
                                <Hash v-else class="size-4" />
                            </Button>
                        </header>

                        <div
                            class="mt-5 space-y-4 text-sm leading-relaxed text-muted-foreground sm:text-base"
                        >
                            <p>
                                Features may change as the application evolves.
                                Maintenance, outages, or security work may
                                require temporary interruption. We may update
                                these terms when the service or its use changes.
                            </p>

                            <div
                                class="rounded-lg border border-border/60 bg-muted/30 p-4 text-xs sm:text-sm text-foreground/80 flex items-start gap-3"
                            >
                                <Layers class="size-4 text-primary mt-0.5 shrink-0" />
                                <div>
                                    <p class="font-medium text-foreground">
                                        Platform Improvements & Retainers
                                    </p>
                                    <p class="text-muted-foreground mt-0.5">
                                        We iteratively upgrade dependencies,
                                        harden security configurations, and
                                        introduce new features. When updates to
                                        these terms occur, the latest revision
                                        date is stamped prominently above.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Section 05: Contact -->
                    <section
                        id="contact"
                        class="scroll-mt-28 rounded-xl border border-border/80 bg-card p-6 sm:p-8 shadow-xs transition-colors reveal-on-scroll"
                    >
                        <header
                            class="flex items-start justify-between gap-4 pb-4 border-b border-border/50"
                        >
                            <div class="space-y-1">
                                <span
                                    class="inline-block text-xs font-mono font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Section 05
                                </span>
                                <h2
                                    class="text-xl sm:text-2xl font-semibold tracking-tight text-foreground"
                                >
                                    Contact
                                </h2>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                type="button"
                                class="size-8 text-muted-foreground hover:text-foreground"
                                title="Copy link to this section"
                                @click="copySectionLink('contact')"
                            >
                                <Check
                                    v-if="copiedSection === 'contact'"
                                    class="size-4 text-primary"
                                />
                                <Hash v-else class="size-4" />
                            </Button>
                        </header>

                        <div
                            class="mt-5 space-y-4 text-sm leading-relaxed text-muted-foreground sm:text-base"
                        >
                            <p>
                                Questions about these terms can be sent to
                                <a
                                    href="mailto:hello@valencekit.test"
                                    class="font-medium text-foreground underline underline-offset-4 hover:text-primary"
                                >
                                    hello@valencekit.test
                                </a>
                                . Replace this local address and add the
                                applicable business jurisdiction before
                                production deployment.
                            </p>
                        </div>
                    </section>

                    <!-- Bottom Navigation & Cross Link Card -->
                    <div
                        class="mt-12 rounded-xl border border-border/80 bg-card p-6 sm:p-8 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6"
                    >
                        <div class="space-y-1">
                            <span
                                class="text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                            >
                                Additional Legal Agreements
                            </span>
                            <h3 class="text-base font-semibold text-foreground">
                                Read our Privacy Policy
                            </h3>
                            <p class="text-xs sm:text-sm text-muted-foreground">
                                Learn how {{ appName }} handles data, cookies,
                                and account security.
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            <Link
                                :href="privacyPolicy()"
                                class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-4 text-xs sm:text-sm font-medium text-primary-foreground shadow-xs transition-colors hover:bg-primary/90"
                            >
                                <span>Privacy Policy</span>
                                <ArrowRight class="size-4" />
                            </Link>
                            <Button
                                variant="outline"
                                size="icon"
                                type="button"
                                class="size-9 rounded-md border-border text-muted-foreground hover:text-foreground"
                                title="Back to top"
                                @click="scrollToTop"
                            >
                                <ArrowUp class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <PublicFooter />
        <ScrollToTop />
    </div>
</template>
