<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { Button } from "@/components/ui/button";
import type { CtaBlock } from "@/types";

defineProps<{ block: CtaBlock }>();
</script>

<template>
    <div>
        <Button size="lg" as-child>
            <Link :href="block.data.url">{{ block.data.label }}</Link>
        </Button>
    </div>
</template>
