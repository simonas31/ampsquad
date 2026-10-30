import { Link, usePage } from "@inertiajs/react";
import { AccountPage } from "@/components/account/AccountPage";
import { OrderStatusBadge } from "@/components/account/OrderStatusBadge";
import { AmountsSummary } from "@/components/shop/AmountsSummary";
import { Button } from "@/components/ui/button";
import { useT } from "@/hooks/use-t";
import {
  formatClock,
  formatDate,
  formatDateTime,
  orderStage,
} from "@/lib/shop";
import type { AccountOrderData, Breadcrumb } from "@/types";

interface ShowProps {
  order: AccountOrderData;
  breadcrumbs: Breadcrumb[];
}

/**
 * One of the customer's orders: where it stands (the order's own status, in
 * words), the way to pay while that is open, the moments of its life, where
 * it goes, and what it came to. It only reads: an order moves along when the
 * shop or the payment provider moves it, never from here.
 */
export default function Show({ order, breadcrumbs }: ShowProps) {
  const t = useT();
  const { props } = usePage();
  const { account, locale } = props;
  const stage = orderStage(order.status);

  const stageText = {
    paid: order.status === "shipped" ? "shipped" : "paid",
    waiting: "waiting",
    closed: "closed",
    refunded: "refunded",
  }[stage] as "paid" | "shipped" | "waiting" | "closed" | "refunded";

  return (
    <AccountPage
      breadcrumbs={breadcrumbs}
      title={t("order.number", { number: order.number })}
      description={t("account.orders.placed", {
        date: formatDate(order.placedAt, locale.current),
      })}
    >
      <div className="space-y-12">
        <section aria-labelledby="status-heading" className="space-y-4">
          <h2 id="status-heading" className="sr-only">
            {t("account.orders.timelineTitle")}
          </h2>
          <OrderStatusBadge status={order.status} />
          <p className="text-ink max-w-[56ch] text-lg text-pretty">
            {t(`account.orders.stageText.${stageText}`)}
          </p>

          {stage === "waiting" && order.payUrl && (
            <div className="space-y-3 pt-2">
              <Button asChild variant="accent" size="lg">
                <a href={order.payUrl}>{t("order.payNow")}</a>
              </Button>
              <p className="meta text-ink-soft">
                {t("order.payUntil", {
                  time: formatClock(order.paymentExpiresAt, locale.current),
                })}
              </p>
            </div>
          )}
        </section>

        <section aria-labelledby="timeline-heading" className="space-y-5">
          <h2 id="timeline-heading" className="display text-ink text-2xl">
            {t("account.orders.timelineTitle")}
          </h2>
          <ol className="border-rule relative space-y-5 border-l pl-6">
            {order.timeline.map((moment) => (
              <li key={moment.key} className="relative">
                <span
                  aria-hidden="true"
                  className="bg-ink absolute top-1.5 -left-[1.9rem] size-2.5 rounded-full"
                />
                <p className="text-ink font-medium">
                  {t(`account.orders.timeline.${moment.key}`)}
                </p>
                <p className="meta text-ink-soft">
                  <time dateTime={moment.at}>
                    {formatDateTime(moment.at, locale.current)}
                  </time>
                </p>
              </li>
            ))}
          </ol>
        </section>

        <section aria-labelledby="delivery-heading" className="space-y-3">
          <h2 id="delivery-heading" className="display text-ink text-2xl">
            {t("account.orders.deliveryTitle")}
          </h2>
          <p className="text-ink font-semibold">{order.shipping.label}</p>
          {order.shipping.details && (
            <p className="text-ink-soft max-w-[56ch] text-pretty">
              {order.shipping.details}
            </p>
          )}
          {order.deliveryAddress ? (
            <div>
              <p className="meta text-ink-soft">
                {t("account.orders.deliveryAddress")}
              </p>
              <address className="text-ink mt-1 max-w-[56ch] not-italic">
                {order.deliveryAddress}
              </address>
            </div>
          ) : (
            <p className="text-ink-soft max-w-[56ch] text-pretty">
              {t("account.orders.pickup")}
            </p>
          )}
          {order.customerNote && (
            <div>
              <p className="meta text-ink-soft">{t("account.orders.note")}</p>
              <p className="text-ink mt-1 max-w-[56ch] text-pretty">
                {order.customerNote}
              </p>
            </div>
          )}
        </section>

        <AmountsSummary
          heading={t("order.summary")}
          lines={order.lines.map((line, index) => ({
            key: `${index}-${line.sku ?? line.name}`,
            name: line.name,
            quantity: line.quantity,
            totalInclVatCents: line.lineTotalInclVatCents,
            imageUrl: line.imageThumbUrl,
          }))}
          subtotalExclVatCents={order.subtotalExclVatCents}
          shipping={{
            name: order.shipping.label,
            priceExclVatCents: order.shippingExclVatCents,
          }}
          vatRate={order.vatRate}
          vatCents={order.vatCents}
          totalInclVatCents={order.totalInclVatCents}
        />

        <Button asChild variant="outline">
          <Link href={account.ordersUrl}>{t("account.orders.back")}</Link>
        </Button>
      </div>
    </AccountPage>
  );
}
