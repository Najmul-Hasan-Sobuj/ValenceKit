<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    ImagePlus,
    Upload,
    X,
    MoreHorizontal,
    Pencil,
    Trash2,
    Image as ImageIcon,
    FolderOpen,
    Eye,
} from '@lucide/vue';
import {
    store,
    update,
    destroy,
} from '@/actions/App/Http/Controllers/Admin/MediaController';
import { usePermissions } from '@/composables/usePermissions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import {
    Drawer,
    DrawerContent,
    DrawerHeader,
    DrawerTitle,
    DrawerDescription,
    DrawerFooter,
} from '@/components/ui/drawer';
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogCancel,
} from '@/components/ui/alert-dialog';
import { formatMediaSize, type MediaItem } from '@/types/media';

const props = withDefaults(
    defineProps<{
        items: MediaItem[];
        multiple?: boolean;
        owner?: { type: 'employee'; id: number } | null;
        collection?: 'avatar' | 'gallery';
        folder?: string;
        editable?: boolean;
        emptyMessage?: string;
    }>(),
    {
        multiple: true,
        owner: null,
        collection: 'gallery',
        folder: 'General',
        editable: true,
        emptyMessage: 'No images yet. Upload your first image to get started.',
    },
);
const { can } = usePermissions();
const fileInput = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const selectionError = ref('');
const previews = ref<{ name: string; url: string }[]>([]);
const selected = ref<MediaItem | null>(null);
const deleting = ref<MediaItem | null>(null);
const uploadingAllowed = computed(
    () =>
        props.editable &&
        can('media.create') &&
        (props.multiple || !props.items.length || can('media.edit')),
);
const accept = 'image/jpeg,image/png,image/webp';
const upload = useForm({
    files: [] as File[],
    owner_type: props.owner?.type ?? null,
    owner_id: props.owner?.id ?? null,
    collection: props.collection,
    folder: props.folder,
});
const edit = useForm({
    _method: 'patch',
    name: '',
    alt: '',
    folder: '',
    file: null as File | null,
});
const removal = useForm({});
const replacementPreview = ref('');

function clearSelection() {
    previews.value.forEach((item) => URL.revokeObjectURL(item.url));
    previews.value = [];
    upload.files = [];
    selectionError.value = '';
    upload.clearErrors();
    if (fileInput.value) fileInput.value.value = '';
}
function choose(files: File[]) {
    if (upload.processing || !uploadingAllowed.value) return;
    clearSelection();
    const limit = props.multiple ? 10 : 1;
    if (files.length > limit) {
        selectionError.value = `Choose up to ${limit} image${limit > 1 ? 's' : ''} at a time.`;
        return;
    }
    if (
        files.some(
            (file) =>
                !accept.split(',').includes(file.type) ||
                file.size > 5 * 1024 * 1024,
        )
    ) {
        selectionError.value = 'Use JPG, PNG or WebP images, up to 5 MB each.';
        return;
    }
    upload.files = files;
    previews.value = files.map((file) => ({
        name: file.name,
        url: URL.createObjectURL(file),
    }));
}
function drop(event: DragEvent) {
    dragging.value = false;
    choose(Array.from(event.dataTransfer?.files ?? []));
}
function submitUpload() {
    upload.owner_type = props.owner?.type ?? null;
    upload.owner_id = props.owner?.id ?? null;
    upload.collection = props.collection;
    upload.post(store.url(), {
        preserveScroll: true,
        onSuccess: clearSelection,
    });
}
function inspect(item: MediaItem) {
    selected.value = item;
    edit.reset();
    edit.clearErrors();
    edit.name = item.name;
    edit.alt = item.alt ?? '';
    edit.folder = item.folder;
    if (replacementPreview.value) URL.revokeObjectURL(replacementPreview.value);
    replacementPreview.value = '';
}
function replace(event: Event) {
    edit.file = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (replacementPreview.value) URL.revokeObjectURL(replacementPreview.value);
    replacementPreview.value = edit.file ? URL.createObjectURL(edit.file) : '';
}
function save() {
    if (!selected.value) return;
    edit.post(update.url(selected.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = null;
        },
    });
}
function remove() {
    if (!deleting.value) return;
    removal.delete(destroy.url(deleting.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleting.value = null;
            selected.value = null;
        },
    });
}
watch(() => props.owner?.id, clearSelection);
watch(
    () => props.folder,
    (value) => {
        upload.folder = value;
    },
);
onBeforeUnmount(() => {
    previews.value.forEach((item) => URL.revokeObjectURL(item.url));
    if (replacementPreview.value) URL.revokeObjectURL(replacementPreview.value);
});
</script>

