<script setup lang="ts">
import { computed } from "vue";
import type { VideoEmbedBlock } from "@/types";

const props = defineProps<{ block: VideoEmbedBlock }>();

const embedUrl = computed(() => {
    const url = props.block.data.url;

    const youtube = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/))([\w-]+)/);
    if (youtube) {
        return `https://www.youtube-nocookie.com/embed/${youtube[1]}`;
    }

    const vimeo = url.match(/vimeo\.com\/(\d+)/);
    if (vimeo) {
        return `https://player.vimeo.com/video/${vimeo[1]}`;
    }

    return url;
});
</script>

<template>
    <div class="shadow-warm-md aspect-video overflow-hidden rounded-lg bg-black">
        <iframe
            :src="embedUrl"
            class="h-full w-full"
            loading="lazy"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen
        />
    </div>
</template>
