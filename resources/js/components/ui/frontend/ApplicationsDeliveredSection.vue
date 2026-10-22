<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import {
    ShoppingCart,
    Boxes,
    Store,
    Users,
    ArrowUpRight,
} from '@lucide/vue';

export interface DomainCategory {
    number: string;
    phase: string;
    title: string;
    tagline: string;
    deliverables: string;
    platforms: string[];
    icon: unknown;
}

const props = withDefaults(
    defineProps<{
        domains?: DomainCategory[];
        eyebrow?: string;
        title?: string;
        description?: string;
        id?: string;
    }>(),
    {
        eyebrow: '03 / Project Archetypes',
        title: 'Applications Delivered.',
        description: 'From Concept to Handover · Proven Full-Stack Solutions',
        id: 'applications-delivered',
    },
);

const defaultDomains: DomainCategory[] = [
    {
        number: '01',
        phase: 'PHASE 01 · RETAIL & WHOLESALE',
        title: 'Point of Sale (POS)',
        tagline: 'High-speed checkout & counter billing',
        deliverables:
            'Barcode scanner optimization, thermal receipt printing, multi-counter cash drawers, and daily sales reconciliation shift reports.',
        platforms: [
            'Supermarkets & Groceries',
            'Fashion & Retail Boutiques',
            'Electronics & Hardware',
            'Pharmacies & Medical Stores',
        ],
        icon: ShoppingCart,
    },
    {
        number: '02',
        phase: 'PHASE 02 · SUPPLY CHAIN',
        title: 'Inventory & Warehousing',
        tagline: 'Multi-branch stock & supply tracking',
        deliverables:
            'Multi-branch stock management with inter-branch transfers, live batch tracking, low-stock threshold triggers, and automated supplier purchase orders.',
        platforms: [
            'Central Distribution Hubs',
            'Multi-Branch Warehouses',
            'Wholesale Traders',
            'Raw Material Inventories',
        ],
        icon: Boxes,
    },
    {
        number: '03',
        phase: 'PHASE 03 · DIGITAL COMMERCE',
        title: 'E-Commerce Platforms',
        tagline: 'Direct-to-consumer & B2B storefronts',
        deliverables:
            'Custom shopping carts, SKU product variations, coupon discount engines, order status SMS tracking, and SSLCommerz / bKash gateway checkout.',
        platforms: [
            'Direct-to-Consumer Brands',
            'B2B Wholesale Portals',
            'Multi-Category Shops',
            'Digital Product Downloads',
        ],
        icon: Store,
    },
    {
        number: '04',
        phase: 'PHASE 04 · OPERATIONS & CRM',
        title: 'CRM & Custom Management',
        tagline: 'Customer relationships & internal pipelines',
        deliverables:
            'Customer lead tracking, automated PDF billing, deal pipelines, client interaction logs, and role-based staff access permissions.',
        platforms: [
            'Service & Tech Agencies',
            'Real Estate Deal Pipelines',
            'Consultancy Portals',
            'Enterprise Memberships',
        ],
        icon: Users,
    },
];

const containerRef = ref<HTMLElement | null>(null);
const trackRef = ref<HTMLElement | null>(null);
const scrollProgress = ref(0);
const translateX = ref(0);
let ticking = false;

const updateMetrics = () => {
    if (!containerRef.value || !trackRef.value) return;

    if (window.innerWidth < 768) {
        scrollProgress.value = 0;
        translateX.value = 0;
        ticking = false;
        return;
    }

    const rect = containerRef.value.getBoundingClientRect();
    const totalDist = containerRef.value.offsetHeight - window.innerHeight;
    if (totalDist <= 0) {
        ticking = false;
        return;
    }

    const current = -rect.top;
    const rawProgress = Math.max(0, Math.min(1, current / totalDist));
    scrollProgress.value = rawProgress;

    // Calculate max translation based on last card's actual geometry
    const children = trackRef.value.children;
    const lastCard = children[children.length - 1] as HTMLElement;
    // Use the actual visible container width where track lives
    const containerWidth = containerRef.value.clientWidth || window.innerWidth;

    if (lastCard) {
        // Safe right margin so Card 04 has generous breathing room and is 100% visible
        const endMargin = 80;
        const totalContentWidth = lastCard.offsetLeft + lastCard.offsetWidth;
        const maxTranslate = Math.max(0, totalContentWidth + endMargin - containerWidth);

        // Map progress: 0.02 to 0.85 scrubs the cards; 0.85 to 1.0 rests with last card fully stationary
        const scrubProgress = Math.max(0, Math.min(1, (rawProgress - 0.02) / 0.83));
        translateX.value = scrubProgress * maxTranslate;
    } else {
        const trackWidth = trackRef.value.scrollWidth;
        const maxTranslate = Math.max(0, trackWidth - containerWidth + 140);
        translateX.value = rawProgress * maxTranslate;
    }

    ticking = false;
};

