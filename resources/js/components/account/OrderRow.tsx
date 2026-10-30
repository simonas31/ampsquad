import { Link } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { useT } from "@/hooks/use-t";
import { formatCents, formatDate } from "@/lib/shop";
import type { AccountOrderSummary, Locale } from "@/types";
import { OrderStatusBadge } from "./OrderStatusBadge";

/**
 * One order as a row: its number, when it was placed, what it comes to and
 * where it stands, with the way to pay while that is still open. Used by the
 * list of orders and by the dashboard's short one.
 */
export function OrderRow({
  order,
  localeCode,
}: {
  order: AccountOrderSummary;
  localeCode: Locale;
}) {
  const t = useT();

  return (
    <article
      aria-label={t("order.number", { number: order.number })}
      className="border-rule rounded-2xl border bg-white p-5 sm:p-6"
    >
      <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
        <div className="min-w-0">
          <h2 className="display text-ink text-xl break-words">
            <Link
              href={order.url}
              className="underline-offset-4 hover:underline"
            >
              {order.number}
            </Link>
          </h2>
          <p className="text-ink-soft mt-1 text-sm">
            {t("account.orders.placed", {
              date: formatDate(order.placedAt, localeCode),
            })}
            {" · "}
            {t("account.orders.items", { count: order.itemCount })}
          </p>
        </div>
        <div className="flex flex-col items-start gap-2 sm:items-end">
          <OrderStatusBadge status={order.status} />
          <p className="text-ink display text-2xl tabular-nums">
            {formatCents(order.totalInclVatCents, localeCode)}
          </p>
        </div>
      </div>

      <div className="mt-5 flex flex-wrap items-center gap-3">
        {order.payUrl && (
          <Button asChild size="sm" variant="accent">
            <a href={order.payUrl}>{t("order.payNow")}</a>
          </Button>
        )}
        <Button asChild size="sm" variant="outline">
          <Link href={order.url}>{t("account.orders.view")}</Link>
        </Button>
      </div>
    </article>
  );
}
