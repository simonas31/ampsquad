import { useIntersectionObserver, usePreferredReducedMotion } from "@vueuse/core";
import { ref, type Ref } from "vue";

/**
 * Toggles `visible` to true the first time the target scrolls into view, then
 * stops observing — sections animate in once and stay put, they don't
 * re-trigger on scroll-back. Reduced-motion users get `visible` true
 * immediately so nothing depends on the observer firing.
 */
export function useScrollReveal(): {
    target: Ref<HTMLElement | null>;
    visible: Ref<boolean>;
} {
    const target = ref<HTMLElement | null>(null);
    const prefersReducedMotion = usePreferredReducedMotion();
    const visible = ref(prefersReducedMotion.value === "reduce");

    if (!visible.value) {
        const { stop } = useIntersectionObserver(
            target,
            ([entry]) => {
                if (entry?.isIntersecting) {
                    visible.value = true;
                    stop();
                }
            },
            { threshold: 0.15 },
        );
    }

    return { target, visible };
}
