<script setup lang="ts">
import { computed } from 'vue';
import { AlertTriangle, Trash2 } from '@lucide/vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';

interface Props {
    open?: boolean;
    title?: string;
    description?: string;
    itemName?: string;
    itemType?: string;
    confirmText?: string;
    cancelText?: string;
    loading?: boolean;
    destructive?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    title: '',
    description: '',
    itemName: '',
    itemType: '',
    confirmText: 'Delete',
    cancelText: 'Cancel',
    loading: false,
    destructive: true,
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'confirm'): void;
    (e: 'cancel'): void;
}>();

const computedTitle = computed(() => {
    if (props.title) return props.title;
    if (props.itemName) return `Delete ${props.itemName}?`;
    if (props.itemType) return `Delete this ${props.itemType.toLowerCase()}?`;
    return 'Confirm Deletion';
});

const computedDescription = computed(() => {
    if (props.description) return props.description;
    return 'This action cannot be undone. This will permanently remove this record and revoke all associated access and permissions.';
});

function handleConfirm(): void {
    emit('confirm');
}

function handleCancel(): void {
    emit('cancel');
    emit('update:open', false);
}
</script>

<template>
    <AlertDialog :open="open" @update:open="(val: boolean) => emit('update:open', val)">
        <AlertDialogContent class="max-w-md p-6 sm:max-w-[440px]">
            <AlertDialogHeader class="gap-3 sm:text-left">
                <div class="flex items-start gap-3.5">
                    <div
                        class="flex size-11 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-950/60 text-red-600 dark:text-red-400 ring-4 ring-red-50 dark:ring-red-950/30"
                    >
                        <Trash2 class="size-5" />
                    </div>
                    <div class="space-y-1 pt-0.5">
                        <AlertDialogTitle class="text-base font-semibold leading-6 text-foreground">
                            <slot name="title">
                                {{ computedTitle }}
                            </slot>
                        </AlertDialogTitle>
                        <AlertDialogDescription class="text-xs text-muted-foreground leading-relaxed">
                            <slot name="description">
                                {{ computedDescription }}
                            </slot>
                        </AlertDialogDescription>
                    </div>
                </div>
            </AlertDialogHeader>

            <!-- Item preview pill if itemName provided -->
            <div
                v-if="itemName"
                class="flex items-center gap-2.5 rounded-lg border border-destructive/20 bg-destructive/5 px-3 py-2 text-xs"
            >
                <div class="size-1.5 rounded-full bg-destructive animate-pulse shrink-0" />
                <span class="font-medium text-muted-foreground uppercase tracking-wider text-[10px]">
                    {{ itemType || 'Target' }}:
                </span>
                <span class="font-medium text-foreground truncate">
                    {{ itemName }}
                </span>
            </div>

            <!-- Optional custom slot -->
            <slot />

            <AlertDialogFooter class="mt-3 gap-2 sm:gap-2">
                <AlertDialogCancel as-child>
                    <Button
                        variant="outline"
                        :disabled="loading"
                        @click="handleCancel"
                    >
                        {{ cancelText }}
                    </Button>
                </AlertDialogCancel>
                <AlertDialogAction as-child>
                    <Button
                        :variant="destructive ? 'destructive' : 'default'"
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90 shadow-xs gap-1.5 focus-visible:ring-destructive font-medium cursor-pointer"
                        :disabled="loading"
                        @click="handleConfirm"
                    >
                        <Trash2 class="size-4" />
                        <span>{{ confirmText }}</span>
                    </Button>
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
