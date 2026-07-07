<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { Button } from "@/components/ui/button";
import type { CtaBlock } from "@/types";

defineProps<{ block: CtaBlock }>();
</script>

<template>
    <div class="bg-gradient-warm ring-primary/20 shadow-warm-md rounded-3xl px-6 py-10 text-center ring-1 sm:px-10">
        <Button size="lg" as-child class="hover:shadow-warm-md transition-all duration-200 hover:scale-[1.02]">
            <Link :href="block.data.url">{{ block.data.label }}</Link>
        </Button>
    </div>
</template>
