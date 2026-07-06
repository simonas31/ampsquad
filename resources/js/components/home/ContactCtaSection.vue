<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { Mail, MapPin, Phone } from "@lucide/vue";
import Reveal from "@/components/common/Reveal.vue";
import { Button } from "@/components/ui/button";

defineProps<{
    title: string;
    buttonLabel: string;
    contactUrl: string;
    email: string;
    phone: string;
    address: string;
}>();
</script>

<template>
    <section class="py-24">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
            <Reveal>
                <h2 class="font-heading text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{ title }}
                </h2>

                <Button as-child size="lg" class="mt-8 transition-transform duration-200 hover:scale-[1.02]">
                    <Link :href="contactUrl">{{ buttonLabel }}</Link>
                </Button>

                <div class="text-muted-foreground mt-10 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-sm">
                    <a :href="`mailto:${email}`" class="hover:text-foreground flex items-center gap-2 transition-colors">
                        <Mail class="size-4" />
                        {{ email }}
                    </a>
                    <a :href="`tel:${phone.replace(/\s+/g, '')}`" class="hover:text-foreground flex items-center gap-2 transition-colors">
                        <Phone class="size-4" />
                        {{ phone }}
                    </a>
                    <span class="flex items-center gap-2">
                        <MapPin class="size-4" />
                        {{ address }}
                    </span>
                </div>
            </Reveal>
        </div>
    </section>
</template>
