<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    Search,
    X,
    ChevronDown,
    RotateCcw,
    Folder,
    Sparkles,
} from '@lucide/vue';
import {
    allSupportedIconNames,
    getCategoryIcon,
} from '@/lib/iconRegistry';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

interface Props {
    modelValue?: string;
    color?: string;
    disabled?: boolean;
    placeholder?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: 'Folder',
    color: '#6366f1',
    disabled: false,
    placeholder: 'Select an icon',
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const isOpen = ref(false);
const searchQuery = ref('');
const activeCategory = ref<'all' | string>('all');
const hoveredIcon = ref<string | null>(null);
const visibleCount = ref(60);

// All clean PascalCase supported icon names sorted alphabetically
const allLucideIconNames: string[] = allSupportedIconNames;

// Curated categories for fast discovery
const categories: Record<
    string,
    { label: string; icon: string; items: string[] }
> = {
    popular: {
        label: 'Popular',
        icon: 'Sparkles',
        items: [
            'Folder',
            'FolderOpen',
            'Wallet',
            'CreditCard',
            'ShoppingBag',
            'ShoppingCart',
            'Tag',
            'Package',
            'Server',
            'Database',
            'Laptop',
            'Code',
            'Home',
            'Car',
            'Coffee',
            'Utensils',
            'Heart',
            'Users',
            'Star',
            'Zap',
            'Settings',
            'FileText',
            'Briefcase',
        ],
    },
    finance: {
        label: 'Finance',
        icon: 'Wallet',
        items: [
            'Wallet',
            'CreditCard',
            'Banknote',
            'Coins',
            'DollarSign',
            'PiggyBank',
            'Receipt',
            'ReceiptText',
            'Scale',
            'Landmark',
            'HandCoins',
            'BadgeDollarSign',
            'Calculator',
            'Percent',
            'TrendingUp',
            'TrendingDown',
            'CircleDollarSign',
            'ChartLine',
            'ChartPie',
            'ChartBar',
            'ArrowUpRight',
            'ArrowDownLeft',
        ],
    },
    commerce: {
        label: 'Commerce',
        icon: 'ShoppingBag',
        items: [
            'ShoppingBag',
            'ShoppingCart',
            'Store',
            'Package',
            'Box',
            'Boxes',
            'Tag',
            'Tags',
            'Gift',
            'Truck',
            'Barcode',
            'QrCode',
            'Ticket',
            'Shirt',
            'Sparkles',
            'Footprints',
            'Watch',
            'BadgePercent',
            'Warehouse',
        ],
    },
    tech: {
        label: 'Tech & IT',
        icon: 'Server',
        items: [
            'Server',
            'Database',
            'Cpu',
            'HardDrive',
            'Cloud',
            'Globe',
            'Wifi',
            'Code',
            'Terminal',
            'Laptop',
            'Monitor',
            'Smartphone',
            'Radio',
            'Router',
            'Shield',
            'Key',
            'Lock',
            'Settings',
            'Wrench',
            'Layers',
            'Zap',
            'GitBranch',
            'TerminalSquare',
            'Bug',
            'Cable',
            'Webhook',
        ],
    },
    office: {
        label: 'Work & Files',
        icon: 'Folder',
        items: [
            'Folder',
            'FolderOpen',
            'FolderTree',
            'FileText',
            'File',
            'Files',
            'Briefcase',
            'Archive',
            'Clipboard',
            'ClipboardList',
            'BookOpen',
            'Book',
            'Bookmark',
            'Calendar',
            'Clock',
            'PenTool',
            'CheckSquare',
            'ListTodo',
            'Inbox',
            'Paperclip',
            'Presentation',
        ],
    },
    living: {
        label: 'Lifestyle',
        icon: 'Home',
        items: [
            'Home',
            'Building',
            'Building2',
            'Car',
            'Bus',
            'Plane',
            'Fuel',
            'Coffee',
            'Utensils',
            'UtensilsCrossed',
            'Pizza',
            'Apple',
            'Heart',
            'Activity',
            'Dumbbell',
            'Music',
            'Film',
            'Camera',
            'Luggage',
            'MapPin',
            'Compass',
            'Palmtree',
            'Bed',
            'Train',
        ],
    },
    people: {
        label: 'People',
        icon: 'Users',
        items: [
            'Users',
            'User',
            'UserCheck',
            'UserPlus',
            'Mail',
            'MessageSquare',
            'MessagesSquare',
            'Bell',
            'Phone',
            'Share2',
            'Megaphone',
            'Send',
            'ThumbsUp',
            'Star',
            'Award',
            'Trophy',
            'Crown',
            'Smile',
            'ShieldCheck',
        ],
    },
    tools: {
        label: 'Tools & Media',
        icon: 'Wrench',
        items: [
            'Wrench',
            'Hammer',
            'Sliders',
            'Search',
            'Filter',
            'Image',
            'Video',
            'Mic',
            'Volume2',
            'Printer',
            'Anchor',
            'Flame',
            'Sun',
            'Moon',
            'Eye',
            'LifeBuoy',
        ],
    },
};

// Keyword / synonym tag map
const searchTags: Record<string, string[]> = {
    money: [
        'Wallet',
        'Banknote',
        'Coins',
        'DollarSign',
        'PiggyBank',
        'CreditCard',
        'HandCoins',
        'Landmark',
        'Receipt',
    ],
    cash: [
        'Wallet',
        'Banknote',
        'Coins',
        'DollarSign',
        'PiggyBank',
        'HandCoins',
    ],
    cart: ['ShoppingCart', 'ShoppingBag', 'Store', 'Package', 'Box', 'Tag'],
    shop: [
        'ShoppingBag',
        'ShoppingCart',
        'Store',
        'Tag',
        'Tags',
        'Gift',
        'Barcode',
    ],
    food: ['Utensils', 'UtensilsCrossed', 'Coffee', 'Pizza', 'Apple'],
    eat: ['Utensils', 'UtensilsCrossed', 'Pizza', 'Apple', 'Coffee'],
    drink: ['Coffee'],
    car: ['Car', 'Bus', 'Plane', 'Fuel', 'Truck'],
    vehicle: ['Car', 'Bus', 'Plane', 'Truck', 'Train'],
    house: ['Home', 'Building', 'Building2'],
    rent: ['Home', 'Building', 'Key', 'Receipt'],
    bill: ['Receipt', 'ReceiptText', 'FileText', 'Calculator'],
    salary: ['TrendingUp', 'Wallet', 'Banknote', 'HandCoins', 'ArrowDownLeft'],
    income: [
        'TrendingUp',
        'Wallet',
        'Banknote',
        'ArrowDownLeft',
        'CircleDollarSign',
    ],
    expense: [
        'TrendingDown',
        'CreditCard',
        'Receipt',
        'ArrowUpRight',
        'Percent',
    ],
    tax: ['Receipt', 'Scale', 'Landmark', 'Calculator', 'FileText'],
    code: ['Code', 'Terminal', 'GitBranch', 'Laptop', 'Cpu', 'TerminalSquare'],
    dev: ['Code', 'Terminal', 'Server', 'Database', 'GitBranch', 'Cpu'],
    web: ['Globe', 'Wifi', 'Server', 'Cloud', 'Code'],
    cloud: ['Cloud', 'Server', 'Database', 'Globe'],
    security: ['Shield', 'ShieldCheck', 'Lock', 'Key', 'Eye'],
    auth: ['Shield', 'Lock', 'Key', 'UserCheck'],
    travel: ['Plane', 'Luggage', 'MapPin', 'Compass', 'Palmtree', 'Ticket'],
    gym: ['Dumbbell', 'Activity', 'Heart'],
    health: ['Heart', 'Activity', 'Dumbbell'],
    book: ['Book', 'BookOpen', 'Bookmark', 'Archive'],
    learn: ['Book', 'BookOpen', 'GraduationCap'],
    mail: ['Mail', 'Send', 'Inbox'],
    message: ['MessageSquare', 'MessagesSquare', 'Send', 'Phone'],
    chat: ['MessageSquare', 'MessagesSquare'],
};

// Safe icon component resolver
const getLucideIcon = (name: string | undefined | null) => {
    return getCategoryIcon(name, Folder);
};

// Reset pagination when search or category changes
watch([searchQuery, activeCategory], () => {
    visibleCount.value = 60;
});

// All matching icon names based on search and category
const allFilteredIcons = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (!query) {
        if (activeCategory.value === 'all') {
            return allLucideIconNames;
        }
        return (
            categories[activeCategory.value]?.items || categories.popular.items
        );
    }

