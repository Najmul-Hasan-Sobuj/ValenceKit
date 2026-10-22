<script setup lang="ts">
import { computed } from 'vue';
import {
    ScanSearch,
    Layers,
    Zap,
    Clock,
    Sparkles,
    CheckCircle2,
    ArrowRight,
    ShieldCheck,
    Lock,
} from '@lucide/vue';

export interface PricingFeature {
    text: string;
    highlight?: boolean;
}

export interface PricingTier {
    id: string;
    number: string;
    name: string;
    phase: string;
    badge?: string;
    price: string;
    cadence: string;
    turnaround: string;
    warranty: string;
    engagement: string;
    description: string;
    featured?: boolean;
    features: (string | PricingFeature)[];
    ctaText: string;
    ctaHref?: string;
    contactSubject?: string;
    icon: unknown;
}

const props = withDefaults(
    defineProps<{
        tiers?: PricingTier[];
        eyebrow?: string;
        title?: string;
        description?: string;
        id?: string;
    }>(),
    {
        eyebrow: 'Transparent Investment',
        title: 'Simple, predictable pricing.',
        description:
            'Architectural precision applied to software engineering. Choose an emergency sprint, a complete custom build, or dedicated monthly infrastructure partnership.',
        id: 'pricing',
    },
);

const defaultTiers: PricingTier[] = [
    {
        id: 'audit-sprint',
        number: '01',
        name: 'Audit & Quick Fix',
        phase: 'IMMEDIATE DIAGNOSTICS & BUG FIXES',
        badge: 'Fast Turnaround',
        price: '$490',
        cadence: 'fixed sprint',
        turnaround: '1–3 Days',
        warranty: '14-Day Post-Fix',
        engagement: 'Fixed Scope',
        description:
            'Surgical root-cause bug isolation and immediate performance remediation for live Laravel applications experiencing crashes, HTTP 500 errors, or database bottlenecks.',
        featured: false,
        icon: ScanSearch,
        features: [
            'Root-cause exception isolation & bug resolution',
            'Database slow-query profiling & index tuning',
            'Linux server, PHP-FPM, & Cloudflare Full-Strict audit',
            'Documented code fixes with regression test coverage',
            '14-day post-fix stability warranty',
        ],
        ctaText: 'Book Audit Sprint',
        contactSubject: 'Inquiry: Audit & Bug Fix Sprint ($490)',
    },
    {
        id: 'custom-app',
        number: '02',
        name: 'Custom App & POS',
        phase: 'FULL-STACK BESPOKE ENGINEERING',
        badge: 'Most Popular',
        price: '$2,400',
        cadence: 'starting per project',
        turnaround: '4–8 Weeks',
        warranty: '30-Day Launch',
        engagement: 'Milestone Delivery',
        description:
            'End-to-end bespoke application engineering built with Laravel, Vue 3, and Inertia.js—tailored to your exact business workflow without third-party bloat.',
        featured: true,
        icon: Layers,
        features: [
            'Complete custom business logic & relational schemas',
            'Point of sale (POS), multi-branch inventory, or CRM',
            'Payment integration (SSLCommerz, bKash, or Stripe)',
            'Role-based permissions & background queue workers',
            'Weekly milestone staging links with Git tracking',
            '30-day post-launch warranty & staff training session',
        ],
        ctaText: 'Start Your Build',
        contactSubject: 'Inquiry: Custom Laravel App / POS Build ($2,400+)',
    },
    {
        id: 'retainer',
        number: '03',
        name: 'Dedicated Retainer',
        phase: 'ONGOING RELIABILITY & DEVOPS',
        badge: 'Ongoing Partnership',
        price: '$790',
        cadence: 'per month',
        turnaround: '2h SLA Response',
        warranty: '24/7 Monitoring',
        engagement: 'Monthly Retainer',
        description:
            'Proactive server management, continuous framework security upgrades, offsite backups, and priority developer hours for operational peace of mind.',
        featured: false,
        icon: Zap,
        features: [
            '20 hours/month dedicated development & improvements',
            'Routine Laravel framework & Composer security patches',
            'Automated offsite database backups with verified restores',
            '24/7 AWS, VPS, & uptime monitoring with instant alerts',
            'Direct Slack / WhatsApp channel with senior engineer',
        ],
        ctaText: 'Reserve Retainer Slot',
        contactSubject: 'Inquiry: Dedicated Monthly Retainer ($790/mo)',
    },
];

const activeTiers = computed(() => props.tiers || defaultTiers);

const defaultGuarantees = [
    {
        title: '14-Day Post-Launch Warranty',
        description: 'Free, immediate fixes for any edge cases discovered after project handoff.',
        icon: ShieldCheck,
    },
    {
        title: 'NDA Protected & 100% IP Ownership',
        description: 'You own all source code, git commits, licenses, and architecture diagrams outright.',
        icon: Lock,
    },
    {
        title: 'Direct Senior Engineer Access',
        description: 'No non-technical account managers or outsourced junior devs. You work directly with me.',
        icon: Zap,
    },
];
</script>

