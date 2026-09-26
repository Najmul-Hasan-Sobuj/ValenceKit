<script setup lang="ts">
import { useTimeAgo } from '@vueuse/core';
import { computed } from 'vue';

const props = defineProps<{
    date: string | Date | null | undefined;
    fallback?: string;
}>();

const parsedDate = computed(() => {
    if (!props.date) return null;
    const d = new Date(props.date);
    return isNaN(d.getTime()) ? null : d;
});

const timeAgo = useTimeAgo(() => parsedDate.value ?? new Date());

const display = computed(() => {
    if (!parsedDate.value) return props.fallback ?? 'Never';
    return timeAgo.value;
});

const fullDate = computed(() => {
    if (!parsedDate.value) return undefined;
    return new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(parsedDate.value);
});
</script>

<template>
    <span :title="fullDate">{{ display }}</span>
</template>