    const matchedSet = new Set<string>();

    // 1. Check keyword tags first
    Object.entries(searchTags).forEach(([tag, icons]) => {
        if (tag.includes(query) || query.includes(tag)) {
            icons.forEach((icon) => matchedSet.add(icon));
        }
    });

    // 2. Search all 2,000+ Lucide icons with substring match
    for (const name of allLucideIconNames) {
        if (name.toLowerCase().includes(query)) {
            matchedSet.add(name);
        }
    }

    return Array.from(matchedSet);
});

// Sliced list for smooth rendering
const displayedIcons = computed(() => {
    return allFilteredIcons.value.slice(0, visibleCount.value);
});

// Infinite scroll handler
const handleScroll = (event: Event) => {
    const target = event.target as HTMLElement;
    if (target.scrollTop + target.clientHeight >= target.scrollHeight - 40) {
        if (visibleCount.value < allFilteredIcons.value.length) {
            visibleCount.value += 60;
        }
    }
};

// Load more manually if desired
const loadMore = () => {
    visibleCount.value += 60;
};

// Select an icon
const selectIcon = (iconName: string) => {
    emit('update:modelValue', iconName);
    isOpen.value = false;
    searchQuery.value = '';
};

// Reset to default
const resetDefault = () => {
    selectIcon('Folder');
};
</script>

