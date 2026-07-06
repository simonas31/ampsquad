<script setup lang="ts">
import { computed } from "vue";
import type { Component } from "vue";
import type { ContentBlock } from "@/types";
import CtaBlock from "./blocks/CtaBlock.vue";
import GalleryBlock from "./blocks/GalleryBlock.vue";
import HeadingBlock from "./blocks/HeadingBlock.vue";
import ImageBlock from "./blocks/ImageBlock.vue";
import QuoteBlock from "./blocks/QuoteBlock.vue";
import RichTextBlock from "./blocks/RichTextBlock.vue";
import VideoEmbedBlock from "./blocks/VideoEmbedBlock.vue";

const props = defineProps<{ blocks: ContentBlock[] }>();

const components: Record<ContentBlock["type"], Component> = {
    heading: HeadingBlock,
    rich_text: RichTextBlock,
    image: ImageBlock,
    gallery: GalleryBlock,
    video_embed: VideoEmbedBlock,
    quote: QuoteBlock,
    cta: CtaBlock,
};

const renderableBlocks = computed(() => props.blocks.filter((block) => block.type in components));
</script>

<template>
    <div class="space-y-8">
        <component
            :is="components[block.type]"
            v-for="(block, index) in renderableBlocks"
            :key="index"
            :block="block"
        />
    </div>
</template>
