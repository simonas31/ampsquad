<script setup lang="ts">
import type { GalleryBlock } from "@/types";

defineProps<{ block: GalleryBlock }>();
</script>

<template>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <img
            v-for="(image, index) in block.data.images"
            :key="index"
            :src="`/storage/${image}`"
            loading="lazy"
            class="aspect-square w-full rounded-lg object-cover"
            alt=""
        />
    </div>
</template>
