import "../css/app.css";

import {
  createInertiaApp,
  router,
  type ResolvedComponent,
} from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import AppLayout from "@/layouts/AppLayout";

const appName = import.meta.env.VITE_APP_NAME ?? "Laravel";

// The initial response server-renders SEO tags for crawlers/social bots that
// never run JS (see app.blade.php). Once React takes over, the <Seo>
// component keeps them fresh across SPA navigation, so the server-rendered
// copies are removed here to avoid duplicates.
document
  .querySelectorAll('[data-seo="server"]')
  .forEach((node) => node.remove());

// <html lang> is only set from Blade on a full page load — Inertia's SPA
// navigation (e.g. clicking the language switcher) swaps props without
// reloading the document, so it needs re-syncing on every visit too.
router.on("navigate", (event) => {
  document.documentElement.lang = event.detail.page.props.locale.current;
});

// SeoData::make() already computes the full "Fragment - AppName" (or bare
// AppName) title server-side, once, so both Blade's initial render and this
// client-side title get exactly the same string — this just passes it
// through instead of appending the app name a second time.
createInertiaApp({
  title: (title) => title || appName,
  resolve: async (name) => {
    const page = await resolvePageComponent<{ default: ResolvedComponent }>(
      `./Pages/${name}.tsx`,
      import.meta.glob<{ default: ResolvedComponent }>("./Pages/**/*.tsx"),
    );

    return page.default;
  },
  layout: () => AppLayout,
  strictMode: true,
  progress: {
    color: "#1a1a17",
  },
});
