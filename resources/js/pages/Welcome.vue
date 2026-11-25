<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { dashboard, home, login, register, services } from '@/routes';
import { store as storeContact } from '@/routes/contact';
import { trackContactLead, type TrackingState } from '@/lib/tracking';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AppHead from '@/components/AppHead.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import PublicFooter from '@/components/PublicFooter.vue';
import StructuredData from '@/components/StructuredData.vue';
import ScrollProgress from '@/components/ScrollProgress.vue';
import ScrollToTop from '@/components/ScrollToTop.vue';
import { useScrollReveal } from '@/composables/useScrollReveal';
import { Button } from '@/components/ui/button';

useScrollReveal();
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
} from '@lucide/vue';

const page = usePage<{
    name?: string;
    appUrl: string;
    tracking: TrackingState;
}>();
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

const form = useForm({
    name: '',
    email: '',
    phone: '',
    message: '',
    referral_source: '',
});
const isSubmitted = ref(false);

const handleSubmit = () => {
    form.post(storeContact.url(), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitted.value = true;
            trackContactLead(page.props.tracking);
        },
    });
};

const resetForm = () => {
    form.reset();
    form.clearErrors();
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
            og-description="ValenceKit is a modern Laravel & Vue.js boilerplate for building fast, secure web applications with Inertia.js, SSR, APIs, and production-ready tools."
            image-alt="ValenceKit - Modern Laravel & Vue.js Boilerplate"
        />
        <StructuredData :entities="structuredData" />

        <!-- Scroll-Linked Progress Indicator -->
        <ScrollProgress />

        <!-- Scroll To Top Button -->
        <ScrollToTop />

        <!-- Top Header Navigation -->
        <PublicHeader />

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
                                            :aria-invalid="!!form.errors.name"
                                        />
                                        <InputError
                                            :message="form.errors.name"
                                        />
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
                                            :aria-invalid="!!form.errors.email"
                                        />
                                        <InputError
                                            :message="form.errors.email"
                                        />
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
                                            v-model="form.phone"
                                            type="tel"
                                            placeholder="Include your country code"
                                            autocomplete="tel"
                                            class="w-full"
                                        />
                                        <InputError
                                            :message="form.errors.phone"
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
                                            :aria-invalid="
                                                !!form.errors.message
                                            "
                                        ></textarea>
                                        <InputError
                                            :message="form.errors.message"
                                        />
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
                                            v-model="form.referral_source"
                                            placeholder="Google, LinkedIn, a recommendation…"
                                            class="w-full"
                                        />
                                        <InputError
                                            :message="
                                                form.errors.referral_source
                                            "
                                        />
                                    </div>

                                    <!-- Submit Button & Submission Status -->
                                    <div
                                        class="flex flex-col gap-4 pt-2 sm:flex-row sm:items-center"
                                    >
                                        <Button
                                            type="submit"
                                            size="lg"
                                            :disabled="form.processing"
                                            class="w-full gap-2 font-medium sm:w-auto sm:min-w-44"
                                        >
                                            <span
                                                v-if="form.processing"
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
        </main>

        <PublicFooter />
    </div>
</template>
