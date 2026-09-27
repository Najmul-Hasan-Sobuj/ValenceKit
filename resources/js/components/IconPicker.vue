<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import * as LucideIcons from '@lucide/vue';
import {
    Search,
    X,
    ChevronDown,
    RotateCcw,
    Folder,
    Sparkles,
} from '@lucide/vue';
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

// Extract all 2,000+ clean PascalCase Lucide icon names sorted alphabetically
const excluded = new Set(['createLucideIcon', 'default']);
const allLucideIconNames: string[] = Object.keys(LucideIcons)
    .filter(
        (key) =>
            !key.endsWith('Icon') &&
            !key.startsWith('Lucide') &&
            typeof (LucideIcons as any)[key] === 'function' &&
            /^[A-Z]/.test(key) &&
            !excluded.has(key)
    )
    .sort();

// Curated categories for fast discovery
const categories: Record<string, { label: string; icon: string; items: string[] }> = {
    popular: {
        label: 'Popular',
        icon: 'Sparkles',
        items: [
            'Folder', 'FolderOpen', 'Wallet', 'CreditCard', 'ShoppingBag', 'ShoppingCart',
            'Tag', 'Package', 'Server', 'Database', 'Laptop', 'Code', 'Home', 'Car',
            'Coffee', 'Utensils', 'Heart', 'Users', 'Star', 'Zap', 'Settings', 'FileText', 'Briefcase',
        ],
    },
    finance: {
        label: 'Finance',
        icon: 'Wallet',
        items: [
            'Wallet', 'CreditCard', 'Banknote', 'Coins', 'DollarSign', 'PiggyBank',
            'Receipt', 'ReceiptText', 'Scale', 'Landmark', 'HandCoins', 'BadgeDollarSign',
            'Calculator', 'Percent', 'TrendingUp', 'TrendingDown', 'CircleDollarSign',
            'ChartLine', 'ChartPie', 'ChartBar', 'ArrowUpRight', 'ArrowDownLeft',
        ],
    },
    commerce: {
        label: 'Commerce',
        icon: 'ShoppingBag',
        items: [
            'ShoppingBag', 'ShoppingCart', 'Store', 'Package', 'Box', 'Boxes',
            'Tag', 'Tags', 'Gift', 'Truck', 'Barcode', 'QrCode', 'Ticket',
            'Shirt', 'Sparkles', 'Footprints', 'Watch', 'BadgePercent', 'Warehouse',
        ],
    },
    tech: {
        label: 'Tech & IT',
        icon: 'Server',
        items: [
            'Server', 'Database', 'Cpu', 'HardDrive', 'Cloud', 'Globe', 'Wifi',
            'Code', 'Terminal', 'Laptop', 'Monitor', 'Smartphone', 'Radio', 'Router',
            'Shield', 'Key', 'Lock', 'Settings', 'Wrench', 'Layers', 'Zap',
            'GitBranch', 'TerminalSquare', 'Bug', 'Cable', 'Webhook',
        ],
    },
    office: {
        label: 'Work & Files',
        icon: 'Folder',
        items: [
            'Folder', 'FolderOpen', 'FolderTree', 'FileText', 'File', 'Files',
            'Briefcase', 'Archive', 'Clipboard', 'ClipboardList', 'BookOpen', 'Book',
            'Bookmark', 'Calendar', 'Clock', 'PenTool', 'CheckSquare', 'ListTodo',
            'Inbox', 'Paperclip', 'Presentation',
        ],
    },
    living: {
        label: 'Lifestyle',
        icon: 'Home',
        items: [
            'Home', 'Building', 'Building2', 'Car', 'Bus', 'Plane', 'Fuel',
            'Coffee', 'Utensils', 'UtensilsCrossed', 'Pizza', 'Apple', 'Heart',
            'Activity', 'Dumbbell', 'Music', 'Film', 'Camera', 'Luggage',
            'MapPin', 'Compass', 'Palmtree', 'Bed', 'Train',
        ],
    },
    people: {
        label: 'People',
        icon: 'Users',
        items: [
            'Users', 'User', 'UserCheck', 'UserPlus', 'Mail', 'MessageSquare',
            'MessagesSquare', 'Bell', 'Phone', 'Share2', 'Megaphone', 'Send',
            'ThumbsUp', 'Star', 'Award', 'Trophy', 'Crown', 'Smile', 'ShieldCheck',
        ],
    },
    tools: {
        label: 'Tools & Media',
        icon: 'Wrench',
        items: [
            'Wrench', 'Hammer', 'Sliders', 'Search', 'Filter', 'Image', 'Video',
            'Mic', 'Volume2', 'Printer', 'Anchor', 'Flame', 'Sun', 'Moon',
            'Eye', 'LifeBuoy',
        ],
    },
};

