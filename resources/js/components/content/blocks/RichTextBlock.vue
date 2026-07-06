<script setup lang="ts">
import type { RichTextBlock } from "@/types";

defineProps<{ block: RichTextBlock }>();
</script>

<template>
    <!-- eslint-disable vue/no-v-html -->
    <div
        class="prose prose-neutral dark:prose-invert max-w-none"
        v-html="block.data.content"
    />
</template>
