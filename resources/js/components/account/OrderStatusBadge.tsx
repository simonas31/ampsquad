import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";
import type { OrderStatus } from "@/types";

/**
 * What colour says about an order: orange while it waits for the customer,
 * navy once the shop has it or has sent it, grey when it ended without a
 * sale, an outline when the money went back. The word is always there too,
 * so colour never carries the meaning alone.
 */
const tones: Record<OrderStatus, string> = {
  pending_payment: "bg-signal text-ink",
  paid: "bg-ink text-bone",
  shipped: "bg-ink text-bone",
  cancelled: "bg-concrete text-ink-soft",
  expired: "bg-concrete text-ink-soft",
  refunded: "border-ink text-ink border",
};

export function OrderStatusBadge({
  status,
  className,
}: {
  status: OrderStatus;
  className?: string;
}) {
  const t = useT();

  return (
    <span
      className={cn(
        "meta inline-block rounded-full px-3 py-1 whitespace-nowrap",
        tones[status],
        className,
      )}
    >
      {t(`account.orders.status.${status}`)}
    </span>
  );
}
