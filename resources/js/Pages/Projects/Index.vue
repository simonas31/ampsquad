<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { FolderSearch } from "@lucide/vue";
import { useI18n } from "vue-i18n";
import Reveal from "@/components/common/Reveal.vue";
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
    projects: Paginated<Project>;
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
            <Reveal class="mb-10 flex flex-wrap items-end justify-between gap-4">
                <h1 class="font-heading text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{ t("nav.projects") }}
                </h1>

                <Select @update:model-value="filterByCategory">
                    <SelectTrigger class="w-full sm:w-48">
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
            </Reveal>

            <div
                v-if="props.projects.data.length > 0"
                class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
            >
                <Reveal
                    v-for="(project, index) in props.projects.data"
                    :key="project.id"
                    :delay="(index % 6) * 75"
                >
                    <ContentCard :project="project" />
                </Reveal>
            </div>
            <div v-else class="flex flex-col items-center gap-3 py-16 text-center">
                <span class="bg-muted text-muted-foreground flex size-14 items-center justify-center rounded-full">
                    <FolderSearch class="size-6" />
                </span>
                <p class="text-muted-foreground">
                    {{ t("content.noProjects") }}
                </p>
            </div>

            <div class="mt-12">
                <Pagination :links="props.projects.links" />
            </div>
        </section>
    </AppLayout>
</template>
