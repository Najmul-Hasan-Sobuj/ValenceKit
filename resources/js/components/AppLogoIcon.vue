<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="223 384 807 490"
        :class="className"
        v-bind="$attrs"
    >
        <image
            href="/favicon.svg"
            x="0"
            y="0"
            width="1254"
            height="1254"
            preserveAspectRatio="none"
        />
    </svg>
</template>
