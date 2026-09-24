<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { dashboard, home, login, register, services } from '@/routes';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
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
    RotateCcw
} from '@lucide/vue';

const page = usePage();
const appName = page.props.name || 'ValenceKit';

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
    <div class="min-h-screen bg-[#FDFDFC] text-[#1b1b18] antialiased selection:bg-primary selection:text-primary-foreground dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
        <Head title="Contact Us">
            <link rel="preconnect" href="https://rsms.me/" />
            <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
        </Head>

        <!-- Top Header Navigation -->
        <header class="border-b border-border/80 bg-background/80 backdrop-blur-md sticky top-0 z-40 w-full transition-colors">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <!-- App Logo -->
                <Link :href="home()" class="flex items-center gap-2 outline-none">
                    <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                        <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
                    </div>
                    <span class="hidden sm:inline text-base font-semibold tracking-tight">{{ appName }}</span>
                </Link>

                <!-- Navigation Links -->
                <nav class="flex items-center gap-4 sm:gap-6 text-sm">
                    <Link
                        :href="services()"
                        class="text-muted-foreground transition-colors hover:text-foreground font-medium"
                    >
                        Services
                    </Link>
                    <Link
                        :href="home()"
                        class="text-foreground transition-colors font-semibold"
                    >
                        Contact
                    </Link>
                </nav>

                <!-- Header Actions -->
                <div class="flex items-center gap-2 sm:gap-3 text-sm">
                    <!-- Theme Toggle -->
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="toggleTheme"
                        class="size-8 sm:size-9 rounded-md text-muted-foreground hover:text-foreground"
                        :title="resolvedAppearance === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
                        type="button"
                    >
                        <Sun v-if="resolvedAppearance === 'dark'" class="size-4" />
                        <Moon v-else class="size-4" />
                        <span class="sr-only">Toggle theme</span>
                    </Button>

                    <!-- Auth Links -->
                    <template v-if="$page.props.auth?.user">
                        <Link
                            :href="dashboard()"
                            class="inline-flex h-8 sm:h-9 items-center justify-center rounded-md bg-primary px-3 sm:px-4 text-xs sm:text-sm font-medium text-primary-foreground shadow-xs transition-colors hover:bg-primary/90"
                        >
                            Dashboard
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-flex h-8 sm:h-9 items-center justify-center rounded-md px-2.5 sm:px-3 text-xs sm:text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Log in
                        </Link>
                        <Link
                            :href="register()"
                            class="inline-flex h-8 sm:h-9 items-center justify-center rounded-md bg-primary px-3 sm:px-4 text-xs sm:text-sm font-medium text-primary-foreground shadow-xs transition-colors hover:bg-primary/90"
                        >
                            Register
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Main Contact Section (UI reference from Bejamas get-in-touch, styled with project design & classes) -->
        <main class="mx-auto max-w-6xl px-6 py-12 lg:py-20 lg:px-8">
            <div class="grid grid-cols-1 gap-12 lg:grid-cols-12 lg:gap-16 items-start">
                
                <!-- Left Column: Header & Context Details -->
                <div class="flex flex-col gap-6 lg:col-span-5 lg:pt-4">
                    <div class="space-y-4">
                        <p class="text-xs font-semibold tracking-wider uppercase text-muted-foreground">
                            Get In Touch
                        </p>
                        <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl lg:text-6xl text-foreground">
                            Tell us about your project.
                        </h1>
                        <p class="text-base sm:text-lg leading-relaxed text-muted-foreground">
                            Tell us what you're working with, what's slowing your team down, and what needs to change. We'll reply with a practical next step. If a quick call makes more sense, we'll suggest one.
                        </p>
                    </div>

                    <!-- Informational Highlights -->
                    <div class="mt-4 flex flex-col gap-5 border-t border-border pt-6 text-sm text-muted-foreground">
                        <div class="flex items-start gap-3">
                            <Clock class="size-5 shrink-0 text-foreground/80 mt-0.5" />
                            <div>
                                <p class="font-medium text-foreground">Fast Turnaround</p>
                                <p>We typically review and reply within 24 hours on business days.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <ShieldCheck class="size-5 shrink-0 text-foreground/80 mt-0.5" />
                            <div>
                                <p class="font-medium text-foreground">Security & Confidentiality</p>
                                <p>We are comfortable with DPAs, standard NDAs, and formal RFP evaluations.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <Mail class="size-5 shrink-0 text-foreground/80 mt-0.5" />
                            <div>
                                <p class="font-medium text-foreground">Prefer direct email?</p>
                                <p>
                                    Write to
                                    <a href="mailto:hello@valencekit.test" class="font-medium text-foreground underline underline-offset-4 hover:text-primary">
                                        hello@valencekit.test
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Contact Form (Card using existing project design and UI classes) -->
                <div class="lg:col-span-7">
                    <Card class="border-border bg-card shadow-sm rounded-xl">
                        <!-- Submission Success View -->
                        <div v-if="isSubmitted" class="p-8 sm:p-12 text-center flex flex-col items-center">
                            <div class="flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary mb-6">
                                <Check class="size-7" />
                            </div>
                            <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight text-foreground mb-3">
                                Message sent successfully!
                            </h2>
                            <p class="max-w-md text-muted-foreground text-base mb-8">
                                Thank you for reaching out, {{ form.name }}. We've received your inquiry and our team will get back to you at <span class="font-medium text-foreground">{{ form.email }}</span> shortly.
                            </p>
                            <Button @click="resetForm" variant="outline" class="gap-2">
                                <RotateCcw class="size-4" />
                                Send another message
                            </Button>
                        </div>

                        <!-- Active Form View -->
                        <template v-else>
                            <CardHeader class="pb-4">
                                <CardTitle class="text-2xl font-semibold tracking-tight">
                                    Send us a message
                                </CardTitle>
                                <CardDescription>
                                    Fill out the form below and we'll get back to you with actionable recommendations.
                                </CardDescription>
                            </CardHeader>

                            <CardContent>
                                <form @submit.prevent="handleSubmit" class="space-y-6">
                                    <!-- Full Name Field -->
                                    <div class="grid gap-2">
                                        <Label for="full-name" class="text-sm font-medium">
                                            Your name <span class="text-red-500">*</span>
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
                                        <Label for="work-email" class="text-sm font-medium">
                                            Work email <span class="text-red-500">*</span>
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
                                        <div class="flex items-center justify-between">
                                            <Label for="phone-number" class="text-sm font-medium">
                                                Phone
                                            </Label>
                                            <span class="text-xs text-muted-foreground">(optional)</span>
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
                                        <Label for="project-message" class="text-sm font-medium">
                                            Tell us about your project <span class="text-red-500">*</span>
                                        </Label>
                                        <textarea
                                            id="project-message"
                                            v-model="form.message"
                                            rows="4"
                                            required
                                            placeholder="What are you looking to migrate or improve?"
                                            class="placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive"
                                            :aria-invalid="!!errors.message"
                                        ></textarea>
                                        <InputError :message="errors.message" />
                                    </div>

                                    <!-- Referral Source Field (Optional) -->
                                    <div class="grid gap-2">
                                        <div class="flex items-center justify-between">
                                            <Label for="referral-source" class="text-sm font-medium">
                                                How did you hear about us?
                                            </Label>
                                            <span class="text-xs text-muted-foreground">(optional)</span>
                                        </div>
                                        <Input
                                            id="referral-source"
                                            v-model="form.referralSource"
                                            placeholder="Google, LinkedIn, a recommendation…"
                                            class="w-full"
                                        />
                                    </div>

                                    <!-- Submit Button & Submission Status -->
                                    <div class="pt-2 flex flex-col sm:flex-row sm:items-center gap-4">
                                        <Button
                                            type="submit"
                                            size="lg"
                                            :disabled="isSubmitting"
                                            class="w-full sm:w-auto sm:min-w-44 gap-2 font-medium"
                                        >
                                            <span v-if="isSubmitting" class="flex items-center gap-2">
                                                <svg class="size-4 animate-spin text-current" viewBox="0 0 24 24" fill="none">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Sending...
                                            </span>
                                            <span v-else class="flex items-center gap-2">
                                                Send it over
                                                <ArrowRight class="size-4" />
                                            </span>
                                        </Button>

                                        <p class="text-xs text-muted-foreground">
                                            No spam. We only use your details to reply.
                                        </p>
                                    </div>

                                    <!-- Bottom Procurement & RFP Note -->
                                    <div class="border-t border-border pt-4 mt-6 text-xs text-muted-foreground space-y-1.5">
                                        <p>
                                            Running an RFP or working through procurement? Send it over. We're used to security reviews, DPAs, and formal evaluation processes.
                                        </p>
                                    </div>
                                </form>
                            </CardContent>
                        </template>
                    </Card>
                </div>

            </div>
        </main>

        <!-- Minimal Footer -->
        <footer class="mt-auto border-t border-border py-8 text-center text-xs text-muted-foreground">
            <div class="mx-auto max-w-6xl px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p>© {{ new Date().getFullYear() }} {{ appName }}. All rights reserved.</p>
                <p>Simple, minimal & modern engineering.</p>
            </div>
        </footer>
    </div>
</template>
