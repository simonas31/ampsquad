<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import type { PaginationLink } from "@/types";

defineProps<{ links: PaginationLink[] }>();
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap items-center justify-center gap-1.5" aria-label="Pagination">
        <template v-for="(link, index) in links" :key="index">
            <span
                v-if="!link.url"
                class="text-muted-foreground px-3 py-1.5 text-sm"
                v-html="link.label"
            />
            <Link
                v-else
                :href="link.url"
                v-html="link.label"
                :class="
                    cn(
                        buttonVariants({ variant: link.active ? 'default' : 'outline', size: 'sm' }),
                        'min-w-9 justify-center',
                    )
                "
            />
        </template>
    </nav>
</template>
