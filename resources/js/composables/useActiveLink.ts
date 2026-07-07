import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";

/**
 * Nav links come back as full absolute URLs (`http://localhost/projects`)
 * while Inertia's page.url is a relative path — compare pathnames so the
 * current nav item can be highlighted regardless of locale prefix.
 */
export function useActiveLink() {
    const page = usePage();
    const currentPath = computed(() => page.url.split("?")[0]);

    function isActive(url: string): boolean {
        return new URL(url).pathname === currentPath.value;
    }

    return { isActive };
}
