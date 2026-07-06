import "../css/app.css";

import { createApp, h, type DefineComponent } from "vue";
import { createInertiaApp, router } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { i18n } from "./i18n";
import type { SharedData } from "./types";

const appName = import.meta.env.VITE_APP_NAME ?? "Laravel";

// SeoData::make() already computes the full "Fragment - AppName" (or bare
// AppName) title server-side, once, so both Blade's initial render and this
// client-side title get exactly the same string — this just passes it
// through instead of appending the app name a second time.
function syncLocale(current: string): void {
    document.documentElement.lang = current;
    i18n.global.locale.value = current as "lt" | "en";
}

createInertiaApp({
    title: (title) => title || appName,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>("./Pages/**/*.vue"),
        ),
    setup({ el, App, props, plugin }) {
        // The initial response server-renders SEO tags for crawlers/social
        // bots that never run JS (see app.blade.php). Once Vue takes over,
        // its own <Seo> component keeps them fresh across SPA navigation, so
        // the server-rendered copies are removed here to avoid duplicates.
        document.querySelectorAll('[data-seo="server"]').forEach((node) => node.remove());

        const initialPageProps = props.initialPage.props as unknown as SharedData;
        syncLocale(initialPageProps.locale.current);

        // <html lang> and vue-i18n's active locale are only set from Blade
        // on a full page load — Inertia's SPA navigation (e.g. clicking the
        // language switcher) swaps props without reloading the document, so
        // both need to be re-synced on every subsequent visit too.
        router.on("navigate", (event) => {
            const page = event.detail.page.props as unknown as SharedData;
            syncLocale(page.locale.current);
        });

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: "#f59e0b",
    },
});
