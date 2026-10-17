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
    ShieldCheck,
    Lock,
    EyeOff,
    UserCheck,
    Clock,
    BookOpen,
    FileText,
    Scale,
    Hash,
    Check,
    ArrowUp,
    ArrowRight,
    Mail,
    Server,
    Cookie,
    Info,
    RefreshCw,
    Shield,
    CheckCircle2,
    KeyRound,
} from '@lucide/vue';

const page = usePage<{ name?: string; appUrl: string }>();
const appName = page.props.name || 'ValenceKit';
const appUrl = page.props.appUrl.replace(/\/$/, '');
const pageTitle = 'Privacy Policy';
const pageDescription = `How ${appName} handles information in the public site and application.`;
const pageUrl = `${appUrl}/privacy-policy`;

const breadcrumbs = [
    { title: 'Home', href: home().url },
    { title: 'Legal', href: privacyPolicy().url },
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
        icon: ShieldCheck,
        title: 'Encrypted Credentials',
        description:
            'Passwords and passkeys are encrypted with modern security standards. Passwords are never stored in plain text.',
    },
    {
        icon: EyeOff,
        title: 'Zero Tracking',
        description:
            'No marketing trackers, third-party pixel cookies, or unauthorized data sharing across the platform.',
    },
    {
        icon: Server,
        title: 'Isolated Infrastructure',
        description:
            'Infrastructure, database, and email providers only process information when specific deployments require them.',
    },
    {
        icon: UserCheck,
        title: 'Your Data Rights',
        description:
            'Full autonomy to review, correct, or request deletion of your account information anytime.',
    },
];

