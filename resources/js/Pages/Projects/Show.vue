<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Reveal from "@/components/common/Reveal.vue";
import BlockRenderer from "@/components/content/BlockRenderer.vue";
import Breadcrumbs from "@/components/content/Breadcrumbs.vue";
import Seo from "@/components/seo/Seo.vue";
import { Badge } from "@/components/ui/badge";
import AppLayout from "@/layouts/AppLayout.vue";
import type { Breadcrumb, Project, SeoData } from "@/types";

defineProps<{
    project: Project;
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
            <Reveal>
                <header class="mb-8 space-y-4">
                    <Badge variant="secondary">{{ project.category.name }}</Badge>
                    <h1 class="font-heading text-3xl font-semibold tracking-tight sm:text-4xl">
                        {{ project.title }}
                    </h1>

                    <dl class="text-muted-foreground grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                        <div v-if="project.location">
                            <dt class="font-medium">{{ t("content.location") }}</dt>
                            <dd>{{ project.location }}</dd>
                        </div>
                        <div v-if="project.clientName">
                            <dt class="font-medium">{{ t("content.client") }}</dt>
                            <dd>{{ project.clientName }}</dd>
                        </div>
                        <div v-if="project.completedAt">
                            <dt class="font-medium">{{ t("content.completed") }}</dt>
                            <dd>{{ project.completedAt }}</dd>
                        </div>
                    </dl>

                    <div v-if="project.tags.length" class="flex flex-wrap gap-2">
                        <Badge v-for="tag in project.tags" :key="tag.id" variant="outline">
                            {{ tag.name }}
                        </Badge>
                    </div>
                </header>
            </Reveal>

            <img
                v-if="project.featuredImageUrl"
                :src="project.featuredImageUrl"
                :alt="project.title"
                class="mb-10 w-full rounded-xl"
            />

            <BlockRenderer v-if="project.blocks" :blocks="project.blocks" />

            <div v-if="project.gallery.length" class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-3">
                <img
                    v-for="(image, index) in project.gallery"
                    :key="index"
                    :src="image.thumbnail"
                    loading="lazy"
                    class="aspect-square w-full rounded-lg object-cover transition-transform duration-300 hover:scale-105"
                    :alt="`${project.title} ${index + 1}`"
                />
            </div>

            <div v-if="breadcrumbs[1]?.url" class="mt-12 border-t pt-6">
                <Link
                    :href="breadcrumbs[1].url"
                    class="text-muted-foreground hover:text-foreground text-sm transition-colors"
                >
                    &larr; {{ t("content.backToProjects") }}
                </Link>
            </div>
        </article>
    </AppLayout>
</template>
