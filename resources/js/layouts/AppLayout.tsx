import { usePage } from "@inertiajs/react";
import { useEffect, useRef, type ReactNode } from "react";
import { AppFooter } from "@/components/layout/AppFooter";
import { AppHeader } from "@/components/layout/AppHeader";
import { Seo } from "@/components/seo/Seo";
import { useT } from "@/hooks/use-t";
import type { SeoData } from "@/types";

/**
 * Applied to every page as Inertia's default layout (see app.tsx), so the
 * header, footer and <Head> tags persist across visits instead of being
 * unmounted and rebuilt by each page.
 */
export default function AppLayout({ children }: { children: ReactNode }) {
  const t = useT();
  const { url, props } = usePage<{ seo: SeoData }>();
  const mainRef = useRef<HTMLElement>(null);
  const previousPath = useRef(url.split("?")[0]);

  // Inertia navigation never reloads the document, so keyboard and screen
  // reader users would otherwise stay focused on the (possibly gone) link
  // they activated. Move focus to <main> when the path changes — but not
  // for query-only visits such as switching a filter.
  useEffect(() => {
    const path = url.split("?")[0];

    if (path !== previousPath.current) {
      previousPath.current = path;
      mainRef.current?.focus({ preventScroll: true });
    }
  }, [url]);

  return (
    <>
      <Seo seo={props.seo} />

      <a
        href="#main-content"
        className="bg-signal text-ink meta sr-only px-4 py-3 focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50"
      >
        {t("common.skipToContent")}
      </a>

      <div className="flex min-h-screen flex-col">
        <AppHeader />

        <main
          id="main-content"
          ref={mainRef}
          tabIndex={-1}
          className="flex-1 outline-none"
        >
          {children}
        </main>

        <AppFooter />
      </div>
    </>
  );
}
