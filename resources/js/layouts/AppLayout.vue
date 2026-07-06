<script setup lang="ts">
import { useI18n } from "vue-i18n";
import AppFooter from "@/components/layout/AppFooter.vue";
import AppHeader from "@/components/layout/AppHeader.vue";

const { t } = useI18n();
</script>

<template>
    <a
        href="#main-content"
        class="bg-primary text-primary-foreground focus:top-2 focus:left-2 sr-only rounded-md px-4 py-2 text-sm font-medium focus:not-sr-only focus:fixed focus:z-50"
    >
        {{ t("common.skipToContent") }}
    </a>

    <div class="flex min-h-screen flex-col">
        <AppHeader />

        <main id="main-content" class="flex-1">
            <slot />
        </main>

        <AppFooter />
    </div>
</template>
