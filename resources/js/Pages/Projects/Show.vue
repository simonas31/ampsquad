<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { ArrowLeft, Calendar, MapPin, User } from "@lucide/vue";
import { ref } from "vue";
import { useI18n } from "vue-i18n";
import Reveal from "@/components/common/Reveal.vue";
import BlockRenderer from "@/components/content/BlockRenderer.vue";
import Breadcrumbs from "@/components/content/Breadcrumbs.vue";
import Lightbox from "@/components/content/Lightbox.vue";
import Seo from "@/components/seo/Seo.vue";
import { Badge } from "@/components/ui/badge";
import AppLayout from "@/layouts/AppLayout.vue";
import type { Breadcrumb, Project, SeoData } from "@/types";

const props = defineProps<{
    project: Project;
    breadcrumbs: Breadcrumb[];
    seo: SeoData;
}>();

const { t } = useI18n();

const lightboxIndex = ref<number | null>(null);
const galleryImages = props.project.gallery.map((image) => image.large);
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
                            <dt class="flex items-center gap-1.5 font-medium">
                                <MapPin class="size-3.5" />
                                {{ t("content.location") }}
                            </dt>
                            <dd>{{ project.location }}</dd>
                        </div>
                        <div v-if="project.clientName">
                            <dt class="flex items-center gap-1.5 font-medium">
                                <User class="size-3.5" />
                                {{ t("content.client") }}
                            </dt>
                            <dd>{{ project.clientName }}</dd>
                        </div>
                        <div v-if="project.completedAt">
                            <dt class="flex items-center gap-1.5 font-medium">
                                <Calendar class="size-3.5" />
                                {{ t("content.completed") }}
                            </dt>
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
                class="shadow-warm-md mb-10 w-full rounded-xl"
            />

            <BlockRenderer v-if="project.blocks" :blocks="project.blocks" />

            <div v-if="project.gallery.length" class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-3">
                <button
                    v-for="(image, index) in project.gallery"
                    :key="index"
                    type="button"
                    class="focus-visible:ring-ring block overflow-hidden rounded-lg focus-visible:ring-2 focus-visible:outline-none"
                    @click="lightboxIndex = index"
                >
                    <img
                        :src="image.thumbnail"
                        loading="lazy"
                        class="aspect-square w-full rounded-lg object-cover transition-transform duration-300 hover:scale-105"
                        :alt="`${project.title} ${index + 1}`"
                    />
                </button>
            </div>

            <Lightbox v-model:index="lightboxIndex" :images="galleryImages" />

            <div v-if="breadcrumbs[1]?.url" class="mt-12 border-t pt-6">
                <Link
                    :href="breadcrumbs[1].url"
                    class="text-muted-foreground hover:text-foreground group flex items-center gap-1.5 text-sm transition-colors"
                >
                    <ArrowLeft class="size-4 transition-transform duration-200 group-hover:-translate-x-1" />
                    {{ t("content.backToProjects") }}
                </Link>
            </div>
        </article>
    </AppLayout>
</template>
