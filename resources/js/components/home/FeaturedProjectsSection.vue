<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Reveal from "@/components/common/Reveal.vue";
import ContentCard from "@/components/content/ContentCard.vue";
import { Button } from "@/components/ui/button";
import type { Project } from "@/types";

defineProps<{ projects: Project[]; projectsUrl: string }>();

const { t } = useI18n();
</script>

<template>
    <section v-if="projects.length" class="bg-secondary/30 py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <Reveal class="flex flex-col items-start gap-4 sm:flex-row sm:items-end sm:justify-between">
                <h2 class="font-heading text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{ t("home.featuredProjects.title") }}
                </h2>
                <Button as-child variant="outline">
                    <Link :href="projectsUrl">{{ t("home.featuredProjects.viewAll") }}</Link>
                </Button>
            </Reveal>

            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Reveal v-for="(project, index) in projects" :key="project.id" :delay="index * 75">
                    <ContentCard :project="project" />
                </Reveal>
            </div>
        </div>
    </section>
</template>