<script lang="ts">
export function createPricingSchema(tiers: PricingTier[], url?: string) {
    return {
        '@type': 'OfferCatalog',
        '@id': url ? `${url}#pricing` : undefined,
        name: 'Laravel Engineering & Consulting Tiers',
        itemListElement: tiers.map((tier) => ({
            '@type': 'Offer',
            name: tier.name,
            description: tier.description,
            price: tier.price.replace(/[^0-9]/g, ''),
            priceCurrency: 'USD',
            category: tier.cadence,
            availability: 'https://schema.org/InStock',
        })),
    };
}
</script>

<template>
    <section
        :id="id"
        class="w-full space-y-12 border-t border-border pt-16 lg:pt-24"
        aria-label="Pricing and engagement plans"
    >
        <!-- Section Header -->
        <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
            <div class="max-w-3xl space-y-4">
                <p
                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase font-mono"
                >
                    {{ eyebrow }}
                </p>
                <h2
                    class="text-3xl font-medium tracking-tight text-foreground md:text-5xl"
                >
                    {{ title }}
                </h2>
                <p
                    class="text-lg leading-relaxed font-normal text-muted-foreground lg:text-xl"
                >
                    {{ description }}
                </p>
            </div>
            <div class="shrink-0">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/40 px-3.5 py-1.5 text-xs font-medium text-muted-foreground font-mono"
                >
                    <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Available for Q4 Projects
                </span>
            </div>
        </div>

        <!-- FOUNDRY OFFICES STACKING CARDS CONTAINER -->
        <div class="relative flex flex-col gap-8 md:gap-12 pb-16 pt-4">
            <article
                v-for="(tier, index) in activeTiers"
                :key="tier.id"
                class="stack-card group relative flex min-h-[500px] flex-col overflow-hidden rounded-2xl border border-border/80 bg-[#FAFAFA] transition-colors duration-500 dark:bg-[#0c0c0e] lg:flex-row shadow-2xl"
                :class="[
                    tier.featured
                        ? 'border-primary/60 dark:border-primary/50'
                        : 'hover:border-foreground/30'
                ]"
                :style="{
                    position: 'sticky',
                    top: `${4.5 + index * 1.5}rem`,
                    transformOrigin: 'center top',
                    boxShadow: '0 -10px 40px rgba(0, 0, 0, 0.15)',
                    zIndex: 10 + index,
                }"
            >
                <!-- Subtle Blueprint Architectural Grid Pattern -->
                <div
                    class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,currentColor_1px,transparent_1px),linear-gradient(to_bottom,currentColor_1px,transparent_1px)] bg-[size:2rem_2rem] opacity-[0.03] text-foreground"
                    aria-hidden="true"
                />

                <!-- Giant Watermark Stroke Index Number (Foundry Offices Style) -->
                <span
                    aria-hidden="true"
                    class="pointer-events-none absolute -top-4 right-6 select-none font-display text-[6.5rem] font-bold leading-none text-transparent md:-top-7 md:right-10 md:text-[9.5rem] [-webkit-text-stroke:1px_rgba(0,0,0,0.08)] dark:[-webkit-text-stroke:1px_rgba(255,255,255,0.12)]"
                >
                    {{ tier.number }}
                </span>

                <!-- LEFT COLUMN: Main Information & Specifications Matrix -->
                <div
                    class="relative z-10 flex flex-1 flex-col justify-between p-6 sm:p-8 lg:p-10"
                >
                    <div>
                        <!-- Header Icon & Phase Metadata -->
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-full border border-border bg-background text-foreground transition-colors duration-500 group-hover:border-primary/60 group-hover:text-primary sm:size-11"
                            >
                                <component
                                    :is="tier.icon"
                                    class="size-5"
                                    aria-hidden="true"
                                />
                            </div>
                            <div>
                                <span
                                    class="font-mono text-[10px] font-semibold tracking-[0.2em] text-muted-foreground uppercase sm:text-xs"
                                >
                                    {{ tier.phase }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Heading & Tagline -->
                        <div class="mt-6 sm:mt-8">
                            <div class="flex items-center gap-3">
                                <h3
                                    class="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl lg:text-5xl"
                                >
                                    {{ tier.name }}
                                </h3>
                                <span
                                    v-if="tier.featured"
                                    class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-0.5 font-mono text-[11px] font-semibold tracking-wide text-primary border border-primary/20"
                                >
                                    <Sparkles class="size-3" aria-hidden="true" />
                                    Featured
                                </span>
                            </div>
                            <p
                                class="mt-3 max-w-xl text-sm leading-relaxed text-muted-foreground sm:text-base"
                            >
                                {{ tier.description }}
                            </p>
                        </div>
                    </div>

                    <!-- Bottom Metrics & Specifications Bar (3 Columns, divided) -->
                    <div class="mt-8 pt-6 sm:mt-10">
                        <div
                            class="grid grid-cols-3 divide-x divide-border border-y border-border"
                        >
                            <div class="py-3.5 pr-3 sm:py-4 sm:pr-4">
                                <div
                                    class="font-mono text-[9px] uppercase tracking-[0.18em] text-muted-foreground sm:text-[10px]"
                                >
                                    Engagement
                                </div>
                                <div
                                    class="mt-1 truncate text-xs font-medium text-foreground sm:text-sm"
                                >
                                    {{ tier.engagement }}
                                </div>
                            </div>
                            <div class="px-3 py-3.5 sm:px-4 sm:py-4">
                                <div
                                    class="font-mono text-[9px] uppercase tracking-[0.18em] text-muted-foreground sm:text-[10px]"
                                >
                                    Turnaround
                                </div>
                                <div
                                    class="mt-1 truncate text-xs font-medium text-foreground sm:text-sm"
                                >
                                    {{ tier.turnaround }}
                                </div>
                            </div>
                            <div class="py-3.5 pl-3 sm:py-4 sm:pl-4">
                                <div
                                    class="font-mono text-[9px] uppercase tracking-[0.18em] text-muted-foreground sm:text-[10px]"
                                >
                                    Warranty
                                </div>
                                <div
                                    class="mt-1 truncate text-xs font-medium text-foreground sm:text-sm"
                                >
                                    {{ tier.warranty }}
                                </div>
                            </div>
                        </div>

                        <!-- CTA Button (Foundry Offices Style Pill) -->
                        <div class="mt-6 flex items-center justify-between gap-4">
                            <a
                                :href="tier.ctaHref || ('mailto:hello@valencekit.test?subject=' + encodeURIComponent(tier.contactSubject || tier.name))"
                                class="inline-flex items-center gap-2.5 rounded-full border px-5 py-2.5 font-mono text-xs font-bold uppercase tracking-[0.18em] transition-all duration-300 sm:px-6 sm:py-3"
                                :class="[
                                    tier.featured
                                        ? 'border-primary bg-primary text-primary-foreground hover:bg-primary/90 shadow-sm'
                                        : 'border-border bg-background text-foreground hover:border-foreground hover:bg-muted'
                                ]"
                            >
                                <span>{{ tier.ctaText }}</span>
                                <ArrowRight
                                    class="size-3.5 transition-transform duration-300 group-hover:translate-x-1"
                                    aria-hidden="true"
                                />
                            </a>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Price Display & Deliverables Spec Sheet -->
                <div
                    class="relative z-10 flex w-full flex-col justify-between border-t border-border/80 bg-muted/30 p-6 sm:p-8 lg:w-5/12 lg:border-t-0 lg:border-l lg:p-10"
                >
                    <div>
                        <!-- Price Block -->
                        <div class="border-b border-border/60 pb-6">
                            <span
                                class="font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-muted-foreground"
                            >
                                Investment Scope
                            </span>
                            <div class="mt-2 flex items-baseline gap-2">
                                <span
                                    class="text-4xl font-semibold tracking-tight text-foreground sm:text-5xl lg:text-6xl"
                                >
                                    {{ tier.price }}
                                </span>
                                <span
                                    class="font-mono text-xs uppercase tracking-wider text-muted-foreground"
                                >
                                    / {{ tier.cadence }}
                                </span>
                            </div>
                        </div>

                        <!-- Deliverables Checklist -->
                        <div class="mt-6 space-y-3">
                            <p
                                class="font-mono text-[11px] font-semibold uppercase tracking-[0.18em] text-foreground"
                            >
                                Included Deliverables:
                            </p>
                            <ul class="space-y-2.5 text-sm">
                                <li
                                    v-for="(feature, fIdx) in tier.features"
                                    :key="fIdx"
                                    class="flex items-start gap-2.5"
                                >
                                    <CheckCircle2
                                        class="mt-0.5 size-4 shrink-0"
                                        :class="tier.featured ? 'text-primary' : 'text-foreground/70'"
                                        aria-hidden="true"
                                    />
                                    <span
                                        class="leading-relaxed"
                                        :class="typeof feature === 'object' && feature.highlight ? 'font-medium text-foreground' : 'text-muted-foreground'"
                                    >
                                        {{ typeof feature === 'string' ? feature : feature.text }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Micro Guarantee Note -->
                    <div class="mt-6 border-t border-border/60 pt-4 text-xs text-muted-foreground/80 flex items-center gap-2">
                        <Clock class="size-3.5 shrink-0" aria-hidden="true" />
                        <span>Direct senior full-stack engineer commitment.</span>
                    </div>
                </div>
            </article>
        </div>

        <!-- Trust & Guarantees Sub-Grid -->
        <div
            class="grid grid-cols-1 gap-6 rounded-2xl border border-border bg-muted/20 p-6 sm:grid-cols-3 sm:p-8"
        >
            <div
                v-for="guarantee in defaultGuarantees"
                :key="guarantee.title"
                class="flex items-start gap-3.5"
            >
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-background text-foreground shadow-2xs"
                >
                    <component
                        :is="guarantee.icon"
                        class="size-4.5 text-primary"
                        aria-hidden="true"
                    />
                </div>
                <div class="space-y-1">
                    <h4 class="text-sm font-medium text-foreground">
                        {{ guarantee.title }}
                    </h4>
                    <p class="text-xs leading-relaxed text-muted-foreground">
                        {{ guarantee.description }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>
