import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import type { SharedData } from "@/types";

export function useLocale() {
    const page = usePage<SharedData>();

    const current = computed(() => page.props.locale.current);
    const available = computed(() => page.props.locale.available);

    return { current, available };
}
