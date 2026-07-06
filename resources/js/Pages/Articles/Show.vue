<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import BlockRenderer from "@/components/content/BlockRenderer.vue";
import Breadcrumbs from "@/components/content/Breadcrumbs.vue";
import Seo from "@/components/seo/Seo.vue";
import { Badge } from "@/components/ui/badge";
import AppLayout from "@/layouts/AppLayout.vue";
import type { Breadcrumb, Project, SeoData } from "@/types";

defineProps<{
    article: Project;
    breadcrumbs: Breadcrumb[];
    seo: SeoData;
}>();

const { t } = useI18n();
</script>

<template>
    <Seo :seo="seo" />

    <AppLayout>
        <Breadcrumbs :items="breadcrumbs" />

        <article class="mx-auto max-w-3xl px-4 pb-24 sm:px-6 lg:px-8">
            <header class="mb-8 space-y-4">
                <Badge variant="secondary">{{ article.category.name }}</Badge>
                <h1 class="font-heading text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{ article.title }}
                </h1>
                <p v-if="article.author" class="text-muted-foreground text-sm">
                    {{ article.author }}
                </p>

                <div v-if="article.tags.length" class="flex flex-wrap gap-2">
                    <Badge v-for="tag in article.tags" :key="tag.id" variant="outline">
                        {{ tag.name }}
                    </Badge>
                </div>
            </header>

            <img
                v-if="article.featuredImageUrl"
                :src="article.featuredImageUrl"
                :alt="article.title"
                class="mb-10 w-full rounded-xl"
            />

            <BlockRenderer v-if="article.blocks" :blocks="article.blocks" />

            <div v-if="breadcrumbs[1]?.url" class="mt-12 border-t pt-6">
                <Link
                    :href="breadcrumbs[1].url"
                    class="text-muted-foreground hover:text-foreground text-sm transition-colors"
                >
                    &larr; {{ t("content.backToArticles") }}
                </Link>
            </div>
        </article>
    </AppLayout>
</template>
