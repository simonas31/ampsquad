<script setup lang="ts">
import { useI18n } from "vue-i18n";
import Reveal from "@/components/common/Reveal.vue";
import { Carousel, CarouselContent, CarouselItem, CarouselNext, CarouselPrevious } from "@/components/ui/carousel";
import type { Video } from "@/types";
import VideoCarouselItem from "./VideoCarouselItem.vue";

defineProps<{ videos: Video[] }>();

const { t } = useI18n();
</script>

<template>
    <section v-if="videos.length" class="py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <Reveal>
                <h2 class="font-heading text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{ t("home.videos.title") }}
                </h2>
            </Reveal>

            <Reveal :delay="100" class="mt-10">
                <Carousel class="mx-auto max-w-4xl">
                    <CarouselContent>
                        <CarouselItem v-for="video in videos" :key="video.id" class="sm:basis-1/2 lg:basis-1/3">
                            <VideoCarouselItem :video="video" />
                        </CarouselItem>
                    </CarouselContent>
                    <CarouselPrevious />
                    <CarouselNext />
                </Carousel>
            </Reveal>
        </div>
    </section>
</template>
