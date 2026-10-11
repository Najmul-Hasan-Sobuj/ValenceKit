<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<{ appUrl: string }>();

const canonicalUrl = computed(() => {
    const url = new URL(page.url, page.props.appUrl);

    url.search = '';
    url.hash = '';

    return url.toString();
});

withDefaults(
    defineProps<{
        title: string;
        description: string;
        ogDescription?: string;
    }>(),
    {
        ogDescription: undefined,
    },
);
</script>

<template>
    <Head :title="title">
        <meta
            head-key="description"
            name="description"
            :content="description"
        />
        <meta head-key="og:title" property="og:title" :content="title" />
        <meta
            head-key="og:description"
            property="og:description"
            :content="ogDescription ?? description"
        />
        <meta head-key="og:url" property="og:url" :content="canonicalUrl" />
        <meta
            head-key="og:site_name"
            property="og:site_name"
            content="ValenceKit"
        />
        <meta
            head-key="twitter:card"
            name="twitter:card"
            content="summary"
        />
        <meta head-key="twitter:title" name="twitter:title" :content="title" />
        <meta
            head-key="twitter:description"
            name="twitter:description"
            :content="ogDescription ?? description"
        />
        <meta head-key="og:type" property="og:type" content="website" />
        <link head-key="canonical" rel="canonical" :href="canonicalUrl" />
        <slot />
    </Head>
</template>
