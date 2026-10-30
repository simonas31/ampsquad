import { router } from "@inertiajs/react";
import { useEffect, useState } from "react";

/**
 * A visit shorter than this finishes before anyone would see a spinner, and
 * one that flashed up for a blink would only be a flicker.
 */
const SHOW_AFTER_MS = 120;

/**
 * The path of the page a link is taking the visitor to, for as long as the
 * visit has been waiting for a moment; null when none is. It lets the link
 * that was pressed say it is working, instead of leaving the visitor to
 * press it again. Only page-to-page visits count: a form posting in the
 * background is not navigation.
 */
export function usePendingPath(): string | null {
  const [pending, setPending] = useState<string | null>(null);

  useEffect(() => {
    let timer: number | undefined;

    const stopStart = router.on("start", (event) => {
      const { visit } = event.detail;

      if (visit.method === "get") {
        window.clearTimeout(timer);
        timer = window.setTimeout(
          () => setPending(visit.url.pathname.replace(/\/$/, "")),
          SHOW_AFTER_MS,
        );
      }
    });
    const stopFinish = router.on("finish", () => {
      window.clearTimeout(timer);
      setPending(null);
    });

    return () => {
      window.clearTimeout(timer);
      stopStart();
      stopFinish();
    };
  }, []);

  return pending;
}
