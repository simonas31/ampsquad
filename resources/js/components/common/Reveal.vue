<script setup lang="ts">
import { useScrollReveal } from "@/composables/useScrollReveal";

withDefaults(defineProps<{ delay?: number }>(), { delay: 0 });

const { target, visible } = useScrollReveal();
</script>

<template>
    <div
        ref="target"
        class="transition-all duration-700 ease-out"
        :class="visible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'"
        :style="{ transitionDelay: visible ? `${delay}ms` : '0ms' }"
    >
        <slot />
    </div>
</template>