// Keyword / synonym tag map
const searchTags: Record<string, string[]> = {
    money: ['Wallet', 'Banknote', 'Coins', 'DollarSign', 'PiggyBank', 'CreditCard', 'HandCoins', 'Landmark', 'Receipt'],
    cash: ['Wallet', 'Banknote', 'Coins', 'DollarSign', 'PiggyBank', 'HandCoins'],
    cart: ['ShoppingCart', 'ShoppingBag', 'Store', 'Package', 'Box', 'Tag'],
    shop: ['ShoppingBag', 'ShoppingCart', 'Store', 'Tag', 'Tags', 'Gift', 'Barcode'],
    food: ['Utensils', 'UtensilsCrossed', 'Coffee', 'Pizza', 'Apple'],
    eat: ['Utensils', 'UtensilsCrossed', 'Pizza', 'Apple', 'Coffee'],
    drink: ['Coffee'],
    car: ['Car', 'Bus', 'Plane', 'Fuel', 'Truck'],
    vehicle: ['Car', 'Bus', 'Plane', 'Truck', 'Train'],
    house: ['Home', 'Building', 'Building2'],
    rent: ['Home', 'Building', 'Key', 'Receipt'],
    bill: ['Receipt', 'ReceiptText', 'FileText', 'Calculator'],
    salary: ['TrendingUp', 'Wallet', 'Banknote', 'HandCoins', 'ArrowDownLeft'],
    income: ['TrendingUp', 'Wallet', 'Banknote', 'ArrowDownLeft', 'CircleDollarSign'],
    expense: ['TrendingDown', 'CreditCard', 'Receipt', 'ArrowUpRight', 'Percent'],
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
    if (!name) return Folder;
    return (LucideIcons as any)[name] || Folder;
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
        return categories[activeCategory.value]?.items || categories.popular.items;
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
                    class="group relative flex h-9 w-full items-center justify-between gap-2.5 rounded-md border border-input bg-transparent px-2.5 text-sm shadow-xs transition-colors hover:bg-accent/40 hover:border-accent-foreground/20 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer"
                >
                    <div class="flex items-center gap-2.5 min-w-0">
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
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="font-medium text-foreground text-sm truncate">
                                {{ modelValue || placeholder }}
                            </span>
                            <span class="text-[11px] text-muted-foreground hidden sm:inline truncate">
                                (click to choose)
                            </span>
                        </div>
                    </div>

                    <!-- Action hint -->
                    <div class="flex items-center gap-1 shrink-0 text-muted-foreground group-hover:text-foreground">
                        <ChevronDown class="size-4 transition-transform duration-200" :class="{ 'rotate-180': isOpen }" />
                    </div>
                </button>
            </PopoverTrigger>

            <PopoverContent
                class="w-[360px] sm:w-[460px] p-0 shadow-2xl rounded-xl border border-border bg-popover text-popover-foreground"
                side="bottom"
                align="start"
                :side-offset="6"
            >
                <div class="flex flex-col divide-y divide-border/60">
                    <!-- Search Header -->
                    <div class="p-3 bg-muted/20">
                        <div class="relative flex items-center">
                            <Search class="absolute left-3 size-4 text-muted-foreground pointer-events-none" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search 2,000+ icons (e.g. wallet, server, chart, user)..."
                                class="pl-9 pr-8 h-9 text-sm bg-background border-border/70 focus-visible:ring-1"
                                autofocus
                            />
                            <button
                                v-if="searchQuery"
                                type="button"
                                @click="searchQuery = ''"
                                class="absolute right-2.5 p-0.5 rounded-full text-muted-foreground hover:text-foreground hover:bg-muted"
                                title="Clear search"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>

                        <!-- Category Filter Pills -->
                        <div class="mt-2.5 flex items-center gap-1 overflow-x-auto pb-1 no-scrollbar text-xs">
                            <button
                                type="button"
                                @click="activeCategory = 'all'"
                                :class="[
                                    'px-2.5 py-1 rounded-md font-medium transition-colors shrink-0 cursor-pointer',
                                    activeCategory === 'all' && !searchQuery
                                        ? 'bg-primary text-primary-foreground shadow-xs'
                                        : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground'
                                ]"
                            >
                                All Icons ({{ allLucideIconNames.length }})
                            </button>
                            <button
                                v-for="(cat, key) in categories"
                                :key="key"
                                type="button"
                                @click="activeCategory = key as string; searchQuery = ''"
                                :class="[
                                    'px-2.5 py-1 rounded-md font-medium transition-colors shrink-0 cursor-pointer',
                                    activeCategory === key && !searchQuery
                                        ? 'bg-primary text-primary-foreground shadow-xs'
                                        : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground'
                                ]"
                            >
                                {{ cat.label }}
                            </button>
                        </div>

                        <!-- Filter Status Counter -->
                        <div class="mt-2 flex items-center justify-between text-[11px] text-muted-foreground">
                            <span>
                                Showing {{ displayedIcons.length }} of {{ allFilteredIcons.length }} icons
                            </span>
                            <span v-if="searchQuery" class="font-medium text-foreground">
                                Filter: "{{ searchQuery }}"
                            </span>
                        </div>
                    </div>

                    <!-- Scrollable Icon Grid Container -->
                    <div class="p-2">
                        <div
                            v-if="displayedIcons.length > 0"
                            class="grid grid-cols-4 sm:grid-cols-5 gap-1.5 max-h-[280px] overflow-y-auto pr-1"
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
                                    'group relative flex flex-col items-center justify-center h-14 rounded-lg border transition-all cursor-pointer p-1',
                                    modelValue === iconName
                                        ? 'border-primary bg-primary/10 text-primary ring-2 ring-primary/30 shadow-xs'
                                        : 'border-border/40 text-muted-foreground hover:border-border hover:bg-accent hover:text-foreground'
                                ]"
                            >
                                <component
                                    :is="getLucideIcon(iconName)"
                                    class="size-5 stroke-[1.8] shrink-0 transition-transform group-hover:scale-110"
                                />
                                <span class="text-[9.5px] mt-1 truncate max-w-full px-1 text-center font-medium leading-none" :class="modelValue === iconName ? 'text-primary' : 'text-muted-foreground group-hover:text-foreground'">
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
                            v-if="displayedIcons.length < allFilteredIcons.length"
                            class="pt-2 text-center"
                        >
                            <button
                                type="button"
                                @click="loadMore"
                                class="text-xs text-primary hover:underline font-medium cursor-pointer"
                            >
                                Scroll down or click to load more (+60)
                            </button>
                        </div>

                        <!-- Empty Search State -->
                        <div
                            v-if="displayedIcons.length === 0"
                            class="py-8 px-4 flex flex-col items-center justify-center text-center text-muted-foreground"
                        >
                            <Folder class="size-8 stroke-[1.5] mb-2 text-muted-foreground/50" />
                            <p class="text-sm font-medium text-foreground">No icons found</p>
                            <p class="text-xs text-muted-foreground mt-0.5">
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
                    <div class="px-3 py-2 bg-muted/20 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-muted-foreground">Preview:</span>
                            <div class="flex items-center gap-1.5 font-medium text-foreground truncate">
                                <component
                                    :is="getLucideIcon(hoveredIcon || modelValue)"
                                    class="size-4 shrink-0 text-primary"
                                />
                                <span class="truncate font-mono">{{ hoveredIcon || modelValue || 'None' }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0">
                            <Button
                                v-if="modelValue !== 'Folder'"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
                                @click="resetDefault"
                                title="Reset to default Folder icon"
                            >
                                <RotateCcw class="size-3 mr-1" />
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
