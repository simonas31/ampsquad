<script setup lang="ts">
import type { RichTextBlock } from "@/types";

defineProps<{ block: RichTextBlock }>();
</script>

<template>
    <!-- eslint-disable vue/no-v-html -->
    <div
        class="prose prose-neutral dark:prose-invert prose-headings:font-heading prose-a:text-primary prose-a:no-underline hover:prose-a:underline max-w-none"
        v-html="block.data.content"
    />
</template>
