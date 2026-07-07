<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import type { SharedData } from "@/types";

const { t } = useI18n();
const page = usePage<SharedData>();
const year = computed(() => new Date().getFullYear());
</script>

<template>
    <footer class="bg-secondary/40 relative">
        <div class="via-border absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent to-transparent" aria-hidden="true" />

        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-3">
                <div class="space-y-3">
                    <span class="font-heading text-xl font-semibold tracking-tight">AmpSquad</span>
                    <p class="text-muted-foreground max-w-xs text-sm">
                        {{ t("footer.tagline") }}
                    </p>
                </div>

                <div class="space-y-3">
                    <h2 class="text-sm font-semibold tracking-wide uppercase">
                        {{ t("footer.quickLinks") }}
                    </h2>
                    <nav class="flex flex-col gap-2">
                        <Link
                            v-for="link in page.props.navigation"
                            :key="link.url"
                            :href="link.url"
                            class="text-muted-foreground hover:text-foreground text-sm transition-colors"
                        >
                            {{ t(link.labelKey) }}
                        </Link>
                    </nav>
                </div>
            </div>

            <div class="relative mt-10 pt-6">
                <div class="via-border absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent to-transparent" aria-hidden="true" />
                <p class="text-muted-foreground text-sm">&copy; {{ year }} AmpSquad. {{ t("footer.rights") }}</p>
            </div>
        </div>
    </footer>
</template>
