<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { dashboard, home, login, register, services } from '@/routes';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';
import { Sun, Moon } from '@lucide/vue';

const page = usePage<{ name?: string; auth?: { user?: unknown } }>();
const appName = page.props.name || 'ValenceKit';
const { resolvedAppearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};
</script>

<template>
    <header
        class="sticky top-0 z-40 w-full border-b border-border/80 bg-background/80 backdrop-blur-md transition-colors"
    >
        <div
            class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8"
        >
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
                >
                    {{ appName }}
                </span>
            </Link>

            <nav
                class="flex items-center gap-4 text-sm sm:gap-6"
                aria-label="Main navigation"
            >
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

            <div class="flex items-center gap-2 text-sm sm:gap-3">
                <Button
                    variant="ghost"
                    size="icon"
                    type="button"
                    class="size-8 rounded-md text-muted-foreground hover:text-foreground sm:size-9"
                    :title="
                        resolvedAppearance === 'dark'
                            ? 'Switch to light mode'
                            : 'Switch to dark mode'
                    "
                    @click="toggleTheme"
                >
                    <Sun
                        v-if="resolvedAppearance === 'dark'"
                        class="size-4"
                        aria-hidden="true"
                    />
                    <Moon v-else class="size-4" aria-hidden="true" />
                    <span class="sr-only">Toggle theme</span>
                </Button>

                <Link
                    v-if="$page.props.auth?.user"
                    :href="dashboard()"
                    class="inline-flex h-8 items-center justify-center rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground shadow-xs transition-colors hover:bg-primary/90 sm:h-9 sm:px-4 sm:text-sm"
                >
                    Dashboard
                </Link>
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
</template>