const handleScroll = () => {
    if (!ticking) {
        window.requestAnimationFrame(updateMetrics);
        ticking = true;
    }
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('resize', handleScroll);
    updateMetrics();
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
    window.removeEventListener('resize', handleScroll);
});
</script>

<template>
    <section
        :id="id"
        ref="containerRef"
        class="w-full relative border-t border-border pt-16 md:pt-0 md:h-[180vh]"
        aria-label="Applications Delivered and Project Archetypes"
    >
        <!-- MOBILE VIEW (Clean vertical timeline with glowing track nodes) -->
        <div class="md:hidden px-4 py-8 space-y-12">
            <div>
                <p class="font-mono text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                    {{ eyebrow }}
                </p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-foreground font-display">
                    {{ title }}
                </h2>
                <p class="mt-2 font-mono text-xs text-muted-foreground uppercase tracking-widest">
                    {{ description }}
                </p>
            </div>

            <!-- Vertical Interactive Timeline -->
            <ol class="relative space-y-10 border-l-2 border-border/80 pl-6 ml-2">
                <li
                    v-for="(domain, idx) in (domains || defaultDomains)"
                    :key="domain.number"
                    class="relative space-y-3"
                >
                    <!-- Pulsing node -->
                    <span
                        class="absolute -left-[31px] top-1.5 size-3 rounded-full border-2 border-background bg-primary ring-4 ring-primary/20"
                        aria-hidden="true"
                    />

                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs font-bold text-primary">
                            {{ domain.number }}
                        </span>
                        <span class="font-mono text-[10px] uppercase tracking-wider text-muted-foreground">
                            {{ domain.phase }}
                        </span>
                    </div>

                    <h3 class="text-xl font-display font-semibold tracking-tight text-foreground">
                        {{ domain.title }}
                    </h3>

                    <p class="text-xs font-mono text-muted-foreground">
                        {{ domain.tagline }}
                    </p>

                    <p class="text-sm leading-relaxed text-muted-foreground">
                        {{ domain.deliverables }}
                    </p>

                    <!-- Platforms Badges -->
                    <div class="flex flex-wrap gap-1.5 pt-2">
                        <span
                            v-for="platform in domain.platforms"
                            :key="platform"
                            class="inline-flex items-center rounded-md border border-border bg-muted/40 px-2 py-0.5 text-[11px] text-foreground"
                        >
                            {{ platform }}
                        </span>
                    </div>
                </li>
            </ol>
        </div>

        <!-- DESKTOP VIEW (Sticky pinned horizontal scrub track with timeline line) -->
        <div
            class="hidden md:flex sticky top-0 h-screen w-full overflow-hidden flex-col justify-center select-none"
        >
            <!-- Top Header Row -->
            <div class="w-full pb-8 flex items-end justify-between border-b border-border/60">
                <div>
                    <p class="font-mono text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                        {{ eyebrow }}
                    </p>
                    <h2 class="mt-1 text-4xl lg:text-5xl font-display font-semibold tracking-tight text-foreground">
                        {{ title }}
                    </h2>
                    <p class="mt-1 font-mono text-xs text-muted-foreground tracking-wider uppercase">
                        {{ description }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="font-mono text-xs text-muted-foreground uppercase tracking-widest">
                        Scroll to explore
                    </span>
                    <span class="inline-flex size-2 rounded-full bg-primary animate-ping" />
                </div>
            </div>

            <!-- Horizontal Scrub Track Container -->
            <div class="relative w-full overflow-hidden py-10">
                <!-- Central Timeline Line running across the screen -->
                <div class="pointer-events-none absolute left-0 right-0 top-1/2 -translate-y-1/2 z-0 h-px bg-border">
                    <!-- Progress Line that expands as you scroll -->
                    <div
                        class="h-px bg-primary origin-left"
                        :style="{
                            transform: `scaleX(${scrollProgress})`,
                            transformOrigin: '0% 50%',
                        }"
                    />
                </div>

                <!-- Moving Track with Trailing Space to prevent cut-off -->
                <div
                    ref="trackRef"
                    class="flex items-center gap-10 pr-24 lg:pr-36 will-change-transform"
                    :style="{
                        transform: `translateX(-${translateX}px)`,
                    }"
                >
                    <!-- Intro Card / Overview -->
                    <div
                        class="relative flex h-[380px] w-[320px] shrink-0 flex-col justify-between rounded-2xl border border-border/80 bg-card/90 p-8 shadow-xl backdrop-blur-md"
                    >
                        <div>
                            <span class="font-mono text-xs font-bold uppercase tracking-[0.2em] text-primary">
                                Architecture
                            </span>
                            <h3 class="mt-3 text-3xl font-display font-semibold text-foreground tracking-tight">
                                Enterprise Systems
                            </h3>
                            <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                                Full-lifecycle applications built for mission-critical operations, retail transactions, and real-time inventory control.
                            </p>
                        </div>
                        <div class="pt-4 border-t border-border/60">
                            <span class="font-mono text-[11px] uppercase tracking-wider text-muted-foreground">
                                4 Specialized Domains →
                            </span>
                        </div>
                    </div>

                    <!-- Domain Cards along Timeline Track -->
                    <div
                        v-for="(domain, idx) in (domains || defaultDomains)"
                        :key="domain.number"
                        class="relative flex h-[400px] w-[420px] sm:w-[440px] max-w-[85vw] shrink-0 flex-col justify-between rounded-2xl border border-border/80 bg-card p-7 lg:p-8 shadow-xl transition-all duration-300 hover:border-primary/60 hover:shadow-2xl group"
                        :class="[
                            idx % 2 === 0 ? '-translate-y-4' : 'translate-y-4'
                        ]"
                    >
                        <!-- Node connecting to central timeline -->
                        <div
                            class="pointer-events-none absolute left-10 size-3 rounded-full border-2 border-background bg-primary shadow-sm"
                            :class="[
                                idx % 2 === 0 ? '-bottom-1.5' : '-top-1.5'
                            ]"
                        />

                        <!-- Card Top: Watermark Number & Icon -->
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-xs font-bold uppercase tracking-[0.2em] text-primary">
                                    {{ domain.phase }}
                                </span>
                                <span class="font-display text-4xl font-bold text-foreground/15 group-hover:text-primary/30 transition-colors">
                                    {{ domain.number }}
                                </span>
                            </div>

                            <div class="mt-4 flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-xl border border-border bg-muted/30 text-foreground group-hover:border-primary/50 group-hover:text-primary transition-colors">
                                    <component :is="domain.icon" class="size-5" aria-hidden="true" />
                                </div>
                                <div>
                                    <h3 class="text-2xl font-display font-semibold tracking-tight text-foreground">
                                        {{ domain.title }}
                                    </h3>
                                    <p class="font-mono text-[11px] text-muted-foreground">
                                        {{ domain.tagline }}
                                    </p>
                                </div>
                            </div>

                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                {{ domain.deliverables }}
                            </p>
                        </div>

                        <!-- Card Bottom: Supported Platforms Pills -->
                        <div class="pt-4 border-t border-border/60">
                            <span class="block font-mono text-[10px] uppercase tracking-wider text-muted-foreground mb-2">
                                Production Deployments:
                            </span>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="platform in domain.platforms"
                                    :key="platform"
                                    class="inline-flex items-center rounded-md border border-border bg-muted/40 px-2.5 py-1 text-xs text-foreground font-medium transition-colors hover:bg-primary/10 hover:border-primary/30"
                                >
                                    {{ platform }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
