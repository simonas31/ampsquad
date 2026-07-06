<script setup lang="ts">
import Seo from "@/components/seo/Seo.vue";
import { Button } from "@/components/ui/button";
import AppLayout from "@/layouts/AppLayout.vue";
import type { SeoData } from "@/types";

defineProps<{ seo: SeoData }>();
</script>

<template>
    <Seo :seo="seo" />

    <AppLayout>
        <section class="mx-auto flex max-w-6xl flex-col items-start gap-6 px-4 py-24 sm:px-6 lg:px-8">
            <h1 class="font-heading text-4xl font-semibold tracking-tight sm:text-5xl">AmpSquad</h1>
            <p class="text-muted-foreground max-w-xl text-lg">
                Laravel + Filament + Inertia + Vue 3 + TypeScript foundations are wired up. The real
                homepage sections replace this placeholder in a later build phase.
            </p>
            <Button>Coming soon</Button>
        </section>
    </AppLayout>
</template>
