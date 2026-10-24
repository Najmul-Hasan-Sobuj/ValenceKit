<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

const props = withDefaults(
    defineProps<{
        hideScrollbar?: boolean;
        class?: string;
    }>(),
    {
        hideScrollbar: true,
        class: '',
    },
);

const scrollProgress = ref(0);

const updateScrollProgress = () => {
    const scrollableHeight =
        document.documentElement.scrollHeight - window.innerHeight;
    scrollProgress.value =
        scrollableHeight > 0
            ? Math.min(1, Math.max(0, window.scrollY / scrollableHeight))
            : 0;
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        if (props.hideScrollbar) {
            document.documentElement.classList.add('page-hide-scrollbar');
        }
        window.addEventListener('scroll', updateScrollProgress, {
            passive: true,
        });
        updateScrollProgress();
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        if (props.hideScrollbar) {
            document.documentElement.classList.remove('page-hide-scrollbar');
        }
        window.removeEventListener('scroll', updateScrollProgress);
    }
});
</script>

<template>
    <div
        id="scroll-indicator"
        class="fixed top-0 right-0 left-0 z-50 h-1 origin-left bg-primary"
        :class="props.class"
        :style="{ transform: `scaleX(${scrollProgress})` }"
        aria-hidden="true"
    />
</template>

<style scoped>
@keyframes scroll-grow {
    from {
        transform: scaleX(0);
    }
    to {
        transform: scaleX(1);
    }
}

@supports (animation-timeline: scroll()) {
    #scroll-indicator {
        animation: scroll-grow auto linear;
        animation-timeline: scroll();
    }
}

:global(html.page-hide-scrollbar),
:global(html.page-hide-scrollbar body) {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

:global(html.page-hide-scrollbar::-webkit-scrollbar),
:global(html.page-hide-scrollbar body::-webkit-scrollbar) {
    display: none;
}
</style>
