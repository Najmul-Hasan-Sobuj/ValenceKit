<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { ArrowUp } from '@lucide/vue';

const props = withDefaults(
    defineProps<{
        threshold?: number;
        class?: string;
    }>(),
    {
        threshold: 300,
        class: '',
    },
);

const isVisible = ref(false);

const checkScroll = () => {
    if (typeof window !== 'undefined') {
        isVisible.value = window.scrollY > props.threshold;
    }
};

const scrollToTop = () => {
    if (typeof window !== 'undefined') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('scroll', checkScroll, { passive: true });
        checkScroll();
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('scroll', checkScroll);
    }
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-3 scale-95"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 translate-y-3 scale-95"
    >
        <button
            v-if="isVisible"
            type="button"
            @click="scrollToTop"
            class="fixed right-6 bottom-6 z-40 flex size-10 cursor-pointer items-center justify-center rounded-full border border-border bg-background/90 text-muted-foreground shadow-md backdrop-blur-xs transition-colors hover:border-foreground/30 hover:bg-accent hover:text-foreground active:scale-95 sm:size-11"
            :class="props.class"
            aria-label="Scroll to top"
            title="Scroll to top"
        >
            <ArrowUp class="size-4 sm:size-5" aria-hidden="true" />
            <span class="sr-only">Scroll to top</span>
        </button>
    </Transition>
</template>
