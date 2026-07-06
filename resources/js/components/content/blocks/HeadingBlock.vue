<script setup lang="ts">
import type { HeadingBlock } from "@/types";

defineProps<{ block: HeadingBlock }>();
</script>

<template>
    <component
        :is="block.data.level"
        class="font-heading font-semibold tracking-tight"
        :class="{
            'text-3xl sm:text-4xl': block.data.level === 'h2',
            'text-2xl sm:text-3xl': block.data.level === 'h3',
            'text-xl sm:text-2xl': block.data.level === 'h4',
        }"
    >
        {{ block.data.text }}
    </component>
</template>
