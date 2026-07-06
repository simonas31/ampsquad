<script setup lang="ts">
import { useForm, usePage } from "@inertiajs/vue3";
import { CheckCircle2 } from "@lucide/vue";
import { useI18n } from "vue-i18n";
import Breadcrumbs from "@/components/content/Breadcrumbs.vue";
import Seo from "@/components/seo/Seo.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import AppLayout from "@/layouts/AppLayout.vue";
import type { Breadcrumb, SeoData } from "@/types";

defineProps<{
    breadcrumbs: Breadcrumb[];
    seo: SeoData;
}>();

const { t } = useI18n();
const page = usePage();

const form = useForm({
    name: "",
    email: "",
    phone: "",
    message: "",
    company: "", // honeypot — real visitors never see or fill this in
});

function submit() {
    form.post(window.location.pathname, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Seo :seo="seo" />

    <AppLayout>
        <Breadcrumbs :items="breadcrumbs" />

        <section class="mx-auto max-w-xl px-4 pb-24 sm:px-6 lg:px-8">
            <h1 class="font-heading mb-8 text-3xl font-semibold tracking-tight sm:text-4xl">
                {{ t("nav.contact") }}
            </h1>

            <div
                v-if="page.flash?.contactSubmitted"
                class="border-primary bg-accent mb-8 flex items-center gap-3 rounded-lg border p-4"
            >
                <CheckCircle2 class="text-primary size-5 shrink-0" />
                <p class="text-sm">{{ t("contact.success") }}</p>
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="name">{{ t("contact.name") }}</Label>
                    <Input id="name" v-model="form.name" required autocomplete="name" />
                    <p v-if="form.errors.name" class="text-destructive text-sm">{{ form.errors.name }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="email">{{ t("contact.email") }}</Label>
                    <Input id="email" v-model="form.email" type="email" required autocomplete="email" />
                    <p v-if="form.errors.email" class="text-destructive text-sm">{{ form.errors.email }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="phone">{{ t("contact.phone") }}</Label>
                    <Input id="phone" v-model="form.phone" type="tel" autocomplete="tel" />
                    <p v-if="form.errors.phone" class="text-destructive text-sm">{{ form.errors.phone }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="message">{{ t("contact.message") }}</Label>
                    <Textarea id="message" v-model="form.message" rows="5" required />
                    <p v-if="form.errors.message" class="text-destructive text-sm">{{ form.errors.message }}</p>
                </div>

                <!-- Honeypot: hidden from real visitors, bots fill it in blindly. -->
                <div class="sr-only" aria-hidden="true">
                    <label for="company">Company</label>
                    <input
                        id="company"
                        v-model="form.company"
                        type="text"
                        tabindex="-1"
                        autocomplete="off"
                    />
                </div>

                <Button type="submit" size="lg" :disabled="form.processing" class="w-full">
                    {{ t("contact.submit") }}
                </Button>
            </form>
        </section>
    </AppLayout>
</template>
