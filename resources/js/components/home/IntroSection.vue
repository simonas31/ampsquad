<script setup lang="ts">
import Reveal from "@/components/common/Reveal.vue";

defineProps<{ title: string; content: string }>();
</script>

<template>
    <section class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
        <Reveal>
            <span class="bg-primary mx-auto block h-1 w-12 rounded-full" aria-hidden="true" />
            <h2 class="font-heading mt-6 text-3xl font-semibold tracking-tight sm:text-4xl">
                {{ title }}
            </h2>
            <p class="text-muted-foreground mt-6 text-lg text-pretty">
                {{ content }}
            </p>
        </Reveal>
    </section>
</template>