<template>
    <section class="space-y-5" aria-label="Image manager">
        <div
            v-if="uploadingAllowed"
            class="rounded-xl border border-dashed bg-card p-4 transition-colors sm:p-5"
            :class="dragging ? 'border-primary bg-primary/5' : 'border-border'"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="drop"
        >
            <div class="flex flex-wrap items-center gap-3">
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-muted"
                    ><ImagePlus class="size-5 text-muted-foreground"
                /></span>
                <div class="min-w-40 flex-1">
                    <p class="text-sm font-medium">
                        {{
                            multiple
                                ? 'Drop images here'
                                : items.length
                                  ? 'Replace profile photo'
                                  : 'Add a profile photo'
                        }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        JPG, PNG or WebP · 5 MB each{{
                            multiple ? ' · Up to 10 at once' : ''
                        }}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="upload.processing"
                    @click="fileInput?.click()"
                    ><Upload class="size-4" /> Choose
                    {{ multiple ? 'images' : 'image' }}</Button
                >
                <input
                    ref="fileInput"
                    type="file"
                    class="sr-only"
                    tabindex="-1"
                    aria-label="Choose images to upload"
                    :multiple="multiple"
                    :accept="accept"
                    :disabled="upload.processing"
                    @change="
                        choose(
                            Array.from(
                                ($event.target as HTMLInputElement).files ?? [],
                            ),
                        )
                    "
                />
            </div>
            <p
                v-if="!multiple && items.length"
                class="mt-3 text-xs text-muted-foreground"
            >
                Uploading a new photo replaces the current one.
            </p>
            <InputError class="mt-2" :message="selectionError" />
            <div v-if="previews.length" class="mt-4 space-y-4 border-t pt-4">
                <div class="flex gap-2 overflow-x-auto pb-1">
                    <div
                        v-for="(preview, i) in previews"
                        :key="preview.url"
                        class="w-20 shrink-0"
                    >
                        <img
                            :src="preview.url"
                            :alt="preview.name"
                            class="aspect-square w-20 rounded-lg border object-cover"
                        />
                        <p class="mt-1 truncate text-xs">{{ preview.name }}</p>
                        <InputError
                            :message="
                                upload.errors[
                                    `files.${i}` as keyof typeof upload.errors
                                ]
                            "
                        />
                    </div>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <div v-if="!owner" class="min-w-0 flex-1 space-y-1.5">
                        <Label>Save in folder</Label>
                        <Input
                            v-model="upload.folder"
                            maxlength="100"
                            aria-label="Upload folder"
                            :disabled="upload.processing"
                        />
                        <InputError :message="upload.errors.folder" />
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        :disabled="upload.processing"
                        @click="clearSelection"
                        ><X class="size-4" /> Clear</Button
                    >
                    <Button
                        type="button"
                        :disabled="upload.processing"
                        @click="submitUpload"
                        ><Upload class="size-4" />{{
                            upload.processing
                                ? 'Uploading…'
                                : `Upload ${previews.length} image${previews.length > 1 ? 's' : ''}`
                        }}</Button
                    >
                </div>
                <div
                    v-if="upload.progress"
                    role="progressbar"
                    :aria-valuenow="upload.progress.percentage"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label="Image upload progress"
                    class="h-1.5 overflow-hidden rounded-full bg-muted"
                >
                    <div
                        class="h-full bg-primary transition-all"
                        :style="{ width: `${upload.progress.percentage}%` }"
                    />
                </div>
            </div>
            <InputError
                v-for="(error, key) in upload.errors"
                :key="key"
                class="mt-2"
                :message="error"
            />
        </div>

        <div
            v-if="items.length"
            class="grid gap-4"
            :class="
                multiple
                    ? 'grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'
                    : 'max-w-xs grid-cols-1'
            "
        >
            <article
                v-for="item in items"
                :key="item.id"
                class="group min-w-0 overflow-hidden rounded-xl border bg-card shadow-xs"
            >
                <button
                    type="button"
                    :aria-label="`Preview ${item.name}`"
                    class="block aspect-[4/3] w-full overflow-hidden bg-muted/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    @click="inspect(item)"
                >
                    <img
                        :src="item.url"
                        :alt="item.alt || item.name"
                        loading="lazy"
                        class="h-full w-full object-contain p-2 transition-transform duration-200 group-hover:scale-[1.03]"
                    />
                </button>
                <div class="space-y-2 border-t p-3">
                    <div class="flex min-w-0 items-center gap-1">
                        <button
                            type="button"
                            class="min-w-0 flex-1 truncate text-left text-sm font-medium hover:underline"
                            :title="item.name"
                            @click="inspect(item)"
                        >
                            {{ item.name }}
                        </button>
                        <DropdownMenu
                            ><DropdownMenuTrigger as-child
                                ><Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    :aria-label="`Actions for ${item.name}`"
                                    ><MoreHorizontal
                                        class="size-4" /></Button></DropdownMenuTrigger
                            ><DropdownMenuContent align="end">
                                <DropdownMenuItem @select="inspect(item)"
                                    ><Eye class="size-4" /> Preview &
                                    details</DropdownMenuItem
                                >
                                <DropdownMenuItem
                                    v-if="editable && item.can_edit"
                                    @select="inspect(item)"
                                    ><Pencil class="size-4" /> Edit
                                    image</DropdownMenuItem
                                >
                                <DropdownMenuSeparator
                                    v-if="editable && item.can_delete"
                                />
                                <DropdownMenuItem
                                    v-if="editable && item.can_delete"
                                    variant="destructive"
                                    class="text-destructive focus:text-destructive"
                                    @select="
                                        deleting = item;
                                        removal.clearErrors();
                                    "
                                    ><Trash2 class="size-4" /> Delete
                                    image</DropdownMenuItem
                                >
                            </DropdownMenuContent></DropdownMenu
                        >
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ item.width }} × {{ item.height }} ·
                        {{ formatMediaSize(item.size) }}
                    </p>
                    <div
                        v-if="multiple"
                        class="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <FolderOpen class="size-3" /><span class="truncate">{{
                            item.folder
                        }}</span
                        ><Badge
                            v-if="item.owner"
                            variant="secondary"
                            class="max-w-full truncate text-[10px]"
                            >{{ item.owner }}</Badge
                        >
                    </div>
                </div>
            </article>
        </div>
        <div
            v-else
            class="rounded-xl border bg-muted/20 px-5 py-10 text-center"
        >
            <ImageIcon class="mx-auto mb-3 size-7 text-muted-foreground/60" />
            <p class="text-sm text-muted-foreground">{{ emptyMessage }}</p>
        </div>

        <Drawer
            :open="!!selected"
            @update:open="
                (value) => {
                    if (!value && !edit.processing) selected = null;
                }
            "
        >
            <DrawerContent
                class="max-h-[92vh] overflow-y-auto md:inset-x-auto md:right-2 md:left-64"
            >
                <DrawerHeader
                    ><div class="flex items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/20"
                            ><ImageIcon class="size-5"
                        /></span>
                        <div class="min-w-0">
                            <DrawerTitle class="break-words"
                                >Image details</DrawerTitle
                            ><DrawerDescription
                                >Preview, organize and update your
                                image.</DrawerDescription
                            >
                        </div>
                    </div></DrawerHeader
                >
                <div
                    v-if="selected"
                    class="grid gap-5 px-6 pb-6 lg:grid-cols-2"
                >
                    <div class="space-y-3">
                        <div
                            class="flex h-64 items-center justify-center rounded-xl border bg-muted/30 sm:h-80"
                        >
                            <img
                                :src="replacementPreview || selected.url"
                                :alt="selected.alt || selected.name"
                                class="h-full w-full object-contain p-3"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ selected.width }} × {{ selected.height }} ·
                            {{ formatMediaSize(selected.size) }} ·
                            {{ selected.mime_type }}
                        </p>
                        <p
                            v-if="selected.owner"
                            class="text-sm text-muted-foreground"
                        >
                            Used by {{ selected.owner }} ·
                            {{
                                selected.collection === 'avatar'
                                    ? 'Profile photo'
                                    : 'Gallery'
                            }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Uploaded
                            {{
                                new Date(
                                    selected.created_at,
                                ).toLocaleDateString()
                            }}
                        </p>
                    </div>
                    <form class="min-w-0 space-y-4" @submit.prevent.stop="save">
                        <div class="space-y-2">
                            <Label for="media-name">Image name</Label
                            ><Input
                                id="media-name"
                                v-model="edit.name"
                                maxlength="255"
                                :disabled="
                                    !editable ||
                                    !selected.can_edit ||
                                    edit.processing
                                "
                            /><InputError :message="edit.errors.name" />
                        </div>
                        <div class="space-y-2">
                            <Label for="media-alt">Alt text</Label
                            ><Input
                                id="media-alt"
                                v-model="edit.alt"
                                placeholder="Describe the image for accessibility"
                                maxlength="255"
                                :disabled="
                                    !editable ||
                                    !selected.can_edit ||
                                    edit.processing
                                "
                            /><InputError :message="edit.errors.alt" />
                        </div>
                        <div class="space-y-2">
                            <Label for="media-folder">Folder</Label
                            ><Input
                                id="media-folder"
                                v-model="edit.folder"
                                maxlength="100"
                                :disabled="
                                    !editable ||
                                    !selected.can_edit ||
                                    edit.processing
                                "
                            /><InputError :message="edit.errors.folder" />
                        </div>
                        <div
                            v-if="editable && selected.can_edit"
                            class="space-y-2 rounded-lg border border-dashed p-3"
                        >
                            <Label for="media-replacement"
                                >Replace image (optional)</Label
                            ><input
                                id="media-replacement"
                                type="file"
                                :accept="accept"
                                :disabled="edit.processing"
                                class="block w-full text-sm file:mr-3 file:rounded-md file:border file:bg-muted file:px-3 file:py-2"
                                @change="replace"
                            />
                            <p class="text-xs text-muted-foreground">
                                JPG, PNG or WebP · Up to 5 MB. Details and
                                association stay intact.
                            </p>
                            <InputError :message="edit.errors.file" />
                        </div>
                        <DrawerFooter
                            class="flex-row flex-wrap justify-end border-t px-0"
                            ><Button
                                type="button"
                                variant="outline"
                                :disabled="edit.processing"
                                @click="selected = null"
                                >Close</Button
                            ><Button
                                v-if="editable && selected.can_edit"
                                type="submit"
                                :disabled="edit.processing"
                                >{{
                                    edit.processing ? 'Saving…' : 'Save changes'
                                }}</Button
                            ></DrawerFooter
                        >
                    </form>
                </div>
            </DrawerContent>
        </Drawer>
        <AlertDialog
            :open="!!deleting"
            @update:open="
                (value) => {
                    if (!value && !removal.processing) deleting = null;
                }
            "
        >
            <AlertDialogContent class="sm:max-w-md"
                ><AlertDialogHeader
                    ><AlertDialogTitle>Delete this image?</AlertDialogTitle
                    ><AlertDialogDescription class="break-words"
                        >“{{ deleting?.name }}” will be permanently removed{{
                            deleting?.owner
                                ? ` from ${deleting.owner} and the media library`
                                : ' from the media library'
                        }}. This cannot be undone.</AlertDialogDescription
                    ></AlertDialogHeader
                ><InputError
                    v-for="(error, key) in removal.errors"
                    :key="key"
                    :message="error"
                /><AlertDialogFooter
                    ><AlertDialogCancel :disabled="removal.processing"
                        >Cancel</AlertDialogCancel
                    ><Button
                        type="button"
                        variant="destructive"
                        :disabled="removal.processing"
                        @click="remove"
                        >{{
                            removal.processing ? 'Deleting…' : 'Delete image'
                        }}</Button
                    ></AlertDialogFooter
                ></AlertDialogContent
            >
        </AlertDialog>
    </section>
</template>
