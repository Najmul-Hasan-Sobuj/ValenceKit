<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ref, reactive, onMounted } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { dashboard, home, login, register, services } from '@/routes';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AppHead from '@/components/AppHead.vue';
import PublicFooter from '@/components/PublicFooter.vue';
import StructuredData from '@/components/StructuredData.vue';
import FaqSection, { createFaqSchema } from '@/components/FaqSection.vue';
import { Button } from '@/components/ui/button';
import {
    Sun,
    Moon,
    ArrowRight,
    CheckCircle2,
    Plus,
    X,
    ShieldCheck,
    Mail,
    ScanSearch,
    Layers,
    ArrowRightLeft,
    Zap,
    Code,
    Server,
    CreditCard,
    Database,
    Cpu,
    ExternalLink,
} from '@lucide/vue';

const page = usePage<{ name?: string; appUrl: string }>();
const appName = page.props.name || 'ValenceKit';
const appUrl = page.props.appUrl.replace(/\/$/, '');
const pageTitle = 'Laravel Services: Web Apps, POS & CRM';
const pageDescription =
    'Full-stack Laravel development services: custom web apps, POS & inventory software, CRM systems, AWS/VPS server administration, and payment gateways.';

const { resolvedAppearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};

// Hero Jump Nav Data with SVG vector icons matching the 4 core pillars
const heroNavItems = [
    {
        id: 'audit',
        name: 'Audit',
        phase: 'Phase 01',
        icon: ScanSearch,
        tagline: 'Code audits, bug fixes, and server diagnostics.',
    },
    {
        id: 'design',
        name: 'Design',
        phase: 'Phase 02',
        icon: Layers,
        tagline: 'Clean web design and relational database schemas.',
    },
    {
        id: 'migrate',
        name: 'Migrate',
        phase: 'Phase 03',
        icon: ArrowRightLeft,
        tagline: 'Custom Laravel web apps, POS, CRM, and payments.',
    },
    {
        id: 'run',
        name: 'Run',
        phase: 'Phase 04',
        icon: Zap,
        tagline: 'Cloudflare, cPanel, AWS, VPS, and maintenance.',
    },
];

const isHeroEntered = ref(false);

onMounted(() => {
    if (typeof window !== 'undefined') {
        if (
            window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
            window.innerWidth < 1024
        ) {
            isHeroEntered.value = true;
        } else {
            setTimeout(() => {
                isHeroEntered.value = true;
            }, 300);
        }
    }
});

const scrollToSection = (id: string) => {
    const el = document.getElementById(id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        if (typeof window !== 'undefined') {
            window.history.pushState(null, '', `#${id}`);
        }
    }
};

// Accordion states for each phase
const activeAccordion = reactive<Record<string, number | null>>({
    audit: 0,
    design: 0,
    migrate: 0,
    run: 0,
});

const toggleAccordion = (phaseKey: string, index: number) => {
    activeAccordion[phaseKey] =
        activeAccordion[phaseKey] === index ? null : index;
};

// Phase 01: Audit Content Data (Bug Fixes, Code & Server Diagnostics)
const auditItems = [
    {
        letter: 'A',
        title: 'Bug Fixes & Production Error Isolation',
        summary:
            'Trace runtime exceptions, broken workflows, and unexpected HTTP 500 errors to their root cause.',
        details: [
            'Inspect Laravel error logs, queue failure tables, and Sentry reports to isolate bugs under production workloads',
            'Debug broken authentication flows, session drops, CSRF token mismatches, and password reset issues',
            'Resolve unhandled null pointer exceptions and race conditions in financial or checkout transactions',
            'Deliver documented bug fixes accompanied by regression tests to prevent identical issues in future releases',
        ],
    },
    {
        letter: 'B',
        title: 'Database Query Profiling & Performance Tuning',
        summary:
            'Eliminate N+1 queries, add strategic indexes, and optimize slow MySQL or PostgreSQL operations.',
        details: [
            'Profile slow queries using Laravel Telescope, Debugbar, and MySQL slow query logs under high record counts',
            'Add compound indexes on frequent WHERE, JOIN, and ORDER BY columns to eliminate full table scans',
            'Refactor heavy Eloquent queries using chunking, lazy collections, and targeted eager-loading',
            'Configure Redis caching layers for expensive read operations and frequently accessed application settings',
        ],
    },
    {
        letter: 'C',
        title: 'Server Configuration & Security Inspection',
        summary:
            'Review server permissions, SSL certificates, firewall rules, and PHP-FPM pool parameters.',
        details: [
            'Audit Linux file permissions, SSH key authorization, open ports, and UFW firewall configurations',
            'Tune PHP-FPM process managers (pm.max_children, memory limits) to prevent server memory exhaustion',
            'Verify Cloudflare SSL/TLS encryption (Full Strict), DNS records, and DDoS mitigation rules',
            'Check for exposed environment variables, outdated Composer packages, and SQL injection risks',
        ],
    },
    {
        letter: 'D',
        title: 'Legacy Codebase & Architecture Review',
        summary:
            'Assess technical debt, maintainability, and upgrade paths across older Laravel or raw PHP codebases.',
        details: [
            'Evaluate legacy business logic against current Laravel conventions, PHP 8 type safety, and PSR standards',
            'Catalog abandoned Composer dependencies and replace them with maintained, modern alternatives',
            'Separate tangled controller actions into dedicated service classes, actions, and form requests',
            'Provide a realistic modernization roadmap with clear time estimates and phased rollout priorities',
        ],
    },
];

// Phase 02: Design Content Data (Web Design & System Architecture)
const designItems = [
    {
        letter: 'A',
        title: 'Responsive Web & Interface Design',
        summary:
            'Clean, accessible interfaces built with Tailwind CSS, Vue 3, and modern typography.',
        details: [
            'Mobile-first layouts structured for high readability across smartphones, tablets, and desktop displays',
            'Consistent design tokens for color scales, typography hierarchies, interactive states, and spacing',
            'Clean dashboard interfaces with accessible data tables, search filters, and actionable status cards',
            'Zero layout shift rendering and fluid transitions that help users complete tasks quickly',
        ],
    },
    {
        letter: 'B',
        title: 'Relational Database Schema Modeling',
        summary:
            'Normalized database structures built specifically for transactional business applications.',
        details: [
            'Architect relational schemas for inventory items, multi-tier categories, customer accounts, and order ledgers',
            'Enforce foreign key constraints, cascading rules, and soft-delete behaviors to preserve data integrity',
            'Implement double-entry transaction ledgers for accounting balances, customer credits, and POS shifts',
            'Structure polymorphic relationships and pivot tables for multi-attribute product catalogs',
        ],
    },
    {
        letter: 'C',
        title: 'POS & Inventory Workflow Architecture',
        summary:
            'Frictionless operational flows designed for retail counters, stockrooms, and warehouses.',
        details: [
            'Design cashier checkout screens optimized for barcode scanner inputs and keyboard shortcuts',
            'Map multi-warehouse stock transfer steps, approval workflows, and purchase order lifecycles',
            'Wireframe real-time low-stock alert dashboards and automated supplier reorder prompts',
            'Structure payment dialogs supporting split payments across cash, bKash, cards, and store credits',
        ],
    },
];