<template>
    <div class="relative w-full">
        <Popover v-model:open="isOpen">
            <PopoverTrigger as-child>
                <button
                    type="button"
                    :disabled="disabled"
                    class="group relative flex h-9 w-full cursor-pointer items-center justify-between gap-2.5 rounded-md border border-input bg-transparent px-2.5 text-sm shadow-xs transition-colors hover:border-accent-foreground/20 hover:bg-accent/40 focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <div class="flex min-w-0 items-center gap-2.5">
                        <!-- Icon Preview Box with tinted category color -->
                        <div
                            class="flex size-6 shrink-0 items-center justify-center rounded text-white shadow-xs transition-transform group-hover:scale-105"
                            :style="{ backgroundColor: color || '#6366f1' }"
                        >
                            <component
                                :is="getLucideIcon(modelValue)"
                                class="size-3.5 stroke-[2.2]"
                            />
                        </div>

                        <!-- Icon Info -->
                        <div class="flex min-w-0 items-center gap-1.5">
                            <span
                                class="truncate text-sm font-medium text-foreground"
                            >
                                {{ modelValue || placeholder }}
                            </span>
                            <span
                                class="hidden truncate text-[11px] text-muted-foreground sm:inline"
                            >
                                (click to choose)
                            </span>
                        </div>
                    </div>

                    <!-- Action hint -->
                    <div
                        class="flex shrink-0 items-center gap-1 text-muted-foreground group-hover:text-foreground"
                    >
                        <ChevronDown
                            class="size-4 transition-transform duration-200"
                            :class="{ 'rotate-180': isOpen }"
                        />
                    </div>
                </button>
            </PopoverTrigger>

            <PopoverContent
                class="w-[360px] rounded-xl border border-border bg-popover p-0 text-popover-foreground shadow-2xl sm:w-[460px]"
                side="bottom"
                align="start"
                :side-offset="6"
            >
                <div class="flex flex-col divide-y divide-border/60">
                    <!-- Search Header -->
                    <div class="bg-muted/20 p-3">
                        <div class="relative flex items-center">
                            <Search
                                class="pointer-events-none absolute left-3 size-4 text-muted-foreground"
                            />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search 2,000+ icons (e.g. wallet, server, chart, user)..."
                                class="h-9 border-border/70 bg-background pr-8 pl-9 text-sm focus-visible:ring-1"
                                autofocus
                            />
                            <button
                                v-if="searchQuery"
                                type="button"
                                @click="searchQuery = ''"
                                class="absolute right-2.5 rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                title="Clear search"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>

                        <!-- Category Filter Pills -->
                        <div
                            class="no-scrollbar mt-2.5 flex items-center gap-1 overflow-x-auto pb-1 text-xs"
                        >
                            <button
                                type="button"
                                @click="activeCategory = 'all'"
                                :class="[
                                    'shrink-0 cursor-pointer rounded-md px-2.5 py-1 font-medium transition-colors',
                                    activeCategory === 'all' && !searchQuery
                                        ? 'bg-primary text-primary-foreground shadow-xs'
                                        : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground',
                                ]"
                            >
                                All Icons ({{ allLucideIconNames.length }})
                            </button>
                            <button
                                v-for="(cat, key) in categories"
                                :key="key"
                                type="button"
                                @click="
                                    activeCategory = key as string;
                                    searchQuery = '';
                                "
                                :class="[
                                    'shrink-0 cursor-pointer rounded-md px-2.5 py-1 font-medium transition-colors',
                                    activeCategory === key && !searchQuery
                                        ? 'bg-primary text-primary-foreground shadow-xs'
                                        : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground',
                                ]"
                            >
                                {{ cat.label }}
                            </button>
                        </div>

                        <!-- Filter Status Counter -->
                        <div
                            class="mt-2 flex items-center justify-between text-[11px] text-muted-foreground"
                        >
                            <span>
                                Showing {{ displayedIcons.length }} of
                                {{ allFilteredIcons.length }} icons
                            </span>
                            <span
                                v-if="searchQuery"
                                class="font-medium text-foreground"
                            >
                                Filter: "{{ searchQuery }}"
                            </span>
                        </div>
                    </div>

                    <!-- Scrollable Icon Grid Container -->
                    <div class="p-2">
                        <div
                            v-if="displayedIcons.length > 0"
                            class="grid max-h-[280px] grid-cols-4 gap-1.5 overflow-y-auto pr-1 sm:grid-cols-5"
                            @scroll="handleScroll"
                        >
                            <button
                                v-for="iconName in displayedIcons"
                                :key="iconName"
                                type="button"
                                @click="selectIcon(iconName)"
                                @mouseenter="hoveredIcon = iconName"
                                @mouseleave="hoveredIcon = null"
                                :title="iconName"
                                :class="[
                                    'group relative flex h-14 cursor-pointer flex-col items-center justify-center rounded-lg border p-1 transition-all',
                                    modelValue === iconName
                                        ? 'border-primary bg-primary/10 text-primary shadow-xs ring-2 ring-primary/30'
                                        : 'border-border/40 text-muted-foreground hover:border-border hover:bg-accent hover:text-foreground',
                                ]"
                            >
                                <component
                                    :is="getLucideIcon(iconName)"
                                    class="size-5 shrink-0 stroke-[1.8] transition-transform group-hover:scale-110"
                                />
                                <span
                                    class="mt-1 max-w-full truncate px-1 text-center text-[9.5px] leading-none font-medium"
                                    :class="
                                        modelValue === iconName
                                            ? 'text-primary'
                                            : 'text-muted-foreground group-hover:text-foreground'
                                    "
                                >
                                    {{ iconName }}
                                </span>
                                <!-- Selected indicator badge -->
                                <span
                                    v-if="modelValue === iconName"
                                    class="absolute top-1 right-1 size-1.5 rounded-full bg-primary"
                                />
                            </button>
                        </div>

                        <!-- Load more indicator button if not all shown -->
                        <div
                            v-if="
                                displayedIcons.length < allFilteredIcons.length
                            "
                            class="pt-2 text-center"
                        >
                            <button
                                type="button"
                                @click="loadMore"
                                class="cursor-pointer text-xs font-medium text-primary hover:underline"
                            >
                                Scroll down or click to load more (+60)
                            </button>
                        </div>

                        <!-- Empty Search State -->
                        <div
                            v-if="displayedIcons.length === 0"
                            class="flex flex-col items-center justify-center px-4 py-8 text-center text-muted-foreground"
                        >
                            <Folder
                                class="mb-2 size-8 stroke-[1.5] text-muted-foreground/50"
                            />
                            <p class="text-sm font-medium text-foreground">
                                No icons found
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                No match for "{{ searchQuery }}"
                            </p>
                            <Button
                                v-if="searchQuery"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="mt-3 text-xs"
                                @click="selectIcon(searchQuery)"
                            >
                                Use "{{ searchQuery }}" anyway
                            </Button>
                        </div>
                    </div>

                    <!-- Footer Preview & Actions -->
                    <div
                        class="flex items-center justify-between bg-muted/20 px-3 py-2 text-xs"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="text-muted-foreground">Preview:</span>
                            <div
                                class="flex items-center gap-1.5 truncate font-medium text-foreground"
                            >
                                <component
                                    :is="
                                        getLucideIcon(hoveredIcon || modelValue)
                                    "
                                    class="size-4 shrink-0 text-primary"
                                />
                                <span class="truncate font-mono">{{
                                    hoveredIcon || modelValue || 'None'
                                }}</span>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-1.5">
                            <Button
                                v-if="modelValue !== 'Folder'"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
                                @click="resetDefault"
                                title="Reset to default Folder icon"
                            >
                                <RotateCcw class="mr-1 size-3" />
                                Reset
                            </Button>
                        </div>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
