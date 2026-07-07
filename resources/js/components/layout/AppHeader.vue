<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import { useScroll } from "@vueuse/core";
import { Languages, Menu } from "@lucide/vue";
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useActiveLink } from "@/composables/useActiveLink";
import { useLocale } from "@/composables/useLocale";
import type { SharedData } from "@/types";
import MobileNav from "./MobileNav.vue";

const { t } = useI18n();
const page = usePage<SharedData>();
const { current, available } = useLocale();
const { isActive } = useActiveLink();
const mobileNavOpen = ref(false);

const homeUrl = computed(
    () => page.props.navigation.find((link) => link.labelKey === "nav.home")?.url ?? "/",
);

const { y: scrollY } = useScroll(window);
const isScrolled = computed(() => scrollY.value > 8);
</script>

<template>
    <header
        class="bg-background/80 supports-backdrop-filter:bg-background/60 sticky top-0 z-40 w-full border-b backdrop-blur transition-shadow duration-200"
        :class="isScrolled ? 'border-border/60 shadow-sm' : 'border-transparent'"
    >
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <Link
                :href="homeUrl"
                class="font-heading hover:text-primary text-xl font-semibold tracking-tight transition-colors"
            >
                AmpSquad
            </Link>

            <nav class="hidden items-center gap-8 md:flex">
                <Link
                    v-for="link in page.props.navigation"
                    :key="link.url"
                    :href="link.url"
                    :aria-current="isActive(link.url) ? 'page' : undefined"
                    class="relative py-1 text-sm font-medium transition-colors"
                    :class="isActive(link.url) ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
                >
                    {{ t(link.labelKey) }}
                    <span
                        v-if="isActive(link.url)"
                        class="bg-primary absolute -bottom-1 left-0 h-0.5 w-full rounded-full"
                        aria-hidden="true"
                    />
                </Link>
            </nav>

            <div class="flex items-center gap-2">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon" :aria-label="t('common.switchLanguage')">
                            <Languages class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem v-for="locale in available" :key="locale.code" as-child>
                            <Link
                                :href="locale.url"
                                :class="[
                                    'w-full',
                                    locale.code === current ? 'text-primary font-semibold' : '',
                                ]"
                            >
                                {{ locale.name }}
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <Button
                    variant="ghost"
                    size="icon"
                    class="md:hidden"
                    :aria-label="t('common.openMenu')"
                    @click="mobileNavOpen = true"
                >
                    <Menu class="size-5" />
                </Button>
            </div>
        </div>

        <MobileNav v-model:open="mobileNavOpen" />
    </header>
</template>