// Phase 03: Migrate & Build Content Data (Web Apps, POS, Inventory, E-commerce, CRM, CMS & Payments)
const migrateItems = [
    {
        letter: 'A',
        title: 'Custom Laravel Web Application Development',
        summary:
            'Full-stack web applications built with Laravel, Inertia.js, Vue 3, and modern REST APIs.',
        details: [
            'Custom business logic tailored to your exact operational requirements without third-party bloat',
            'Single Page Application navigation speed combined with server-side security using Inertia.js',
            'Role-based access control (RBAC) with granular permissions for administrators, staff, and customers',
            'Background job processing via Redis queues for transactional emails, PDF generation, and data exports',
        ],
    },
    {
        letter: 'B',
        title: 'Point of Sale (POS) & Inventory Systems',
        summary:
            'Real-time stock tracking, multi-branch inventory control, and fast cashier checkout billing.',
        details: [
            'Fast checkout counter interfaces supporting barcode scanner input and thermal receipt printing',
            'Multi-branch stock management with inter-branch transfers and live stock level updates',
            'Batch tracking, expiration date warnings, and automated low-stock notification triggers',
            'Daily register opening balances, cash reconciliation, shift closing reports, and sales summaries',
        ],
    },
    {
        letter: 'C',
        title: 'E-Commerce Platforms & Custom CRM Software',
        summary:
            'Custom online storefronts, customer relationship pipelines, and automated order fulfillment.',
        details: [
            'Custom shopping cart and checkout flows with tax calculations, coupon codes, and shipping rules',
            'Product variation management (size, color, SKU) with individual inventory tracking per variant',
            'Lead tracking, sales pipelines, customer interaction logs, and automated follow-up reminders',
            'Automated invoice generation, PDF packing slips, and order status updates via SMS and email',
        ],
    },
    {
        letter: 'D',
        title: 'Custom Content Management Systems (CMS)',
        summary:
            'Lightweight, secure content management systems built without WordPress vulnerabilities.',
        details: [
            'Clean administrative dashboards for publishing pages, articles, dynamic banners, and media assets',
            'SEO-friendly URL slug generators, automatic XML sitemaps, and Open Graph meta controls',
            'Media library with automatic image resizing and conversion to WebP format upon upload',
            'Markdown and rich-text editing with revision histories and draft preview links',
        ],
    },
    {
        letter: 'E',
        title: 'Payment Gateway Integrations (SSLCommerz, bKash, Stripe)',
        summary:
            'Direct API integrations with local and international payment gateways with webhook validation.',
        details: [
            'bKash merchant API integration with tokenized checkout, payment queries, and refund capabilities',
            'SSLCommerz gateway setup supporting major credit cards, debit cards, and mobile financial services',
            'Instant Payment Notification (IPN) webhooks with cryptographically verified signatures',
            'Automatic transaction logging, duplicate payment prevention, and payment status synchronization',
        ],
    },
];

// Phase 04: Run Content Data (Cloudflare, cPanel, AWS, VPS & DevOps)
const runItems = [
    {
        letter: 'A',
        title: 'Cloudflare Setup & Edge Security',
        summary:
            'DNS routing, SSL/TLS certificates, DDoS mitigation, and edge caching rules.',
        details: [
            'Configure DNS records, nameserver routing, and email security standards (SPF, DKIM, DMARC)',
            'Enforce Full (Strict) SSL encryption between Cloudflare edge servers and origin servers',
            'Implement Page Rules and Cache Rules to cache static assets and reduce origin server load',
            'Set up Web Application Firewall (WAF) rules, bot fight mode, and rate limiting against attacks',
        ],
    },
    {
        letter: 'B',
        title: 'AWS Infrastructure & Linux VPS Deployment',
        summary:
            'Production server provisioning on AWS EC2, S3, RDS, DigitalOcean, Linode, and Hetzner VPS.',
        details: [
            'Clean Linux server provisioning on Ubuntu LTS with Nginx, PHP-FPM, MySQL, Redis, and Supervisor',
            'AWS S3 bucket configuration for private media asset storage and offsite database backups',
            'Automated zero-downtime deployment pipelines using Git webhooks or GitHub Actions',
            'Configure background worker daemons with Supervisor for queue processing and Laravel Horizon',
        ],
    },
    {
        letter: 'C',
        title: 'cPanel & Shared Hosting Management',
        summary:
            'Proper configuration and deployment of Laravel applications on cPanel environments.',
        details: [
            'Restructure directory layouts so public_html points safely to the Laravel /public root directory',
            'Configure correct PHP versions, required PHP extensions, and Composer dependencies on cPanel',
            'Set up cPanel cron jobs for Laravel automated task scheduling (php artisan schedule:run)',
            'Manage MySQL databases, phpMyAdmin exports, and automated backup schedules',
        ],
    },
    {
        letter: 'D',
        title: 'Maintenance Retainers & Priority Bug Support',
        summary:
            'Ongoing monitoring, framework updates, database backups, and rapid troubleshooting.',
        details: [
            'Regular Laravel framework and Composer package updates to protect against security vulnerabilities',
            'Uptime monitoring with instant notifications for server or API disruptions',
            'Scheduled database backups stored offsite with verified restore procedures',
            'Direct developer communication with dedicated hours for fast fixes and small enhancements',
        ],
    },
];

// FAQ Data tailored to real client concerns
const faqs = [
    {
        letter: 'A',
        question: `What services do you provide as a Laravel developer?`,
        answer: `I specialize in full-stack Laravel engineering across the entire project lifecycle: custom web applications, responsive web design, bug fixing, server administration (Cloudflare, cPanel, AWS, and Linux VPS), point of sale (POS) applications, inventory management systems, e-commerce stores, custom CRM software, lightweight CMS solutions, and payment gateway integrations including SSLCommerz and bKash.`,
    },
    {
        letter: 'B',
        question:
            'Can you fix bugs in an existing Laravel application built by another developer?',
        answer: 'Yes. Taking over existing codebases to fix critical bugs, performance issues, and broken workflows is a core part of my work. I start by inspecting your application error logs and profiling slow database queries, then resolve the root cause without breaking existing features.',
    },
    {
        letter: 'C',
        question: 'Which server and hosting platforms do you support?',
        answer: 'I work with Linux VPS providers (DigitalOcean, Linode, Hetzner), Amazon Web Services (AWS EC2, S3, RDS), and traditional cPanel shared or dedicated hosting. I also configure Cloudflare for DNS management, Full Strict SSL, web application firewalls, and asset caching.',
    },
    {
        letter: 'D',
        question:
            'How do you integrate payment gateways like bKash and SSLCommerz?',
        answer: 'I implement direct API integrations using official merchant protocols. Every integration includes secure token generation, Instant Payment Notification (IPN) webhooks with signature verification, duplicate payment guards, and automatic database transaction logging.',
    },
    {
        letter: 'E',
        question:
            'Can you build custom POS or inventory systems for retail businesses?',
        answer: 'Yes. I build custom POS and inventory software designed for daily retail and warehouse operations. Features include fast barcode scanning, thermal receipt printing, multi-counter cash registers, inter-branch stock transfers, low-stock alerts, and daily sales reconciliation.',
    },
    {
        letter: 'F',
        question: 'What is your typical project timeline and delivery process?',
        answer: 'Bug fixes and server setups are typically completed within 1 to 3 days. Complete custom applications (such as a POS, CRM, or e-commerce platform) generally take 4 to 8 weeks depending on scope, delivered in transparent weekly milestones with functional staging previews.',
    },
    {
        letter: 'G',
        question:
            'Do you provide ongoing maintenance and server support after launch?',
        answer: 'Yes. I offer monthly maintenance retainers covering security updates, server health monitoring, automated offsite database backups, and dedicated hours for on-call troubleshooting and new feature additions.',
    },
];

