<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { Images, HardDrive, Link2, FolderOpen, Search, X } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import MediaManager from '@/components/MediaManager.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/admin/media';
import { decodePaginationLabel } from '@/lib/decodePaginationLabel';
import { formatMediaSize, type MediaItem } from '@/types/media';

const props = defineProps<{
    media: {
        data: MediaItem[];
        links: { url: string | null; label: string; active: boolean }[];
        meta: {
            from: number | null;
            to: number | null;
            total: number;
            last_page: number;
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
    filters: { search?: string; folder?: string; usage?: string };
    folders: string[];
    stats: { total: number; size: number; attached: number };
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Media Library', href: index.url() }] },
});
const search = ref(props.filters.search ?? '');
function filter(
    folder = props.filters.folder ?? '',
    usage = props.filters.usage ?? 'all',
) {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            folder: folder || undefined,
            usage,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
watchDebounced(search, () => filter(), { debounce: 350 });
</script>

<template>
    <div class="space-y-6 p-4 sm:p-6">
        <Head title="Media Library" />
        <Heading
            title="Media Library"
            description="Your images, organized. Upload once, keep every detail in one place."
        />
        <div class="grid gap-3 sm:grid-cols-3">
            <div
                v-for="stat in [
                    { icon: Images, label: 'Images', value: stats.total },
                    {
                        icon: HardDrive,
                        label: 'Storage used',
                        value: formatMediaSize(stats.size),
                    },
                    {
                        icon: Link2,
                        label: 'Attached to records',
                        value: stats.attached,
                    },
                ]"
                :key="stat.label"
                class="flex items-center gap-3 rounded-xl border bg-card px-4 py-3"
            >
                <component
                    :is="stat.icon"
                    class="size-5 text-muted-foreground"
                />
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ stat.label }}
                    </p>
                    <p class="text-lg font-semibold tabular-nums">
                        {{ stat.value }}
                    </p>
                </div>
            </div>
        </div>
        <div class="grid items-start gap-5 md:grid-cols-[180px_minmax(0,1fr)]">
            <aside
                class="space-y-2 rounded-xl border bg-card p-3 md:sticky md:top-4"
                aria-label="Media folders"
            >
                <p
                    class="px-2 pb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Folders
                </p>
                <Button
                    variant="ghost"
                    class="w-full justify-start"
                    :class="!filters.folder ? 'bg-muted' : ''"
                    @click="filter('')"
                    ><Images class="size-4" /> All images</Button
                >
                <Button
                    v-for="folder in folders"
                    :key="folder"
                    variant="ghost"
                    class="w-full justify-start"
                    :class="filters.folder === folder ? 'bg-muted' : ''"
                    :title="folder"
                    @click="filter(folder)"
                    ><FolderOpen class="size-4 shrink-0" /><span
                        class="truncate"
                        >{{ folder }}</span
                    ></Button
                >
                <p
                    class="px-2 pt-2 text-xs leading-relaxed text-muted-foreground"
                >
                    Create a folder when uploading, or move an image in its
                    details.
                </p>
            </aside>
            <div class="min-w-0 space-y-5">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative min-w-48 flex-1">
                        <Search
                            class="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground"
                        /><Input
                            v-model="search"
                            aria-label="Search images"
                            placeholder="Search image name or alt text…"
                            class="pl-9"
                            maxlength="100"
                        />
                    </div>
                    <select
                        :value="filters.usage ?? 'all'"
                        aria-label="Filter image usage"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        @change="
                            filter(
                                filters.folder,
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option value="all">All usage</option>
                        <option value="attached">Attached to records</option>
                        <option value="unattached">Library only</option>
                    </select>
                    <Button
                        v-if="
                            filters.folder ||
                            search ||
                            (filters.usage && filters.usage !== 'all')
                        "
                        variant="ghost"
                        size="sm"
                        @click="
                            search = '';
                            filter('', 'all');
                        "
                        ><X class="size-4" /> Clear filters</Button
                    >
                </div>
                <MediaManager
                    :items="media.data"
                    :folder="filters.folder || 'General'"
                    :empty-message="
                        stats.total
                            ? 'No images match these filters. Try another search or folder.'
                            : 'Your library is ready. Upload your first images above.'
                    "
                />
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-t pt-4"
                >
                    <p class="text-sm text-muted-foreground">
                        {{
                            media.meta.total
                                ? `${media.meta.from}–${media.meta.to} of ${media.meta.total} images`
                                : '0 images'
                        }}
                    </p>
                    <nav
                        v-if="media.meta.last_page > 1"
                        class="flex flex-wrap gap-1"
                        aria-label="Media pagination"
                    >
                        <template
                            v-for="(link, i) in media.meta.links || media.links"
                            :key="i"
                            ><Button
                                v-if="link.url"
                                as-child
                                size="sm"
                                :variant="link.active ? 'secondary' : 'outline'"
                                ><Link
                                    :href="link.url"
                                    preserve-scroll
                                    :aria-current="
                                        link.active ? 'page' : undefined
                                    "
                                    >{{
                                        decodePaginationLabel(link.label)
                                    }}</Link
                                ></Button
                            ><Button
                                v-else
                                disabled
                                size="sm"
                                variant="outline"
                                >{{ decodePaginationLabel(link.label) }}</Button
                            ></template
                        >
                    </nav>
                </div>
            </div>
        </div>
    </div>
</template>
