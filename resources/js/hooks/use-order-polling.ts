import { router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import {
  ORDER_POLL_INTERVAL_MS,
  ORDER_POLL_LIMIT,
  shouldPollOrder,
} from "@/lib/shop";
import type { OrderStatus } from "@/types";

/**
 * Asks the server again about an order that is waiting for its payment,
 * every few seconds and for a while, until it is no longer waiting. It
 * reloads only the `order` prop, so each check is one small request and
 * nothing else on the page is rebuilt. `gaveUp` is true once it stopped
 * with the order still waiting.
 */
export function useOrderPolling(status: OrderStatus): {
  polling: boolean;
  gaveUp: boolean;
} {
  const [attempts, setAttempts] = useState(0);
  const polling = shouldPollOrder(status, attempts);

  useEffect(() => {
    if (!polling) {
      return;
    }

    const timer = window.setTimeout(() => {
      setAttempts((count) => count + 1);
      router.reload({ only: ["order"] });
    }, ORDER_POLL_INTERVAL_MS);

    return () => window.clearTimeout(timer);
  }, [polling, attempts]);

  return {
    polling,
    gaveUp: status === "pending_payment" && attempts >= ORDER_POLL_LIMIT,
  };
}