// Technology Stack & Partner Categories (High-fidelity border grid)
const partnerCategories = [
    {
        title: 'Frameworks & Languages',
        description: 'Core technologies for modern full-stack development',
        items: [
            { name: 'Laravel', role: 'PHP Web Framework', icon: Code },
            { name: 'PHP 8.3+', role: 'Modern Server Runtime', icon: Cpu },
            { name: 'Vue.js 3', role: 'Reactive Frontend', icon: Layers },
            {
                name: 'Inertia.js',
                role: 'Modern Monolith Adapter',
                icon: ArrowRightLeft,
            },
            {
                name: 'Tailwind CSS',
                role: 'Utility-First Styling',
                icon: Layers,
            },
        ],
    },
    {
        title: 'Servers & Cloud Infrastructure',
        description: 'Production hosting, security, and edge acceleration',
        items: [
            { name: 'AWS Cloud', role: 'EC2, S3, RDS & SES', icon: Server },
            { name: 'Cloudflare', role: 'DNS, SSL & WAF Security', icon: Zap },
            {
                name: 'Linux VPS',
                role: 'DigitalOcean, Linode, Ubuntu',
                icon: Server,
            },
            { name: 'cPanel', role: 'Hosting Management', icon: Server },
            { name: 'Nginx', role: 'HTTP Reverse Proxy', icon: Server },
        ],
    },
    {
        title: 'Payment Gateways & Commerce',
        description: 'Direct transaction processing and merchant webhooks',
        items: [
            {
                name: 'bKash API',
                role: 'Tokenized Mobile Checkout',
                icon: CreditCard,
            },
            {
                name: 'SSLCommerz',
                role: 'Multi-Card & MFS Gateway',
                icon: CreditCard,
            },
            {
                name: 'Nagad Gateway',
                role: 'Direct Merchant Payment',
                icon: CreditCard,
            },
            {
                name: 'Stripe',
                role: 'International Card Processing',
                icon: CreditCard,
            },
            { name: 'PayPal', role: 'Global Digital Wallet', icon: CreditCard },
        ],
    },
    {
        title: 'Databases & Queue Tooling',
        description: 'Relational storage, caching, and background workers',
        items: [
            { name: 'MySQL', role: 'Relational Database', icon: Database },
            { name: 'PostgreSQL', role: 'Advanced SQL Engine', icon: Database },
            { name: 'Redis', role: 'In-Memory Cache & Queues', icon: Zap },
            { name: 'Supervisor', role: 'Queue Process Daemon', icon: Cpu },
            {
                name: 'Git & Actions',
                role: 'Version Control & CI/CD',
                icon: Code,
            },
        ],
    },
];

// Project Domains & Business Applications Delivered (Clients border grid)
const clientCategories = [
    {
        title: 'Retail & Wholesale (POS)',
        deliverables:
            'Counter billing, barcode scanning, thermal printing, multi-counter cash drawers, daily shift closing',
        platforms: [
            'Grocery & Supermarkets',
            'Fashion Boutiques',
            'Electronics & Hardware',
            'Pharmacies & Medical Stores',
        ],
    },
    {
        title: 'Inventory & Warehousing',
        deliverables:
            'Multi-branch stock tracking, inter-branch transfers, purchase orders, low stock alerts, batch numbers',
        platforms: [
            'Central Warehouses',
            'Distribution Hubs',
            'Wholesale Traders',
            'Raw Material Inventories',
        ],
    },
    {
        title: 'E-Commerce & Digital Stores',
        deliverables:
            'Custom shopping carts, product variations, coupon engines, SSLCommerz/bKash checkout, order tracking',
        platforms: [
            'Direct-to-Consumer Brands',
            'B2B Wholesale Portals',
            'Multi-Category Shops',
            'Digital Download Stores',
        ],
    },
    {
        title: 'CRM & Custom Management',
        deliverables:
            'Customer lead tracking, sales pipelines, automated invoicing, SMS/email updates, staff permission roles',
        platforms: [
            'Service Agencies',
            'Real Estate Pipelines',
            'Consultancy Portals',
            'Membership Systems',
        ],
    },
];


// SEO Structured Data (JSON-LD)
const structuredData = {
    '@type': 'Service',
    '@id': `${appUrl}/services#service`,
    name: pageTitle,
    description: pageDescription,
    url: `${appUrl}/services`,
    provider: {
        '@id': `${appUrl}/#organization`,
    },
    serviceType: [
        'Web Application Development',
        'Point of Sale (POS) Applications',
        'Inventory Management Software',
        'E-Commerce Development',
        'CRM Software Development',
        'CMS Development',
        'Bug Fixing & Code Audits',
        'Server Administration & DevOps',
        'Payment Gateway Integration',
    ],
    hasOfferCatalog: {
        '@type': 'OfferCatalog',
        name: 'Laravel Developer Services',
        itemListElement: [
            {
                '@type': 'Offer',
                itemOffered: {
                    '@type': 'Service',
                    name: 'Phase 01: Audit - Bug Fixes, Code & Server Diagnostics',
                    description:
                        'Isolate runtime exceptions, profile slow database queries, inspect server configurations, and evaluate technical debt.',
                },
            },
            {
                '@type': 'Offer',
                itemOffered: {
                    '@type': 'Service',
                    name: 'Phase 02: Design - UI/UX & Relational Database Architecture',
                    description:
                        'Mobile-first responsive web design with Tailwind CSS and normalized database modeling for transactional software.',
                },
            },
            {
                '@type': 'Offer',
                itemOffered: {
                    '@type': 'Service',
                    name: 'Phase 03: Migrate & Build - Custom Laravel Applications & Payments',
                    description:
                        'Full-stack web applications, POS systems, inventory management, e-commerce, CRM tools, and bKash/SSLCommerz integrations.',
                },
            },
            {
                '@type': 'Offer',
                itemOffered: {
                    '@type': 'Service',
                    name: 'Phase 04: Run - Server Setup, Cloudflare & Ongoing Maintenance',
                    description:
                        'Cloudflare DNS/SSL, AWS and VPS provisioning, cPanel configuration, automated backups, and maintenance retainers.',
                },
            },
        ],
    },
};

