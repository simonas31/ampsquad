<script setup lang="ts">
import type { ImageBlock } from "@/types";

defineProps<{ block: ImageBlock }>();
</script>

<template>
    <figure>
        <img
            :src="`/storage/${block.data.image}`"
            :alt="block.data.caption ?? ''"
            loading="lazy"
            class="w-full rounded-lg"
        />
        <figcaption v-if="block.data.caption" class="text-muted-foreground mt-2 text-sm">
            {{ block.data.caption }}
        </figcaption>
    </figure>
</template>
