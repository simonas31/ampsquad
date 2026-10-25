import { router } from "@inertiajs/react";
import { useEffect, useState } from "react";

/** A visit shorter than this finishes before anyone would notice a dimmed page. */
const SHOW_AFTER_MS = 150;

/**
 * Whether a page-to-page visit (a link, a filter, a page of results) has
 * been waiting for a moment, so the listing can dim and say it is busy.
 * Background requests such as adding to the cart are not navigation and
 * do not count.
 */
export function useNavigating(): boolean {
  const [navigating, setNavigating] = useState(false);

  useEffect(() => {
    let timer: number | undefined;

    const stopStart = router.on("start", (event) => {
      if (event.detail.visit.method === "get") {
        timer = window.setTimeout(() => setNavigating(true), SHOW_AFTER_MS);
      }
    });
    const stopFinish = router.on("finish", () => {
      window.clearTimeout(timer);
      setNavigating(false);
    });

    return () => {
      window.clearTimeout(timer);
      stopStart();
      stopFinish();
    };
  }, []);

  return navigating;
}
