<script setup lang="ts">
import type { QuoteBlock } from "@/types";

defineProps<{ block: QuoteBlock }>();
</script>

<template>
    <blockquote class="border-primary space-y-2 border-l-4 pl-6">
        <p class="font-heading text-xl italic sm:text-2xl">&ldquo;{{ block.data.text }}&rdquo;</p>
        <footer v-if="block.data.author" class="text-muted-foreground text-sm not-italic">
            — {{ block.data.author }}
        </footer>
    </blockquote>
</template>
