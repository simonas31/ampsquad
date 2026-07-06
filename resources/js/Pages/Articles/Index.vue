<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Breadcrumbs from "@/components/content/Breadcrumbs.vue";
import ContentCard from "@/components/content/ContentCard.vue";
import Pagination from "@/components/content/Pagination.vue";
import Seo from "@/components/seo/Seo.vue";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import AppLayout from "@/layouts/AppLayout.vue";
import type { Breadcrumb, Category, Paginated, Project, SeoData } from "@/types";

const props = defineProps<{
    articles: Paginated<Project>;
    categories: Category[];
    breadcrumbs: Breadcrumb[];
    seo: SeoData;
}>();

const { t } = useI18n();

function filterByCategory(categoryId: unknown) {
    router.get(
        window.location.pathname,
        { category: categoryId === "all" ? undefined : String(categoryId) },
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
    <Seo :seo="seo" />

    <AppLayout>
        <Breadcrumbs :items="breadcrumbs" />

        <section class="mx-auto max-w-6xl px-4 pb-24 sm:px-6 lg:px-8">
            <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
                <h1 class="font-heading text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{ t("nav.articles") }}
                </h1>

                <Select @update:model-value="filterByCategory">
                    <SelectTrigger class="w-48">
                        <SelectValue :placeholder="t('content.allCategories')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{{ t("content.allCategories") }}</SelectItem>
                        <SelectItem
                            v-for="category in props.categories"
                            :key="category.id"
                            :value="String(category.id)"
                        >
                            {{ category.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div
                v-if="props.articles.data.length > 0"
                class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
            >
                <ContentCard v-for="article in props.articles.data" :key="article.id" :project="article" />
            </div>
            <p v-else class="text-muted-foreground py-12 text-center">
                {{ t("content.noArticles") }}
            </p>

            <div class="mt-12">
                <Pagination :links="props.articles.links" />
            </div>
        </section>
    </AppLayout>
</template>
