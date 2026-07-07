<script setup lang="ts">
import { computed, ref } from "vue";
import Lightbox from "@/components/content/Lightbox.vue";
import type { GalleryBlock } from "@/types";

const props = defineProps<{ block: GalleryBlock }>();

const lightboxIndex = ref<number | null>(null);
const images = computed(() => props.block.data.images.map((image) => `/storage/${image}`));
</script>

<template>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <button
            v-for="(image, index) in images"
            :key="index"
            type="button"
            class="focus-visible:ring-ring block overflow-hidden rounded-lg focus-visible:ring-2 focus-visible:outline-none"
            @click="lightboxIndex = index"
        >
            <img
                :src="image"
                loading="lazy"
                class="aspect-square w-full rounded-lg object-cover transition-transform duration-300 hover:scale-105"
                alt=""
            />
        </button>
    </div>

    <Lightbox v-model:index="lightboxIndex" :images="images" />
</template>
