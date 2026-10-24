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

const props = withDefaults(
    defineProps<{
        title: string;
        description: string;
        ogDescription?: string;
        ogImage?: string;
        ogType?: string;
        twitterCard?: string;
        imageAlt?: string;
    }>(),
    {
        ogDescription: undefined,
        ogImage: '/images/og-image.png',
        ogType: 'website',
        twitterCard: 'summary_large_image',
        imageAlt: 'ValenceKit - Modern Laravel & Vue 3 Architecture',
    },
);

const absoluteOgImage = computed(() => {
    const img = props.ogImage || '/images/og-image.png';
    if (img.startsWith('http://') || img.startsWith('https://')) {
        return img;
    }
    return new URL(img, page.props.appUrl).toString();
});
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
        <meta head-key="og:type" property="og:type" :content="ogType" />
        <meta
            head-key="og:image"
            property="og:image"
            :content="absoluteOgImage"
        />
        <meta
            head-key="og:image:secure_url"
            property="og:image:secure_url"
            :content="absoluteOgImage"
        />
        <meta
            head-key="og:image:width"
            property="og:image:width"
            content="1200"
        />
        <meta
            head-key="og:image:height"
            property="og:image:height"
            content="630"
        />
        <meta
            head-key="og:image:alt"
            property="og:image:alt"
            :content="imageAlt"
        />
        <meta
            head-key="og:image:type"
            property="og:image:type"
            content="image/png"
        />

        <meta
            head-key="twitter:card"
            name="twitter:card"
            :content="twitterCard"
        />
        <meta head-key="twitter:title" name="twitter:title" :content="title" />
        <meta
            head-key="twitter:description"
            name="twitter:description"
            :content="ogDescription ?? description"
        />
        <meta
            head-key="twitter:image"
            name="twitter:image"
            :content="absoluteOgImage"
        />
        <meta
            head-key="twitter:image:alt"
            name="twitter:image:alt"
            :content="imageAlt"
        />

        <link head-key="canonical" rel="canonical" :href="canonicalUrl" />
        <slot />
    </Head>
</template>
