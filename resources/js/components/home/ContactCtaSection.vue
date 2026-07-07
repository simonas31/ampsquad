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
    <section class="px-4 py-24 sm:px-6 lg:px-8">
        <div class="bg-gradient-warm ring-primary/20 shadow-warm-lg mx-auto max-w-3xl rounded-3xl px-6 py-16 text-center ring-1 sm:px-12">
            <Reveal>
                <h2 class="font-heading text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{ title }}
                </h2>

                <Button as-child size="lg" class="hover:shadow-warm-md mt-8 transition-all duration-200 hover:scale-[1.02]">
                    <Link :href="contactUrl">{{ buttonLabel }}</Link>
                </Button>

                <div class="text-muted-foreground mt-10 flex flex-wrap items-center justify-center gap-x-8 gap-y-4 text-sm">
                    <a :href="`mailto:${email}`" class="hover:text-foreground group flex items-center gap-2 transition-colors">
                        <span class="bg-primary/10 text-primary group-hover:bg-primary/20 flex size-8 items-center justify-center rounded-full transition-colors">
                            <Mail class="size-4" />
                        </span>
                        {{ email }}
                    </a>
                    <a :href="`tel:${phone.replace(/\s+/g, '')}`" class="hover:text-foreground group flex items-center gap-2 transition-colors">
                        <span class="bg-primary/10 text-primary group-hover:bg-primary/20 flex size-8 items-center justify-center rounded-full transition-colors">
                            <Phone class="size-4" />
                        </span>
                        {{ phone }}
                    </a>
                    <span class="flex items-center gap-2">
                        <span class="bg-primary/10 text-primary flex size-8 items-center justify-center rounded-full">
                            <MapPin class="size-4" />
                        </span>
                        {{ address }}
                    </span>
                </div>
            </Reveal>
        </div>
    </section>
</template>
