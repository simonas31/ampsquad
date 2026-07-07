<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { ArrowRight } from "@lucide/vue";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import type { Project } from "@/types";

defineProps<{ project: Project }>();
</script>

<template>
    <Link :href="project.url" class="group block">
        <Card
            class="hover:ring-primary/30 hover:shadow-warm-lg h-full overflow-hidden py-0 transition-all duration-300 hover:-translate-y-1"
        >
            <div class="bg-muted aspect-4/3 overflow-hidden">
                <img
                    v-if="project.featuredImageThumbUrl"
                    :src="project.featuredImageThumbUrl"
                    :alt="project.title"
                    loading="lazy"
                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                />
            </div>
            <div class="space-y-2 p-5">
                <Badge variant="secondary">{{ project.category.name }}</Badge>
                <h3 class="font-heading flex items-center gap-1.5 text-lg font-semibold tracking-tight">
                    {{ project.title }}
                    <ArrowRight
                        class="text-primary size-4 -translate-x-1 opacity-0 transition-all duration-200 group-hover:translate-x-0 group-hover:opacity-100"
                    />
                </h3>
                <p v-if="project.excerpt" class="text-muted-foreground line-clamp-2 text-sm">
                    {{ project.excerpt }}
                </p>
            </div>
        </Card>
    </Link>
</template>
