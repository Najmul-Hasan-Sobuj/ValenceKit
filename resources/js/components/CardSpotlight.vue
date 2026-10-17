<script setup lang="ts">
import { ref } from 'vue';

const props = withDefaults(
    defineProps<{
        class?: string;
        glowSize?: number;
    }>(),
    {
        class: '',
        glowSize: 350,
    },
);

const mouseX = ref(-1000);
const mouseY = ref(-1000);
const isHovered = ref(false);

const handleMouseMove = (e: MouseEvent) => {
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    mouseX.value = e.clientX - rect.left;
    mouseY.value = e.clientY - rect.top;
};

const handleMouseEnter = () => {
    isHovered.value = true;
};

const handleMouseLeave = () => {
    isHovered.value = false;
};
</script>

<template>
    <div
        class="group/spotlight relative overflow-hidden rounded-xl border border-border/70 bg-card/60 p-5 transition-all duration-300 hover:border-border hover:bg-card"
        :class="props.class"
        @mousemove="handleMouseMove"
        @mouseenter="handleMouseEnter"
        @mouseleave="handleMouseLeave"
    >
        <!-- Border Spotlight Highlight (follows mouse) -->
        <div
            class="pointer-events-none absolute -inset-px rounded-xl border-2 border-primary/70 transition-opacity duration-200"
            :class="isHovered ? 'opacity-100' : 'opacity-0'"
            :style="{
                maskImage: `radial-gradient(260px circle at ${mouseX}px ${mouseY}px, black 0%, transparent 80%)`,
                WebkitMaskImage: `radial-gradient(260px circle at ${mouseX}px ${mouseY}px, black 0%, transparent 80%)`,
            }"
            aria-hidden="true"
        />

        <!-- Radial Cursor Spotlight Surface Glow -->
        <div
            class="pointer-events-none absolute -inset-px rounded-xl transition-opacity duration-200"
            :class="isHovered ? 'opacity-100' : 'opacity-0'"
            :style="{
                background: `radial-gradient(${props.glowSize}px circle at ${mouseX}px ${mouseY}px, rgba(99, 102, 241, 0.25) 0%, transparent 70%)`,
            }"
            aria-hidden="true"
        />

        <div class="relative z-10 flex h-full flex-col justify-between">
            <slot />
        </div>
    </div>
</template>