const structuredDataEntities = [
    structuredData,
    createFaqSchema(faqs, `${appUrl}/services`),
    {
        '@type': 'WebPage',
        '@id': `${appUrl}/services#webpage`,
        name: pageTitle,
        url: `${appUrl}/services`,
        description: pageDescription,
        isPartOf: {
            '@id': `${appUrl}/#website`,
        },
        about: {
            '@id': `${appUrl}/services#service`,
        },
    },
    {
        '@type': 'BreadcrumbList',
        '@id': `${appUrl}/services#breadcrumb`,
        itemListElement: [
            {
                '@type': 'ListItem',
                position: 1,
                name: 'Home',
                item: `${appUrl}/`,
            },
            {
                '@type': 'ListItem',
                position: 2,
                name: 'Services',
                item: `${appUrl}/services`,
            },
        ],
    },
];
</script>

<template>
    <div
        class="min-h-screen bg-[#FDFDFC] text-[#1b1b18] antialiased selection:bg-primary selection:text-primary-foreground dark:bg-[#0a0a0a] dark:text-[#EDEDEC]"
    >
        <AppHead
            :title="pageTitle"
            :description="pageDescription"
            og-description="Custom web applications, point of sale software, inventory platforms, e-commerce, bug fixing, server management, and payment gateway integrations."
            image-alt="ValenceKit - Full-Stack Laravel Services, Web Apps, POS & CRM"
        />
        <StructuredData :entities="structuredDataEntities" />

        <!-- Top Header Navigation -->
        <header
            class="sticky top-0 z-40 w-full border-b border-border/80 bg-background/80 backdrop-blur-md transition-colors"
        >
            <div
                class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8"
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
                <nav
                    class="flex items-center gap-4 text-sm sm:gap-6"
                    aria-label="Main Navigation"
                >
                    <Link
                        :href="services()"
                        class="font-semibold text-foreground transition-colors hover:text-foreground"
                    >
                        Services
                    </Link>
                    <Link
                        :href="home()"
                        class="font-medium text-muted-foreground transition-colors hover:text-foreground"
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
                            aria-hidden="true"
                        />
                        <Moon v-else class="size-4" aria-hidden="true" />
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

        <main
            class="mx-auto max-w-7xl space-y-24 px-4 py-12 sm:px-6 lg:space-y-32 lg:px-8 lg:py-20"
        >
            <!-- Hero Section -->
            <section class="space-y-12">
                <div class="max-w-3xl space-y-5">
                    <p
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Full-Stack Laravel Engineering
                    </p>
                    <h1
                        class="text-4xl font-normal tracking-tight text-foreground sm:text-6xl lg:text-7xl"
                    >
                        Custom web applications, built to scale.
                    </h1>
                    <p
                        class="text-lg leading-relaxed font-normal text-muted-foreground sm:text-2xl"
                    >
                        I am a full-stack Laravel developer. I build custom web
                        applications, point of sale software, inventory
                        platforms, e-commerce systems, and CRM management tools.
                        I also resolve production bugs, optimize databases,
                        configure cloud servers, and integrate payment gateways
                        like SSLCommerz and bKash.
                    </p>
                    <div
                        class="flex flex-wrap items-center gap-y-2 pt-1 text-sm text-muted-foreground"
                    >
                        <span>Web Applications & POS</span>
                        <span class="mx-2.5 opacity-40">·</span>
                        <span>Bug Fixes & Audits</span>
                        <span class="mx-2.5 opacity-40">·</span>
                        <span>Cloudflare, cPanel, AWS & VPS</span>
                        <span class="mx-2.5 opacity-40">·</span>
                        <span
                            class="font-medium text-foreground underline decoration-border underline-offset-4"
                            >SSLCommerz & bKash</span
                        >
                    </div>
                </div>

                <!-- BEJAMAS-STYLE HERO JUMP NAVIGATION WITH SPOTLIGHT & ART ANIMATION -->
                <nav
                    class="services-hero-nav relative mx-auto mt-10 grid max-w-7xl grid-cols-2 overflow-hidden border-t border-border text-2xl md:grid-cols-4 md:overflow-visible lg:mt-20"
                    aria-label="Service sections"
                    :data-animation-complete="isHeroEntered"
                >
                    <a
                        v-for="(item, index) in heroNavItems"
                        :key="item.id"
                        :href="`#${item.id}`"
                        @click.prevent="scrollToSection(item.id)"
                        class="services-hero-nav__item group relative block cursor-pointer border-l border-border px-6 pt-8 pb-10 outline-none first:border-l-0 md:pb-20 lg:pb-28"
                        :class="{
                            'border-l-0 md:border-l': index === 2,
                        }"
                    >
                        <!-- Heading -->
                        <h3
                            class="text-2xl font-normal tracking-tight text-foreground md:mb-5 md:text-3xl"
                        >
                            {{ item.name }}
                        </h3>

                        <!-- Interactive Row: Control Circle, Vector Icon, Jump Hint -->
                        <div class="relative flex min-h-[50px] items-center">
                            <!-- Circular Control Button -->
                            <span
                                class="services-hero-nav__control relative mb-auto flex size-10 flex-none items-center justify-center overflow-hidden rounded-full border border-border/80 bg-white text-foreground shadow-xs dark:bg-[#18181b]"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="24"
                                    height="24"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    class="services-hero-nav__arrow absolute"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill="currentColor"
                                        fill-rule="evenodd"
                                        d="M2.625 12a9.375 9.375 0 1 1 18.75 0 9.375 9.375 0 0 1-18.75 0ZM12 1.375C6.132 1.375 1.375 6.132 1.375 12S6.132 22.625 12 22.625 22.625 17.868 22.625 12 17.868 1.375 12 1.375Zm-1.5 7a.625.625 0 1 0 0 1.25h2.991l-4.933 4.933a.625.625 0 0 0 .884.884l4.933-4.933v2.99a.625.625 0 1 0 1.25 0V9A.625.625 0 0 0 15 8.376h-4.5Z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </span>

                            <!-- Vector Icon in place of PNG -->
                            <div
                                class="services-hero-nav__art pointer-events-none absolute top-0.5 left-12 z-10 flex size-9 items-center justify-center text-muted-foreground group-hover:text-foreground"
                            >
                                <component
                                    :is="item.icon"
                                    class="size-6 sm:size-7"
                                    :stroke-width="1.75"
                                    aria-hidden="true"
                                />
                            </div>

                            <!-- Hint: Jump to section with animated gradient mask -->
                            <span
                                class="services-hero-nav__hint relative mb-auto ml-2 overflow-hidden px-2 pt-[0.5625rem] text-base"
                            >
                                <span
                                    class="services-hero-nav__hint-label block text-sm font-medium whitespace-nowrap text-foreground"
                                >
                                    Jump to section
                                </span>
                                <span
                                    class="services-hero-nav__hint-mask absolute top-0 left-0 size-full bg-gradient-to-r from-transparent from-0% to-[#FDFDFC] to-20% dark:to-[#0a0a0a]"
                                    aria-hidden="true"
                                />
                            </span>
                        </div>
                    </a>
                </nav>
            </section>

            <!-- PHASE 01: AUDIT (Bug Fixes, Code & Server Diagnostics) -->
            <section
                id="audit"
                class="scroll-mt-28 border-t border-border pt-16 lg:pt-24"
            >
                <div class="grid grid-cols-1 gap-10 md:grid-cols-12 lg:gap-16">
                    <!-- Left Sticky Summary Column -->
                    <div
                        class="space-y-4 self-start md:sticky md:top-24 md:col-span-4"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="flex size-6 items-center justify-center rounded-md border border-border/60 bg-muted text-foreground"
                            >
                                <ScanSearch
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                            </span>
                            <span
                                class="block text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Phase 01 · Inspection & Bug Fixing
                            </span>
                        </div>
                        <h2
                            class="text-3xl font-semibold tracking-tight text-foreground lg:text-4xl"
                        >
                            Audit
                        </h2>
                        <p
                            class="text-lg leading-snug font-medium text-foreground lg:text-xl"
                        >
                            Identify bugs, slow queries, and server
                            vulnerabilities.
                        </p>
                        <p
                            class="text-sm leading-relaxed text-muted-foreground"
                        >
                            Before writing new features or refactoring legacy
                            code, I inspect the application under real
                            workloads. I trace runtime exceptions, isolate
                            memory leaks, profile slow SQL queries, and review
                            server configurations to find the exact root cause
                            of your system issues.
                        </p>
                        <div
                            class="mt-4 aspect-video w-full overflow-hidden rounded-lg border border-border/70 bg-muted/40 shadow-xs"
                        >
                            <img
                                src="/static/service/audit.webp"
                                alt="Code audit, profiling, and server diagnostics workspace"
                                width="1376"
                                height="768"
                                loading="lazy"
                                decoding="async"
                                class="h-full w-full object-cover transition-transform duration-300 hover:scale-[1.02]"
                            />
                        </div>
                    </div>

                    <!-- Right Interactive Collapsible Accordion List -->
                    <div class="divide-y divide-border md:col-span-8">
                        <div
                            v-for="(item, index) in auditItems"
                            :key="index"
                            class="group/item py-5 transition-colors"
                        >
                            <button
                                @click="toggleAccordion('audit', index)"
                                class="flex w-full cursor-pointer items-start justify-between gap-4 py-1 text-left focus:outline-none"
                                :aria-expanded="activeAccordion.audit === index"
                            >
                                <div class="flex items-start gap-4">
                                    <!-- Alphabet Badge -->
                                    <span
                                        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-colors duration-300"
                                        :class="
                                            activeAccordion.audit === index
                                                ? 'bg-foreground text-background'
                                                : 'bg-muted text-foreground group-hover/item:bg-foreground group-hover/item:text-background'
                                        "
                                    >
                                        {{ item.letter }}
                                    </span>
                                    <div>
                                        <h3
                                            class="text-xl font-medium tracking-tight text-foreground transition-all duration-300 lg:text-2xl"
                                        >
                                            <span
                                                class="collapsible-title-text inline-block"
                                            >
                                                {{ item.title }}
                                            </span>
                                        </h3>
                                        <p
                                            class="mt-1.5 text-sm leading-relaxed text-muted-foreground"
                                        >
                                            {{ item.summary }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Toggle Plus / X icon -->
                                <div
                                    class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md border border-border bg-card transition-colors duration-300"
                                >
                                    <X
                                        v-if="activeAccordion.audit === index"
                                        class="size-4 text-foreground transition-transform duration-300"
                                        aria-hidden="true"
                                    />
                                    <Plus
                                        v-else
                                        class="size-4 text-muted-foreground transition-transform duration-300 group-hover/item:rotate-90 group-hover/item:text-foreground"
                                        aria-hidden="true"
                                    />
                                </div>
                            </button>

                            <!-- Expanded Content -->
                            <div
                                v-if="activeAccordion.audit === index"
                                class="mt-4 animate-in space-y-2.5 pr-4 pl-12 text-sm text-muted-foreground duration-200 fade-in slide-in-from-top-2"
                            >
                                <div
                                    v-for="(point, pIdx) in item.details"
                                    :key="pIdx"
                                    class="flex items-baseline gap-2.5"
                                >
                                    <CheckCircle2
                                        class="mt-0.5 size-4 shrink-0 text-foreground/70"
                                        aria-hidden="true"
                                    />
                                    <span class="leading-relaxed">{{
                                        point
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- PHASE 02: DESIGN (Web Design, UI/UX & System Architecture) -->
            <section
                id="design"
                class="scroll-mt-28 border-t border-border pt-16 lg:pt-24"
            >
                <div class="grid grid-cols-1 gap-10 md:grid-cols-12 lg:gap-16">
                    <!-- Left Sticky Summary Column -->
                    <div
                        class="space-y-4 self-start md:sticky md:top-24 md:col-span-4"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="flex size-6 items-center justify-center rounded-md border border-border/60 bg-muted text-foreground"
                            >
                                <Layers class="size-3.5" aria-hidden="true" />
                            </span>
                            <span
                                class="block text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Phase 02 · UI/UX & System Architecture
                            </span>
                        </div>
                        <h2
                            class="text-3xl font-semibold tracking-tight text-foreground lg:text-4xl"
                        >
                            Design
                        </h2>
                        <p
                            class="text-lg leading-snug font-medium text-foreground lg:text-xl"
                        >
                            Clean web interfaces backed by efficient database
                            schemas.
                        </p>
                        <p
                            class="text-sm leading-relaxed text-muted-foreground"
                        >
                            Good software starts with thoughtful structure. I
                            design responsive web interfaces that are easy to
                            navigate on both desktop and mobile, paired with
                            relational database schemas built to handle real
                            business operations without slowdowns.
                        </p>
                        <div
                            class="mt-4 aspect-video w-full overflow-hidden rounded-lg border border-border/70 bg-muted/40 shadow-xs"
                        >
                            <img
                                src="/static/service/architecture.webp"
                                alt="System architecture and relational database schema design overview"
                                width="1376"
                                height="768"
                                loading="lazy"
                                decoding="async"
                                class="h-full w-full object-cover transition-transform duration-300 hover:scale-[1.02]"
                            />
                        </div>
                    </div>

                    <!-- Right Interactive Collapsible Accordion List -->
                    <div class="divide-y divide-border md:col-span-8">
                        <div
                            v-for="(item, index) in designItems"
                            :key="index"
                            class="group/item py-5 transition-colors"
                        >
                            <button
                                @click="toggleAccordion('design', index)"
                                class="flex w-full cursor-pointer items-start justify-between gap-4 py-1 text-left focus:outline-none"
                                :aria-expanded="
                                    activeAccordion.design === index
                                "
                            >
                                <div class="flex items-start gap-4">
                                    <span
                                        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-colors duration-300"
                                        :class="
                                            activeAccordion.design === index
                                                ? 'bg-foreground text-background'
                                                : 'bg-muted text-foreground group-hover/item:bg-foreground group-hover/item:text-background'
                                        "
                                    >
                                        {{ item.letter }}
                                    </span>
                                    <div>
                                        <h3
                                            class="text-xl font-medium tracking-tight text-foreground transition-all duration-300 lg:text-2xl"
                                        >
                                            <span
                                                class="collapsible-title-text inline-block"
                                            >
                                                {{ item.title }}
                                            </span>
                                        </h3>
                                        <p
                                            class="mt-1.5 text-sm leading-relaxed text-muted-foreground"
                                        >
                                            {{ item.summary }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md border border-border bg-card transition-colors duration-300"
                                >
                                    <X
                                        v-if="activeAccordion.design === index"
                                        class="size-4 text-foreground transition-transform duration-300"
                                        aria-hidden="true"
                                    />
                                    <Plus
                                        v-else
                                        class="size-4 text-muted-foreground transition-transform duration-300 group-hover/item:rotate-90 group-hover/item:text-foreground"
                                        aria-hidden="true"
                                    />
                                </div>
                            </button>

                            <div
                                v-if="activeAccordion.design === index"
                                class="mt-4 animate-in space-y-2.5 pr-4 pl-12 text-sm text-muted-foreground duration-200 fade-in slide-in-from-top-2"
                            >
                                <div
                                    v-for="(point, pIdx) in item.details"
                                    :key="pIdx"
                                    class="flex items-baseline gap-2.5"
                                >
                                    <CheckCircle2
                                        class="mt-0.5 size-4 shrink-0 text-foreground/70"
                                        aria-hidden="true"
                                    />
                                    <span class="leading-relaxed">{{
                                        point
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- PHASE 03: MIGRATE (Web Apps, POS, Inventory, E-commerce, CRM, CMS & Payments) -->
            <section
                id="migrate"
                class="scroll-mt-28 border-t border-border pt-16 lg:pt-24"
            >
                <div class="grid grid-cols-1 gap-10 md:grid-cols-12 lg:gap-16">
                    <!-- Left Sticky Summary Column -->
                    <div
                        class="space-y-4 self-start md:sticky md:top-24 md:col-span-4"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="flex size-6 items-center justify-center rounded-md border border-border/60 bg-muted text-foreground"
                            >
                                <ArrowRightLeft
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                            </span>
                            <span
                                class="block text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Phase 03 · Application Engineering
                            </span>
                        </div>
                        <h2
                            class="text-3xl font-semibold tracking-tight text-foreground lg:text-4xl"
                        >
                            Migrate
                        </h2>
                        <p
                            class="text-lg leading-snug font-medium text-foreground lg:text-xl"
                        >
                            Custom Laravel web applications, business software,
                            and payment integrations.
                        </p>
                        <p
                            class="text-sm leading-relaxed text-muted-foreground"
                        >
                            Whether upgrading a legacy PHP application or
                            building a new web system from scratch, I write
                            clean, maintainable Laravel code. From inventory
                            platforms and POS registers to custom e-commerce and
                            local payment gateways, every feature is built to
                            run reliably.
                        </p>
                        <div
                            class="mt-4 aspect-video w-full overflow-hidden rounded-lg border border-border/70 bg-muted/40 shadow-xs"
                        >
                            <img
                                src="/static/service/apps.webp"
                                alt="Custom Laravel web application, POS system, and business software interface"
                                width="1376"
                                height="768"
                                loading="lazy"
                                decoding="async"
                                class="h-full w-full object-cover transition-transform duration-300 hover:scale-[1.02]"
                            />
                        </div>
                    </div>

                    <!-- Right Interactive Collapsible Accordion List -->
                    <div class="divide-y divide-border md:col-span-8">
                        <div
                            v-for="(item, index) in migrateItems"
                            :key="index"
                            class="group/item py-5 transition-colors"
                        >
                            <button
                                @click="toggleAccordion('migrate', index)"
                                class="flex w-full cursor-pointer items-start justify-between gap-4 py-1 text-left focus:outline-none"
                                :aria-expanded="
                                    activeAccordion.migrate === index
                                "
                            >
                                <div class="flex items-start gap-4">
                                    <span
                                        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-colors duration-300"
                                        :class="
                                            activeAccordion.migrate === index
                                                ? 'bg-foreground text-background'
                                                : 'bg-muted text-foreground group-hover/item:bg-foreground group-hover/item:text-background'
                                        "
                                    >
                                        {{ item.letter }}
                                    </span>
                                    <div>
                                        <h3
                                            class="text-xl font-medium tracking-tight text-foreground transition-all duration-300 lg:text-2xl"
                                        >
                                            <span
                                                class="collapsible-title-text inline-block"
                                            >
                                                {{ item.title }}
                                            </span>
                                        </h3>
                                        <p
                                            class="mt-1.5 text-sm leading-relaxed text-muted-foreground"
                                        >
                                            {{ item.summary }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md border border-border bg-card transition-colors duration-300"
                                >
                                    <X
                                        v-if="activeAccordion.migrate === index"
                                        class="size-4 text-foreground transition-transform duration-300"
                                        aria-hidden="true"
                                    />
                                    <Plus
                                        v-else
                                        class="size-4 text-muted-foreground transition-transform duration-300 group-hover/item:rotate-90 group-hover/item:text-foreground"
                                        aria-hidden="true"
                                    />
                                </div>
                            </button>

                            <div
                                v-if="activeAccordion.migrate === index"
                                class="mt-4 animate-in space-y-2.5 pr-4 pl-12 text-sm text-muted-foreground duration-200 fade-in slide-in-from-top-2"
                            >
                                <div
                                    v-for="(point, pIdx) in item.details"
                                    :key="pIdx"
                                    class="flex items-baseline gap-2.5"
                                >
                                    <CheckCircle2
                                        class="mt-0.5 size-4 shrink-0 text-foreground/70"
                                        aria-hidden="true"
                                    />
                                    <span class="leading-relaxed">{{
                                        point
                                    }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Scoped Acceptance Criteria Callout -->
                        <div class="pt-8">
                            <div
                                class="flex flex-col justify-between gap-5 rounded-xl border border-border bg-card p-6 shadow-xs sm:flex-row sm:items-center sm:p-7"
                            >
                                <div class="max-w-xl space-y-1.5">
                                    <p
                                        class="flex items-center gap-2 text-sm font-semibold text-foreground"
                                    >
                                        <ShieldCheck
                                            class="size-4 text-emerald-500"
                                            aria-hidden="true"
                                        />
                                        Code quality, automated tests, and
                                        security are standard criteria
                                    </p>
                                    <p
                                        class="text-xs leading-relaxed text-muted-foreground sm:text-sm"
                                    >
                                        Every application includes input
                                        validation, CSRF protection, SQL
                                        injection prevention, and Pest automated
                                        tests. No unverified third-party
                                        libraries or fragile shortcuts.
                                    </p>
                                </div>
                                <Link
                                    :href="home()"
                                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-xs font-medium text-primary-foreground shadow-xs transition-colors hover:bg-primary/90 sm:text-sm"
                                >
                                    Discuss your project
                                    <ArrowRight
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- PHASE 04: RUN (Cloudflare, cPanel, AWS, VPS & DevOps) -->
            <section
                id="run"
                class="scroll-mt-28 border-t border-border pt-16 lg:pt-24"
            >
                <div class="grid grid-cols-1 gap-10 md:grid-cols-12 lg:gap-16">
                    <!-- Left Sticky Summary Column -->
                    <div
                        class="space-y-4 self-start md:sticky md:top-24 md:col-span-4"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="flex size-6 items-center justify-center rounded-md border border-border/60 bg-muted text-foreground"
                            >
                                <Zap class="size-3.5" aria-hidden="true" />
                            </span>
                            <span
                                class="block text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Phase 04 · Server & DevOps Operations
                            </span>
                        </div>
                        <h2
                            class="text-3xl font-semibold tracking-tight text-foreground lg:text-4xl"
                        >
                            Run
                        </h2>
                        <p
                            class="text-lg leading-snug font-medium text-foreground lg:text-xl"
                        >
                            Server setups, cloud configuration, and ongoing
                            maintenance.
                        </p>
                        <p
                            class="text-sm leading-relaxed text-muted-foreground"
                        >
                            Deploying an application is only the first step. I
                            configure high-availability servers, set up DNS and
                            SSL with Cloudflare, provision AWS infrastructure,
                            manage cPanel environments, and provide continuous
                            maintenance so your systems run without
                            interruptions.
                        </p>
                        <div
                            class="mt-4 aspect-video w-full overflow-hidden rounded-lg border border-border/70 bg-muted/40 shadow-xs"
                        >
                            <img
                                src="/static/service/cloud.webp"
                                alt="Cloud infrastructure, VPS management, and high-availability server orchestration"
                                width="1376"
                                height="768"
                                loading="lazy"
                                decoding="async"
                                class="h-full w-full object-cover transition-transform duration-300 hover:scale-[1.02]"
                            />
                        </div>
                    </div>

                    <!-- Right Interactive Collapsible Accordion List -->
                    <div class="divide-y divide-border md:col-span-8">
                        <div
                            v-for="(item, index) in runItems"
                            :key="index"
                            class="group/item py-5 transition-colors"
                        >
                            <button
                                @click="toggleAccordion('run', index)"
                                class="flex w-full cursor-pointer items-start justify-between gap-4 py-1 text-left focus:outline-none"
                                :aria-expanded="activeAccordion.run === index"
                            >
                                <div class="flex items-start gap-4">
                                    <span
                                        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-colors duration-300"
                                        :class="
                                            activeAccordion.run === index
                                                ? 'bg-foreground text-background'
                                                : 'bg-muted text-foreground group-hover/item:bg-foreground group-hover/item:text-background'
                                        "
                                    >
                                        {{ item.letter }}
                                    </span>
                                    <div>
                                        <h3
                                            class="text-xl font-medium tracking-tight text-foreground transition-all duration-300 lg:text-2xl"
                                        >
                                            <span
                                                class="collapsible-title-text inline-block"
                                            >
                                                {{ item.title }}
                                            </span>
                                        </h3>
                                        <p
                                            class="mt-1.5 text-sm leading-relaxed text-muted-foreground"
                                        >
                                            {{ item.summary }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md border border-border bg-card transition-colors duration-300"
                                >
                                    <X
                                        v-if="activeAccordion.run === index"
                                        class="size-4 text-foreground transition-transform duration-300"
                                        aria-hidden="true"
                                    />
                                    <Plus
                                        v-else
                                        class="size-4 text-muted-foreground transition-transform duration-300 group-hover/item:rotate-90 group-hover/item:text-foreground"
                                        aria-hidden="true"
                                    />
                                </div>
                            </button>

                            <div
                                v-if="activeAccordion.run === index"
                                class="mt-4 animate-in space-y-2.5 pr-4 pl-12 text-sm text-muted-foreground duration-200 fade-in slide-in-from-top-2"
                            >
                                <div
                                    v-for="(point, pIdx) in item.details"
                                    :key="pIdx"
                                    class="flex items-baseline gap-2.5"
                                >
                                    <CheckCircle2
                                        class="mt-0.5 size-4 shrink-0 text-foreground/70"
                                        aria-hidden="true"
                                    />
                                    <span class="leading-relaxed">{{
                                        point
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- PARTNERS & TECHNOLOGY STACK SECTION: BORDER GRID -->
            <section
                class="w-full space-y-12 border-t border-border pt-16 lg:pt-24"
            >
                <div class="max-w-3xl space-y-4">
                    <p
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Technologies & Infrastructure
                    </p>
                    <h2
                        class="text-3xl font-medium tracking-tight text-foreground md:text-5xl"
                    >
                        Technology Stack
                    </h2>
                    <p
                        class="text-xl leading-relaxed font-normal text-muted-foreground lg:text-2xl"
                    >
                        Proven frameworks, cloud platforms, and payment gateways
                        configured to production standards.
                    </p>
                </div>

                <!-- Responsive Border Grid Table -->
                <div class="w-full border-t border-border">
                    <div
                        v-for="category in partnerCategories"
                        :key="category.title"
                        class="grid grid-cols-1 border-b border-border lg:grid-cols-4"
                    >
                        <!-- Category Header -->
                        <div
                            class="flex flex-col justify-start p-6 text-lg font-normal text-foreground lg:p-8 lg:text-xl"
                        >
                            <span class="font-medium lg:sticky lg:top-28">{{
                                category.title
                            }}</span>
                            <span
                                class="mt-1 block text-xs text-muted-foreground"
                                >{{ category.description }}</span
                            >
                        </div>

                        <!-- Stack Items Subgrid -->
                        <div
                            class="-mt-px -ml-px grid grid-cols-2 border-t border-border sm:grid-cols-3 lg:col-span-3 lg:border-t-0 lg:border-l"
                        >
                            <div
                                v-for="item in category.items"
                                :key="item.name"
                                class="group/logo flex min-h-[7rem] flex-col justify-center border-t border-l border-border p-5 transition-colors hover:bg-muted/40 sm:p-6 lg:h-32"
                            >
                                <div
                                    class="mb-1.5 flex items-center gap-2.5 text-foreground"
                                >
                                    <component
                                        :is="item.icon"
                                        class="size-4 shrink-0 text-muted-foreground transition-colors group-hover/logo:text-foreground sm:size-5"
                                        aria-hidden="true"
                                    />
                                    <span
                                        class="text-sm font-semibold tracking-tight sm:text-base"
                                        >{{ item.name }}</span
                                    >
                                </div>
                                <span
                                    class="line-clamp-1 text-xs text-muted-foreground"
                                    >{{ item.role }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CLIENTS & APPLICATION DOMAINS SECTION: BORDER GRID -->
            <section
                class="w-full space-y-12 border-t border-border pt-16 lg:pt-24"
            >
                <div class="max-w-3xl space-y-4">
                    <p
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Project Domains & Client Work
                    </p>
                    <h2
                        class="text-3xl font-medium tracking-tight text-foreground md:text-5xl"
                    >
                        Applications Delivered
                    </h2>
                    <p
                        class="text-xl leading-relaxed font-normal text-muted-foreground lg:text-2xl"
                    >
                        Custom software solutions built for business owners,
                        retail stores, and service companies.
                    </p>
                </div>

                <!-- Responsive Border Grid Table -->
                <div class="w-full border-t border-border">
                    <div
                        v-for="domain in clientCategories"
                        :key="domain.title"
                        class="grid grid-cols-1 border-b border-border lg:grid-cols-4"
                    >
                        <!-- Category Header -->
                        <div
                            class="flex flex-col justify-start p-6 text-lg font-normal text-foreground lg:p-8 lg:text-xl"
                        >
                            <span class="font-medium lg:sticky lg:top-28">{{
                                domain.title
                            }}</span>
                            <span
                                class="mt-2 block text-xs leading-relaxed text-muted-foreground"
                                >{{ domain.deliverables }}</span
                            >
                        </div>

                        <!-- Deliverables 2x2 Subgrid -->
                        <div
                            class="-mt-px -ml-px grid grid-cols-1 border-t border-border sm:grid-cols-2 lg:col-span-3 lg:border-t-0 lg:border-l"
                        >
                            <div
                                v-for="plat in domain.platforms"
                                :key="plat"
                                class="flex min-h-[5.5rem] items-center border-t border-l border-border p-5 transition-colors hover:bg-muted/40 sm:p-6 lg:h-28"
                            >
                                <div class="flex items-center gap-3">
                                    <span
                                        class="size-2 shrink-0 rounded-full bg-foreground/60"
                                        aria-hidden="true"
                                    />
                                    <span
                                        class="text-sm font-medium text-foreground sm:text-base"
                                        >{{ plat }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- FAQS SECTION: FULL WIDTH & MODERN ACCORDION -->
            <FaqSection :items="faqs" />


            <!-- BOTTOM CTA BANNER -->
            <section
                class="space-y-6 rounded-2xl border border-border bg-card p-8 text-center shadow-xs sm:p-14"
            >
                <div class="mx-auto max-w-2xl space-y-4">
                    <p
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Start a Project or Fix an Issue
                    </p>
                    <h2
                        class="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl lg:text-5xl"
                    >
                        Ready to build or fix your application?
                    </h2>
                    <p
                        class="text-base leading-relaxed text-muted-foreground sm:text-lg"
                    >
                        Send over your project requirements, server details, or
                        bug description. I will review it and reply with a
                        practical technical plan, timeline, and cost estimate.
                    </p>
                </div>

                <div
                    class="flex flex-col items-center justify-center gap-4 pt-2 sm:flex-row"
                >
                    <Link
                        :href="home()"
                        class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-md bg-primary px-6 text-sm font-medium text-primary-foreground shadow-xs transition-colors hover:bg-primary/90 sm:w-auto"
                    >
                        Send a message
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                    <a
                        href="mailto:hello@valencekit.test"
                        class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-md border border-border bg-background px-6 text-sm font-medium text-foreground transition-colors hover:bg-muted sm:w-auto"
                    >
                        <Mail
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        hello@valencekit.test
                    </a>
                </div>

                <p class="pt-2 text-xs text-muted-foreground">
                    Standard Non-Disclosure Agreements (NDAs) are welcome for
                    proprietary systems and enterprise databases.
                </p>
            </section>
        </main>

        <PublicFooter />
    </div>
</template>

<style scoped>
/* Hero Navigation Layout */
.services-hero-nav {
    position: relative;
    user-select: none;
}

.services-hero-nav__item {
    transition:
        opacity 0.8s cubic-bezier(0.4, 0, 0.2, 1),
        transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    transform-origin: center center;
}

/* Vector Icon Initial & Transition States */
.services-hero-nav__art {
    transform-origin: center center;
    transition:
        filter 0.4s cubic-bezier(0.4, 0, 0.2, 1),
        opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1),
        transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    transform: scale(1);
    pointer-events: none;
    will-change: transform, opacity, filter;
}

@media (min-width: 1024px) {
    .services-hero-nav:not([data-animation-complete='true'])
        .services-hero-nav__art {
        opacity: 0;
        transform: scale(0.6);
        transition: none;
    }
    .services-hero-nav[data-animation-complete='true'] .services-hero-nav__art {
        opacity: 1;
        transform: scale(1);
    }
}

/* Control Circle */
.services-hero-nav__control {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) 0.15s;
}

/* Arrow inside Control */
.services-hero-nav__arrow {
    transition:
        transform 0.5s cubic-bezier(0.4, 0, 0.2, 1),
        color 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateY(-2.5rem) rotate(0deg);
}

/* Hint Label & Mask */
.services-hero-nav__hint-label {
    opacity: 0;
    filter: blur(2px);
    transition:
        opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1),
        filter 0.6s cubic-bezier(0.4, 0, 0.2, 1),
        transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    transform: scale(0.98);
}

.services-hero-nav__hint-mask {
    transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Nav Hover: Non-hovered items dim & scale down */
.services-hero-nav[data-animation-complete='true']:hover
    .services-hero-nav__item {
    opacity: 0.4;
    transition-duration: 0.4s;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transform: scale(0.95);
}

/* Nav Hover: Non-hovered vector icons blur subtly & dim */
.services-hero-nav[data-animation-complete='true']:hover
    .services-hero-nav__art {
    filter: blur(6px);
    opacity: 0.25;
}

/* Nav Hover: Non-hovered controls dim & blur */
.services-hero-nav[data-animation-complete='true']:hover
    .services-hero-nav__control {
    opacity: 0.6;
    filter: blur(2px);
}

/* Specifically Hovered Item */
.services-hero-nav[data-animation-complete='true']
    .services-hero-nav__item:hover {
    opacity: 1 !important;
    transition-duration: 0.2s;
    transition-timing-function: cubic-bezier(0, 0, 0.2, 1);
    transform: scale(1) !important;
}

/* 1. Its vector icon shrinks and dissolves away */
.services-hero-nav[data-animation-complete='true']
    .services-hero-nav__item:hover
    .services-hero-nav__art {
    opacity: 0 !important;
    transform: scale(0.4) !important;
    filter: none !important;
}

/* 2. Its control circle inverts */
.services-hero-nav[data-animation-complete='true']
    .services-hero-nav__item:hover
    .services-hero-nav__control {
    opacity: 1 !important;
    filter: none !important;
    background-color: var(--foreground) !important;
    color: var(--background) !important;
    border-color: var(--foreground) !important;
}

/* 3. Its arrow slides down into the circle and rotates to point downwards */
.services-hero-nav[data-animation-complete='true']
    .services-hero-nav__item:hover
    .services-hero-nav__arrow {
    transform: translateY(0px) rotate(135deg) !important;
    color: var(--background) !important;
}

/* 4. Its hint label reveals from blur */
.services-hero-nav[data-animation-complete='true']
    .services-hero-nav__item:hover
    .services-hero-nav__hint-label {
    opacity: 1 !important;
    filter: none !important;
    transform: scale(1) !important;
}

/* 5. Its gradient mask wipes away */
.services-hero-nav[data-animation-complete='true']
    .services-hero-nav__item:hover
    .services-hero-nav__hint-mask {
    transform: translateX(100%) !important;
}

/* Collapsible accordion title underline animation */
.collapsible-title-text {
    box-decoration-break: clone;
    background-image: linear-gradient(currentColor, currentColor);
    background-position: 100% 100%;
    background-repeat: no-repeat;
    background-size: 0 1px;
    padding-bottom: 0.25rem;
    transition: background-size 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

button:hover .collapsible-title-text {
    background-position: 0 100%;
    background-size: 100% 1px;
}
</style>
