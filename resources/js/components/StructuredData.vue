<script setup lang="ts">
import { computed, h } from 'vue';
import { Head } from '@inertiajs/vue3';

type JsonLdEntity = Record<string, unknown>;

const props = defineProps<{
    entities: JsonLdEntity[];
}>();

const jsonLd = computed(() =>
    JSON.stringify({
        '@context': 'https://schema.org',
        '@graph': props.entities,
    }),
);

const structuredDataHead = () =>
    h(Head, null, {
        default: () =>
            h(
                'script',
                {
                    'head-key': 'structured-data',
                    type: 'application/ld+json',
                },
                jsonLd.value,
            ),
    });
</script>

<template>
    <component :is="structuredDataHead" />
</template>
