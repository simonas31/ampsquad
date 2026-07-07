<script setup lang="ts">
import { usePreferredReducedMotion } from "@vueuse/core";
import Autoplay from "embla-carousel-autoplay";
import { ref } from "vue";
import { useI18n } from "vue-i18n";
import Reveal from "@/components/common/Reveal.vue";
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
    type CarouselApi,
} from "@/components/ui/carousel";
import type { Video } from "@/types";
import VideoCarouselItem from "./VideoCarouselItem.vue";

defineProps<{ videos: Video[] }>();

const { t } = useI18n();

const prefersReducedMotion = usePreferredReducedMotion();
const autoplayPlugins =
    prefersReducedMotion.value === "reduce"
        ? []
        : [Autoplay({ delay: 4500, stopOnInteraction: false, stopOnMouseEnter: true })];

const carouselApi = ref<CarouselApi>();
const selectedIndex = ref(0);
const slideCount = ref(0);

function onInitApi(api: CarouselApi) {
    if (!api) {
        return;
    }

    carouselApi.value = api;
    slideCount.value = api.scrollSnapList().length;
    selectedIndex.value = api.selectedScrollSnap();
    api.on("select", () => {
        selectedIndex.value = api.selectedScrollSnap();
    });
}
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
                <Carousel
                    class="mx-auto max-w-4xl"
                    :opts="{ loop: true }"
                    :plugins="autoplayPlugins"
                    @init-api="onInitApi"
                >
                    <CarouselContent>
                        <CarouselItem v-for="video in videos" :key="video.id" class="sm:basis-1/2 lg:basis-1/3">
                            <VideoCarouselItem :video="video" />
                        </CarouselItem>
                    </CarouselContent>
                    <CarouselPrevious />
                    <CarouselNext />
                </Carousel>

                <div v-if="slideCount > 1" class="mt-6 flex items-center justify-center gap-2">
                    <button
                        v-for="index in slideCount"
                        :key="index"
                        type="button"
                        class="size-2 rounded-full transition-colors"
                        :class="index - 1 === selectedIndex ? 'bg-primary' : 'bg-border hover:bg-muted-foreground/50'"
                        :aria-label="t('common.goToSlide', { number: index })"
                        :aria-current="index - 1 === selectedIndex"
                        @click="carouselApi?.scrollTo(index - 1)"
                    />
                </div>
            </Reveal>
        </div>
    </section>
</template>
