<script setup lang="ts">
import { onMounted, onUpdated } from "vue";
import { useInstagramEmbeds } from "@/composables/useInstagramEmbeds";
import type { Video } from "@/types";

const props = defineProps<{ video: Video }>();

const { reprocess } = useInstagramEmbeds();

if (props.video.type === "instagram_embed") {
    onMounted(reprocess);
    onUpdated(reprocess);
}
</script>

<template>
    <div>
        <div class="bg-muted aspect-9/16 overflow-hidden rounded-xl">
            <blockquote
                v-if="video.type === 'instagram_embed' && video.embedUrl"
                class="instagram-media h-full w-full"
                :data-instgrm-permalink="video.embedUrl"
                data-instgrm-version="14"
            />
            <video
                v-else-if="video.videoUrl"
                :src="video.videoUrl"
                :poster="video.posterUrl ?? undefined"
                controls
                preload="none"
                class="h-full w-full object-cover"
            />
        </div>
        <p v-if="video.title" class="text-muted-foreground mt-2 text-sm">{{ video.title }}</p>
    </div>
</template>
