<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { privacyPolicy, terms } from '@/routes';

const page = usePage<{ name?: string }>();
const appName = page.props.name || 'ValenceKit';
</script>

<template>
    <footer class="border-t border-border py-8 text-sm text-muted-foreground">
        <div
            class="mx-auto flex max-w-7xl flex-col gap-5 px-4 sm:px-6 lg:px-8"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p>
                    © {{ new Date().getFullYear() }} {{ appName }}. All rights
                    reserved.
                </p>
                <nav
                    aria-label="Footer navigation"
                    class="flex flex-wrap gap-x-5 gap-y-2"
                >
                    <Link
                        :href="privacyPolicy()"
                        class="transition-colors hover:text-foreground"
                    >
                        Privacy Policy
                    </Link>
                    <Link
                        :href="terms()"
                        class="transition-colors hover:text-foreground"
                    >
                        Terms of Service
                    </Link>
                    <a
                        href="mailto:hello@valencekit.test"
                        class="transition-colors hover:text-foreground"
                    >
                        Support
                    </a>
                </nav>
            </div>
            <p class="text-xs">
                Laravel and Vue.js engineering for practical, production-ready
                applications.
            </p>
        </div>
    </footer>
</template>
