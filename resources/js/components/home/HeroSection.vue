<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import { Button } from "@/components/ui/button";

defineProps<{
    title: string;
    subtitle: string;
    contactUrl: string;
    projectsUrl: string;
}>();

const { t } = useI18n();
</script>

<template>
    <section class="relative overflow-hidden">
        <div
            class="bg-gradient-warm pointer-events-none absolute inset-0"
            aria-hidden="true"
        />
        <div
            class="bg-primary/20 pointer-events-none absolute -top-24 -right-24 size-72 rounded-full blur-3xl"
            aria-hidden="true"
        />
        <div
            class="bg-primary/10 pointer-events-none absolute -bottom-32 left-1/4 size-96 rounded-full blur-3xl"
            aria-hidden="true"
        />

        <div
            class="animate-in fade-in slide-in-from-bottom-4 relative mx-auto flex max-w-6xl flex-col items-start gap-6 px-4 py-28 duration-700 sm:px-6 lg:px-8 lg:py-36"
        >
            <h1 class="font-heading max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                {{ title }}
            </h1>
            <p class="text-muted-foreground max-w-xl text-lg text-pretty">
                {{ subtitle }}
            </p>
            <div class="flex flex-wrap gap-3 pt-2">
                <Button as-child size="lg" class="hover:shadow-warm-md transition-all duration-200 hover:scale-[1.02]">
                    <Link :href="contactUrl">{{ t("home.hero.cta") }}</Link>
                </Button>
                <Button
                    as-child
                    size="lg"
                    variant="outline"
                    class="hover:shadow-warm-sm transition-all duration-200 hover:scale-[1.02]"
                >
                    <Link :href="projectsUrl">{{ t("home.hero.secondaryCta") }}</Link>
                </Button>
            </div>
        </div>
    </section>
</template>
