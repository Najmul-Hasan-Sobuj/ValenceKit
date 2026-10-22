<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { dashboard, home, login, register, services } from '@/routes';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import {
    NavigationMenu,
    NavigationMenuContent,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    NavigationMenuTrigger,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import { useAppearance } from '@/composables/useAppearance';
import { Sun, Moon, ArrowRight } from '@lucide/vue';

const page = usePage<{ name?: string; auth?: { user?: unknown } }>();
const appName = page.props.name || 'ValenceKit';
const { resolvedAppearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};

const dummyServices = [
    {
        title: 'Point of Sale (POS)',
        href: '/services#pos',
        tagline: 'High-speed checkout, barcode scanning, and multi-counter registers.',
    },
    {
        title: 'Inventory & Warehousing',
        href: '/services#inventory',
        tagline: 'Multi-branch stock tracking, batch control, and automated supplier reorders.',
    },
    {
        title: 'E-Commerce Platforms',
        href: '/services#ecommerce',
        tagline: 'Direct-to-consumer storefronts, SKU variations, and local payment checkouts.',
    },
    {
        title: 'CRM & Custom Management',
        href: '/services#crm',
        tagline: 'Client pipelines, automated PDF billing, and role-based staff permissions.',
    },
];

const handleServicesClick = (e: MouseEvent) => {
    e.preventDefault();
    if (typeof window !== 'undefined' && window.location.pathname === '/services') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        router.visit(services());
    }
};
</script>

<template>
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
                >
                    {{ appName }}
                </span>
            </Link>

            <!-- Navigation Menu (Shadcn Getting Started Style) -->
            <NavigationMenu>
                <NavigationMenuList class="gap-1">
                    <!-- Services with Getting Started Menu & Direct Click Navigation -->
                    <NavigationMenuItem>
                        <NavigationMenuTrigger
                            class="bg-transparent hover:bg-accent/50 focus:bg-accent/50 data-[state=open]:bg-accent/50 text-muted-foreground hover:text-foreground font-medium cursor-pointer"
                            @click="handleServicesClick"
                        >
                            Services
                        </NavigationMenuTrigger>
                        <NavigationMenuContent>
                            <ul
                                class="grid gap-3 p-4 w-[340px] sm:w-[480px] md:w-[540px] lg:w-[620px] grid-cols-1 sm:grid-cols-[.85fr_1.15fr]"
                            >
                                <!-- Left Featured Card with Vertical Minimal Bolt Image -->
                                <li class="sm:row-span-4">
                                    <NavigationMenuLink as-child>
                                        <Link
                                            :href="services()"
                                            class="relative flex h-full w-full select-none flex-col justify-end overflow-hidden rounded-md p-5 no-underline outline-none transition-all hover:opacity-95 focus:shadow-md group border border-border/70 shadow-xs min-h-[300px]"
                                        >
                                            <!-- Vertical Bolt Background Image -->
                                            <img
                                                src="/static/service/bolt.jpg"
                                                alt="ValenceKit Engineering Bolt"
                                                class="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                                                loading="eager"
                                            />
                                            <!-- Dark Gradient Scrim for crisp typography contrast -->
                                            <div
                                                class="absolute inset-0 bg-gradient-to-t from-black/95 via-black/45 to-black/15 pointer-events-none"
                                            />

                                            <!-- Card Content Overlaid -->
                                            <div class="relative z-10 text-white">
                                                <div
                                                    class="mb-1 text-base font-semibold font-display tracking-tight text-white"
                                                >
                                                    All Services
                                                </div>
                                                <p
                                                    class="text-xs leading-relaxed text-zinc-300"
                                                >
                                                    Full-stack Laravel & Vue engineering: POS, inventory, and tailored platforms.
                                                </p>
                                                <div
                                                    class="mt-3 flex items-center gap-1.5 text-xs font-semibold text-white/90"
                                                >
                                                    <span class="group-hover:underline underline-offset-4">Explore Overview</span>
                                                    <ArrowRight
                                                        class="size-3.5 transition-transform group-hover:translate-x-1"
                                                    />
                                                </div>
                                            </div>
                                        </Link>
                                    </NavigationMenuLink>
                                </li>

                                <!-- Right Column: Service Pages -->
                                <li
                                    v-for="service in dummyServices"
                                    :key="service.title"
                                >
                                    <NavigationMenuLink as-child>
                                        <Link
                                            :href="service.href"
                                            class="block select-none space-y-1 rounded-md p-3 leading-none no-underline outline-none transition-colors hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground"
                                        >
                                            <div
                                                class="text-sm font-medium leading-none text-foreground"
                                            >
                                                {{ service.title }}
                                            </div>
                                            <p
                                                class="line-clamp-2 text-xs leading-snug text-muted-foreground"
                                            >
                                                {{ service.tagline }}
                                            </p>
                                        </Link>
                                    </NavigationMenuLink>
                                </li>
                            </ul>
                        </NavigationMenuContent>
                    </NavigationMenuItem>

                    <!-- Contact Link -->
                    <NavigationMenuItem>
                        <NavigationMenuLink as-child>
                            <Link
                                :href="home()"
                                :class="navigationMenuTriggerStyle()"
                                class="bg-transparent hover:bg-accent/50 focus:bg-accent/50 text-muted-foreground hover:text-foreground font-medium"
                            >
                                Contact
                            </Link>
                        </NavigationMenuLink>
                    </NavigationMenuItem>
                </NavigationMenuList>
            </NavigationMenu>

            <!-- Header Actions -->
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
