<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Reveal from "@/components/common/Reveal.vue";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import type { CalculatorCategoryTeaser } from "@/types";

defineProps<{ categories: CalculatorCategoryTeaser[]; contactUrl: string }>();

const { t } = useI18n();
</script>

<template>
    <section v-if="categories.length" class="bg-secondary/30 py-20">
        <div class="mx-auto max-w-6xl px-4 text-center sm:px-6 lg:px-8">
            <Reveal>
                <h2 class="font-heading text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{ t("home.calculator.title") }}
                </h2>
                <p class="text-muted-foreground mx-auto mt-4 max-w-xl text-lg text-pretty">
                    {{ t("home.calculator.subtitle") }}
                </p>
            </Reveal>

            <div class="mt-10 grid gap-6 sm:grid-cols-3">
                <Reveal v-for="(category, index) in categories" :key="category.id" :delay="index * 75">
                    <Card class="h-full p-6 text-left transition-shadow duration-200 hover:shadow-md">
                        <h3 class="font-heading text-lg font-semibold">{{ category.name }}</h3>
                        <ul v-if="category.options.length" class="text-muted-foreground mt-3 space-y-1 text-sm">
                            <li v-for="option in category.options" :key="option">{{ option }}</li>
                        </ul>
                    </Card>
                </Reveal>
            </div>

            <Button as-child size="lg" class="mt-10 transition-transform duration-200 hover:scale-[1.02]">
                <Link :href="contactUrl">{{ t("home.calculator.cta") }}</Link>
            </Button>
        </div>
    </section>
</template>
