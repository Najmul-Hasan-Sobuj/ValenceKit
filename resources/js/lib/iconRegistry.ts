import { defineAsyncComponent, type Component } from 'vue';
import { Folder, HelpCircle } from '@lucide/vue';

export const fallbackIcon: Component = HelpCircle;

export const allSupportedIconNames: string[] = [
    'Activity',
    'Anchor',
    'Apple',
    'Archive',
    'ArrowDownLeft',
    'ArrowUpRight',
    'Award',
    'BadgeDollarSign',
    'BadgePercent',
    'Banknote',
    'Barcode',
    'Bed',
    'Bell',
    'Book',
    'BookOpen',
    'Bookmark',
    'Box',
    'Boxes',
    'Briefcase',
    'Bug',
    'Building',
    'Building2',
    'Bus',
    'Cable',
    'Calculator',
    'Calendar',
    'Camera',
    'Car',
    'ChartBar',
    'ChartLine',
    'ChartPie',
    'CheckSquare',
    'CircleDollarSign',
    'Clipboard',
    'ClipboardList',
    'Clock',
    'Cloud',
    'Code',
    'Coffee',
    'Coins',
    'Compass',
    'Cpu',
    'CreditCard',
    'Crown',
    'Database',
    'DollarSign',
    'Dumbbell',
    'Eye',
    'File',
    'FileText',
    'Files',
    'Film',
    'Filter',
    'Flame',
    'Folder',
    'FolderOpen',
    'FolderTree',
    'Footprints',
    'Fuel',
    'Gift',
    'GitBranch',
    'Globe',
    'GraduationCap',
    'Hammer',
    'HandCoins',
    'HardDrive',
    'Heart',
    'HelpCircle',
    'Home',
    'Image',
    'Inbox',
    'Key',
    'Landmark',
    'Laptop',
    'Layers',
    'LifeBuoy',
    'ListTodo',
    'Lock',
    'Luggage',
    'Mail',
    'MapPin',
    'Megaphone',
    'MessageSquare',
    'MessagesSquare',
    'Mic',
    'Monitor',
    'Moon',
    'Music',
    'Package',
    'Palmtree',
    'Paperclip',
    'PenTool',
    'Percent',
    'Phone',
    'PiggyBank',
    'Pizza',
    'Plane',
    'Presentation',
    'Printer',
    'QrCode',
    'Radio',
    'Receipt',
    'ReceiptText',
    'Router',
    'Scale',
    'Search',
    'Send',
    'Server',
    'Settings',
    'Share2',
    'Shield',
    'ShieldCheck',
    'Shirt',
    'ShoppingBag',
    'ShoppingCart',
    'Sliders',
    'Smartphone',
    'Smile',
    'Sparkles',
    'Star',
    'Store',
    'Sun',
    'Tag',
    'Tags',
    'Terminal',
    'TerminalSquare',
    'ThumbsUp',
    'Ticket',
    'Train',
    'TrendingDown',
    'TrendingUp',
    'Trophy',
    'Truck',
    'User',
    'UserCheck',
    'UserPlus',
    'Users',
    'Utensils',
    'UtensilsCrossed',
    'Video',
    'Volume2',
    'Wallet',
    'Warehouse',
    'Watch',
    'Webhook',
    'Wifi',
    'Wrench',
    'Zap',
];

const iconCache = new Map<string, Component>();

/**
 * Resolve an icon component dynamically using defineAsyncComponent.
 * Falls back to HelpCircle (or provided fallback) for unknown or legacy names.
 */
export function getCategoryIcon(
    name: string | null | undefined,
    defaultFallback: Component = fallbackIcon,
): Component {
    if (!name) {
        return defaultFallback;
    }

    if (name === 'Folder') {
        return Folder;
    }

    if (name === 'HelpCircle') {
        return HelpCircle;
    }

    const cached = iconCache.get(name);
    if (cached) {
        return cached;
    }

    const component = defineAsyncComponent(async () => {
        try {
            const icons = await import('@lucide/vue');
            const icon = (icons as Record<string, any>)[name];
            return icon || defaultFallback;
        } catch {
            return defaultFallback;
        }
    });

    iconCache.set(name, component);
    return component;
}
