<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import Seo from "@/components/seo/Seo.vue";
import { Button } from "@/components/ui/button";
import AppLayout from "@/layouts/AppLayout.vue";
import type { SeoData } from "@/types";

const props = defineProps<{ status: 403 | 404 | 500 | 503; seo: SeoData }>();

const { t } = useI18n();

const key = computed(() => String(props.status) as "403" | "404" | "500" | "503");
</script>

<template>
    <Seo :seo="seo" />

    <AppLayout>
        <section class="relative overflow-hidden">
            <div class="bg-gradient-warm pointer-events-none absolute inset-0" aria-hidden="true" />

            <div
                class="relative mx-auto flex max-w-2xl flex-col items-start gap-6 px-4 py-32 text-center sm:items-center sm:px-6 lg:px-8"
            >
                <h1 class="font-heading text-4xl font-semibold tracking-tight sm:text-5xl">
                    {{ t(`errors.${key}.title`) }}
                </h1>
                <p class="text-muted-foreground text-lg">
                    {{ t(`errors.${key}.description`) }}
                </p>
                <Button as-child class="hover:shadow-warm-md transition-all duration-200 hover:scale-[1.02]">
                    <Link href="/">{{ t("errors.backHome") }}</Link>
                </Button>
            </div>
        </section>
    </AppLayout>
</template>
