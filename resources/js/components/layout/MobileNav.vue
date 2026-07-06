<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from "@/components/ui/sheet";
import type { SharedData } from "@/types";

const open = defineModel<boolean>("open", { default: false });

const { t } = useI18n();
const page = usePage<SharedData>();
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="w-72">
            <SheetHeader>
                <SheetTitle class="font-heading text-left">AmpSquad</SheetTitle>
                <SheetDescription class="sr-only">
                    {{ t("common.mobileNavDescription") }}
                </SheetDescription>
            </SheetHeader>

            <nav class="flex flex-col gap-1 px-4">
                <Link
                    v-for="link in page.props.navigation"
                    :key="link.url"
                    :href="link.url"
                    class="hover:bg-accent hover:text-accent-foreground rounded-md px-3 py-2.5 text-base font-medium transition-colors"
                    @click="open = false"
                >
                    {{ t(link.labelKey) }}
                </Link>
            </nav>
        </SheetContent>
    </Sheet>
</template>
