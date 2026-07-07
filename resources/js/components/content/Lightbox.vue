<script setup lang="ts">
import { ChevronLeft, ChevronRight } from "@lucide/vue";
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogTitle } from "@/components/ui/dialog";

const props = defineProps<{ images: string[]; index: number | null }>();
const emit = defineEmits<{ "update:index": [value: number | null] }>();

const { t } = useI18n();

const isOpen = computed({
    get: () => props.index !== null,
    set: (value: boolean) => {
        if (!value) {
            emit("update:index", null);
        }
    },
});

const current = computed(() => (props.index !== null ? props.images[props.index] : null));

function next() {
    if (props.index === null) {
        return;
    }
    emit("update:index", (props.index + 1) % props.images.length);
}

function prev() {
    if (props.index === null) {
        return;
    }
    emit("update:index", (props.index - 1 + props.images.length) % props.images.length);
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === "ArrowRight") {
        next();
    } else if (event.key === "ArrowLeft") {
        prev();
    }
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent
            class="max-w-4xl border-none bg-transparent p-0 shadow-none ring-0"
            @keydown="onKeydown"
        >
            <DialogTitle class="sr-only">{{ t("content.imagePreview") }}</DialogTitle>
            <img
                v-if="current"
                :src="current"
                class="max-h-[85vh] w-full rounded-xl object-contain"
                alt=""
            />
            <template v-if="images.length > 1">
                <Button
                    variant="outline"
                    size="icon"
                    class="absolute top-1/2 left-2 -translate-y-1/2 rounded-full sm:-left-4"
                    :aria-label="t('content.previousImage')"
                    @click="prev"
                >
                    <ChevronLeft />
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    class="absolute top-1/2 right-2 -translate-y-1/2 rounded-full sm:-right-4"
                    :aria-label="t('content.nextImage')"
                    @click="next"
                >
                    <ChevronRight />
                </Button>
            </template>
        </DialogContent>
    </Dialog>
</template>