// Navigation sections
const sections = [
    {
        id: 'scope',
        number: '01',
        title: 'What this policy covers',
        icon: Info,
    },
    {
        id: 'information',
        number: '02',
        title: 'Information we handle',
        icon: Lock,
    },
    {
        id: 'usage',
        number: '03',
        title: 'How information is used',
        icon: Server,
    },
    {
        id: 'cookies',
        number: '04',
        title: 'Cookies and service providers',
        icon: Cookie,
    },
    {
        id: 'choices',
        number: '05',
        title: 'Your choices & data rights',
        icon: UserCheck,
    },
    {
        id: 'changes',
        number: '06',
        title: 'Policy revisions & changes',
        icon: RefreshCw,
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
                        <ShieldCheck class="size-3.5 text-primary" />
                        <span>Legal & Privacy</span>
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
                            {{ pageDescription }} We are committed to
                            transparency, rigorous credential protection, and
                            respecting your privacy at every level.
                        </p>
                    </div>

                    <!-- Document switcher & actions -->
                    <div class="flex flex-wrap items-center gap-2.5 pt-2 lg:pt-0">
                        <div
                            class="inline-flex rounded-lg border border-border bg-muted/40 p-1"
                        >
                            <span
                                class="inline-flex items-center gap-1.5 rounded-md bg-background px-3 py-1.5 text-xs font-medium text-foreground shadow-xs"
                            >
                                <FileText class="size-3.5 text-primary" />
                                Privacy Policy
                            </span>
                            <Link
                                :href="terms()"
                                class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                            >
                                <Scale class="size-3.5" />
                                Terms of Service
                            </Link>
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
                            aria-label="Privacy Policy sections"
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
                                        Privacy inquiries?
                                    </p>
                                    <p class="text-muted-foreground">
                                        Direct your data questions to
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

                <!-- Detailed Policy Content -->
                <div class="lg:col-span-8 space-y-8">
                    <!-- Section 01: What this policy covers -->
                    <section
                        id="scope"
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
                                    What this policy covers
                                </h2>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                type="button"
                                class="size-8 text-muted-foreground hover:text-foreground"
                                title="Copy link to this section"
                                @click="copySectionLink('scope')"
                            >
                                <Check
                                    v-if="copiedSection === 'scope'"
                                    class="size-4 text-primary"
                                />
                                <Hash v-else class="size-4" />
                            </Button>
                        </header>

                        <div
                            class="mt-5 space-y-4 text-sm leading-relaxed text-muted-foreground sm:text-base"
                        >
                            <p>
                                This policy explains how {{ appName }} handles
                                information when you browse this site or use its
                                authenticated application features.
                            </p>
                            <div
                                class="rounded-lg border border-border/60 bg-muted/40 p-4 text-sm text-foreground/90 flex items-start gap-3"
                            >
                                <Info
                                    class="size-5 shrink-0 text-primary mt-0.5"
                                />
                                <p>
                                    Whether interacting with public marketing
                                    pages or managing private application
                                    data, our engineering architecture is
                                    designed to minimize collection and preserve
                                    strict boundaries.
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Section 02: Information we handle -->
                    <section
                        id="information"
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
                                    Information we handle
                                </h2>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                type="button"
                                class="size-8 text-muted-foreground hover:text-foreground"
                                title="Copy link to this section"
                                @click="copySectionLink('information')"
                            >
                                <Check
                                    v-if="copiedSection === 'information'"
                                    class="size-4 text-primary"
                                />
                                <Hash v-else class="size-4" />
                            </Button>
                        </header>

                        <div
                            class="mt-5 space-y-4 text-sm leading-relaxed text-muted-foreground sm:text-base"
                        >
                            <p>
                                Account features may handle information such as
                                your name, email address, password credentials,
                                session data, security settings, and passkey
                                data.
                            </p>

                            <!-- Security highlight box -->
                            <div
                                class="rounded-lg border border-border/70 bg-background p-4 shadow-2xs space-y-2"
                            >
                                <div class="flex items-center gap-2">
                                    <KeyRound class="size-4 text-primary" />
                                    <span
                                        class="font-semibold text-foreground text-sm"
                                    >
                                        Credential Handling Standard
                                    </span>
                                </div>
                                <p class="text-xs sm:text-sm text-muted-foreground">
                                    Passwords are handled as credentials and
                                    should never be stored in plain text.
                                    Industry-standard hashing and FIDO2/WebAuthn
                                    public-key cryptographic standards protect
                                    passkeys and credentials.
                                </p>
                            </div>

                            <p>
                                The public contact form currently validates
                                input in your browser and does not send or store
                                a submission on the server.
                            </p>
                        </div>
                    </section>

                    <!-- Section 03: How information is used -->
                    <section
                        id="usage"
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
                                    How information is used
                                </h2>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                type="button"
                                class="size-8 text-muted-foreground hover:text-foreground"
                                title="Copy link to this section"
                                @click="copySectionLink('usage')"
                            >
                                <Check
                                    v-if="copiedSection === 'usage'"
                                    class="size-4 text-primary"
                                />
                                <Hash v-else class="size-4" />
                            </Button>
                        </header>

                        <div
                            class="mt-5 space-y-4 text-sm leading-relaxed text-muted-foreground sm:text-base"
                        >
                            <p>
                                Information is used to provide requested account
                                features, authenticate users, protect the
                                service, maintain security logs, and respond to
                                support requests when a supported communication
                                channel is used.
                            </p>
                            <ul class="space-y-2 text-sm text-foreground/90 pl-1">
                                <li class="flex items-center gap-2.5">
                                    <CheckCircle2
                                        class="size-4 text-primary shrink-0"
                                    />
                                    <span
                                        >Authenticate authorized accounts and
                                        verify sessions.</span
                                    >
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <CheckCircle2
                                        class="size-4 text-primary shrink-0"
                                    />
                                    <span
                                        >Protect infrastructure against brute
                                        force or unauthorized access.</span
                                    >
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <CheckCircle2
                                        class="size-4 text-primary shrink-0"
                                    />
                                    <span
                                        >Maintain audit-safe security logs for
                                        session integrity.</span
                                    >
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <CheckCircle2
                                        class="size-4 text-primary shrink-0"
                                    />
                                    <span
                                        >Respond promptly to user support and
                                        system inquiries.</span
                                    >
                                </li>
                            </ul>
                        </div>
                    </section>

                    <!-- Section 04: Cookies and service providers -->
                    <section
                        id="cookies"
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
                                    Cookies and service providers
                                </h2>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                type="button"
                                class="size-8 text-muted-foreground hover:text-foreground"
                                title="Copy link to this section"
                                @click="copySectionLink('cookies')"
                            >
                                <Check
                                    v-if="copiedSection === 'cookies'"
                                    class="size-4 text-primary"
                                />
                                <Hash v-else class="size-4" />
                            </Button>
                        </header>

                        <div
                            class="mt-5 space-y-4 text-sm leading-relaxed text-muted-foreground sm:text-base"
                        >
                            <p>
                                The application may use essential cookies for
                                sessions, security, and user preferences.
                                Hosting, infrastructure, email, or payment
                                providers may process information only when
                                those integrations are enabled for a deployment.
                            </p>

                            <div
                                class="rounded-lg border border-border/60 bg-muted/30 p-4 text-xs sm:text-sm text-foreground/80 flex items-start gap-3"
                            >
                                <Cookie class="size-4 text-primary mt-0.5 shrink-0" />
                                <div>
                                    <p class="font-medium text-foreground">
                                        Essential vs Third-Party Cookies
                                    </p>
                                    <p class="text-muted-foreground mt-0.5">
                                        We do not utilize invasive advertising
                                        trackers. Essential cookies allow the
                                        session state, CSRF protection, and
                                        light/dark theme preference to persist
                                        reliably.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Section 05: Your choices -->
                    <section
                        id="choices"
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
                                    Your choices
                                </h2>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                type="button"
                                class="size-8 text-muted-foreground hover:text-foreground"
                                title="Copy link to this section"
                                @click="copySectionLink('choices')"
                            >
                                <Check
                                    v-if="copiedSection === 'choices'"
                                    class="size-4 text-primary"
                                />
                                <Hash v-else class="size-4" />
                            </Button>
                        </header>

                        <div
                            class="mt-5 space-y-4 text-sm leading-relaxed text-muted-foreground sm:text-base"
                        >
                            <p>
                                You may request access, correction, or deletion
                                of account information where applicable. Contact
                                <a
                                    href="mailto:hello@valencekit.test"
                                    class="font-medium text-foreground underline underline-offset-4 hover:text-primary"
                                >
                                    hello@valencekit.test
                                </a>
                                for privacy questions. Replace this local
                                address before production deployment.
                            </p>
                        </div>
                    </section>

                    <!-- Section 06: Changes -->
                    <section
                        id="changes"
                        class="scroll-mt-28 rounded-xl border border-border/80 bg-card p-6 sm:p-8 shadow-xs transition-colors reveal-on-scroll"
                    >
                        <header
                            class="flex items-start justify-between gap-4 pb-4 border-b border-border/50"
                        >
                            <div class="space-y-1">
                                <span
                                    class="inline-block text-xs font-mono font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Section 06
                                </span>
                                <h2
                                    class="text-xl sm:text-2xl font-semibold tracking-tight text-foreground"
                                >
                                    Changes
                                </h2>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                type="button"
                                class="size-8 text-muted-foreground hover:text-foreground"
                                title="Copy link to this section"
                                @click="copySectionLink('changes')"
                            >
                                <Check
                                    v-if="copiedSection === 'changes'"
                                    class="size-4 text-primary"
                                />
                                <Hash v-else class="size-4" />
                            </Button>
                        </header>

                        <div
                            class="mt-5 space-y-4 text-sm leading-relaxed text-muted-foreground sm:text-base"
                        >
                            <p>
                                This policy may be updated when the
                                application's data practices change. The latest
                                version will be published on this page.
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
                                Read our Terms of Service
                            </h3>
                            <p class="text-xs sm:text-sm text-muted-foreground">
                                Understand rights, acceptable use, and account
                                guidelines for {{ appName }}.
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            <Link
                                :href="terms()"
                                class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-4 text-xs sm:text-sm font-medium text-primary-foreground shadow-xs transition-colors hover:bg-primary/90"
                            >
                                <span>Terms of Service</span>
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
