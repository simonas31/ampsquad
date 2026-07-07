<script setup lang="ts">
import { onMounted, onUpdated, ref } from "vue";
import { Skeleton } from "@/components/ui/skeleton";
import { useInstagramEmbeds } from "@/composables/useInstagramEmbeds";
import type { Video } from "@/types";

const props = defineProps<{ video: Video }>();

const { reprocess } = useInstagramEmbeds();

// Instagram's embed.js swaps the blockquote for its own iframe asynchronously,
// so the slide is blank until that happens — show a skeleton until it does.
const isEmbedLoading = ref(props.video.type === "instagram_embed" && Boolean(props.video.embedUrl));

if (props.video.type === "instagram_embed") {
    onMounted(() => {
        void reprocess().then(() => {
            isEmbedLoading.value = false;
        });
    });
    onUpdated(reprocess);
}
</script>

<template>
    <div>
        <div class="bg-muted aspect-9/16 relative overflow-hidden rounded-xl">
            <Skeleton v-if="isEmbedLoading" class="absolute inset-0 rounded-xl" />
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
