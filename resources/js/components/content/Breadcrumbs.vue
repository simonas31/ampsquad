<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { ChevronRight } from "@lucide/vue";
import type { Breadcrumb } from "@/types";

defineProps<{ items: Breadcrumb[] }>();
</script>

<template>
    <nav aria-label="Breadcrumb" class="mx-auto max-w-6xl px-4 py-4 sm:px-6 lg:px-8">
        <ol class="text-muted-foreground flex flex-wrap items-center gap-1.5 text-sm">
            <li v-for="(item, index) in items" :key="index" class="flex items-center gap-1.5">
                <Link
                    v-if="item.url"
                    :href="item.url"
                    class="hover:text-foreground transition-colors"
                >
                    {{ item.label }}
                </Link>
                <span v-else class="text-foreground font-medium">{{ item.label }}</span>
                <ChevronRight v-if="index < items.length - 1" class="size-3.5 shrink-0" />
            </li>
        </ol>
    </nav>
</template>
