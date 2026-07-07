<script setup lang="ts">
import Reveal from "@/components/common/Reveal.vue";
import BlockRenderer from "@/components/content/BlockRenderer.vue";
import Breadcrumbs from "@/components/content/Breadcrumbs.vue";
import Seo from "@/components/seo/Seo.vue";
import AppLayout from "@/layouts/AppLayout.vue";
import type { Breadcrumb, Page, SeoData } from "@/types";

defineProps<{
    page: Page;
    breadcrumbs: Breadcrumb[];
    seo: SeoData;
}>();
</script>

<template>
    <Seo :seo="seo" />

    <AppLayout>
        <Breadcrumbs :items="breadcrumbs" />

        <article class="mx-auto max-w-3xl px-4 pb-24 sm:px-6 lg:px-8">
            <Reveal>
                <span class="bg-primary mb-6 block h-1 w-12 rounded-full" aria-hidden="true" />
                <h1 class="font-heading mb-8 text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{ page.title }}
                </h1>

                <img
                    v-if="page.featuredImageUrl"
                    :src="page.featuredImageUrl"
                    :alt="page.title"
                    class="shadow-warm-md mb-10 w-full rounded-xl"
                />

                <BlockRenderer :blocks="page.blocks" />
            </Reveal>
        </article>
    </AppLayout>
</template>
