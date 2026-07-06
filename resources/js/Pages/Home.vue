<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import CalculatorTeaserSection from "@/components/home/CalculatorTeaserSection.vue";
import ContactCtaSection from "@/components/home/ContactCtaSection.vue";
import FeaturedProjectsSection from "@/components/home/FeaturedProjectsSection.vue";
import HeroSection from "@/components/home/HeroSection.vue";
import IntroSection from "@/components/home/IntroSection.vue";
import VideoCarouselSection from "@/components/home/VideoCarouselSection.vue";
import Seo from "@/components/seo/Seo.vue";
import AppLayout from "@/layouts/AppLayout.vue";
import type { CalculatorCategoryTeaser, Project, SeoData, SharedData, Video } from "@/types";

const props = defineProps<{
    seo: SeoData;
    hero: { title: string; subtitle: string };
    intro: { title: string; content: string };
    cta: { title: string; buttonLabel: string };
    featuredProjects: Project[];
    videos: Video[];
    calculatorCategories: CalculatorCategoryTeaser[];
    contact: { email: string; phone: string; address: string };
}>();

const page = usePage<SharedData>();

const contactUrl = computed(
    () => page.props.navigation.find((link) => link.labelKey === "nav.contact")?.url ?? "/contact",
);
const projectsUrl = computed(
    () => page.props.navigation.find((link) => link.labelKey === "nav.projects")?.url ?? "/projects",
);
</script>

<template>
    <Seo :seo="seo" />

    <AppLayout>
        <HeroSection
            :title="hero.title"
            :subtitle="hero.subtitle"
            :contact-url="contactUrl"
            :projects-url="projectsUrl"
        />

        <IntroSection :title="intro.title" :content="intro.content" />

        <FeaturedProjectsSection :projects="featuredProjects" :projects-url="projectsUrl" />

        <VideoCarouselSection :videos="videos" />

        <CalculatorTeaserSection :categories="calculatorCategories" :contact-url="contactUrl" />

        <ContactCtaSection
            :title="cta.title"
            :button-label="cta.buttonLabel"
            :contact-url="contactUrl"
            :email="props.contact.email"
            :phone="props.contact.phone"
            :address="props.contact.address"
        />
    </AppLayout>
</template>
