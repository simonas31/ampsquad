<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { computed } from "vue";
import type { SeoData } from "@/types";

const props = defineProps<{ seo: SeoData }>();

const twitterCard = computed(() => (props.seo.ogImage ? "summary_large_image" : "summary"));
</script>

<template>
    <Head :title="seo.title">
        <meta v-if="seo.description" name="description" :content="seo.description" />
        <meta v-if="seo.noindex" name="robots" content="noindex, nofollow" />
        <link v-if="seo.canonical" rel="canonical" :href="seo.canonical" />

        <link
            v-for="alternate in seo.alternates"
            :key="alternate.locale"
            rel="alternate"
            :hreflang="alternate.locale"
            :href="alternate.url"
        />

        <meta property="og:title" :content="seo.title" />
        <meta v-if="seo.description" property="og:description" :content="seo.description" />
        <meta property="og:type" :content="seo.ogType" />
        <meta v-if="seo.canonical" property="og:url" :content="seo.canonical" />
        <meta v-if="seo.ogImage" property="og:image" :content="seo.ogImage" />

        <meta name="twitter:card" :content="twitterCard" />
        <meta name="twitter:title" :content="seo.title" />
        <meta v-if="seo.description" name="twitter:description" :content="seo.description" />
        <meta v-if="seo.ogImage" name="twitter:image" :content="seo.ogImage" />

        <component :is="'script'" v-for="(schema, index) in seo.jsonLd" :key="index" type="application/ld+json">{{
            JSON.stringify(schema)
        }}</component>
    </Head>
</template>
