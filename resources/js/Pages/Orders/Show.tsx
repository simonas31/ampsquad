import { Link, usePage } from "@inertiajs/react";
import { Loader2 } from "lucide-react";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { AmountsSummary } from "@/components/shop/AmountsSummary";
import { Button } from "@/components/ui/button";
import { useNavUrl } from "@/hooks/use-nav-url";
import { useOrderPolling } from "@/hooks/use-order-polling";
import { useT } from "@/hooks/use-t";
import { formatClock, orderStage } from "@/lib/shop";
import type { Breadcrumb, OrderData } from "@/types";

interface OrderShowProps {
  order: OrderData;
  breadcrumbs: Breadcrumb[];
}

/**
 * An order's own page, which is where the payment provider sends the
 * customer back to. It never decides anything: it shows where the server says
 * the order stands, and while the order waits for its payment it asks the
 * server again every few seconds, because coming back from the bank proves
 * nothing and the confirmation arrives separately.
 */
export default function OrderShow({ order, breadcrumbs }: OrderShowProps) {
  const t = useT();
  const { locale } = usePage().props;
  const shopUrl = useNavUrl("nav.shop", "/shop");
  const contactUrl = useNavUrl("nav.contact", "/contact");
  const { polling, gaveUp } = useOrderPolling(order.status);
  const stage = orderStage(order.status);

  return (
    <>
      <PageHeader
        breadcrumbs={breadcrumbs}
        title={t("order.number", { number: order.number })}
        size="default"
      />

      <Container size="default" className="py-14 lg:py-20">
        <div className="grid grid-cols-1 gap-x-10 gap-y-12 lg:grid-cols-12">
          <div className="space-y-8 lg:col-span-7">
            {/* Read out whenever it changes, so a customer who waits is told when the payment lands. */}
            <div role="status" aria-live="polite" className="space-y-4">
              {stage === "paid" && (
                <>
                  <h2 className="display text-ink text-3xl">
                    {order.status === "shipped"
                      ? t("order.shippedTitle")
                      : t("order.paidTitle")}
                  </h2>
                  <p className="text-ink-soft max-w-[52ch] text-lg text-pretty">
                    {order.status === "shipped"
                      ? t("order.shippedText")
                      : t("order.paidText")}
                  </p>
                </>
              )}

              {stage === "waiting" && (
                <>
                  <h2 className="display text-ink text-3xl">
                    {t("order.waitingTitle")}
                  </h2>
                  <p className="text-ink-soft max-w-[52ch] text-lg text-pretty">
                    {t("order.waitingText")}
                  </p>
                  {polling && (
                    <p className="meta text-ink-soft flex items-center gap-2">
                      <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                      {t("order.checking")}
                    </p>
                  )}
                  {gaveUp && (
                    <p className="border-signal text-ink border-l-2 py-1 pl-4 text-sm">
                      {t("order.stillWaiting")}
                    </p>
                  )}
                </>
              )}

              {stage === "closed" && (
                <>
                  <h2 className="display text-ink text-3xl">
                    {t("order.closedTitle")}
                  </h2>
                  <p className="text-ink-soft max-w-[52ch] text-lg text-pretty">
                    {t("order.closedText")}
                  </p>
                </>
              )}

              {stage === "refunded" && (
                <>
                  <h2 className="display text-ink text-3xl">
                    {t("order.refundedTitle")}
                  </h2>
                  <p className="text-ink-soft max-w-[52ch] text-lg text-pretty">
                    {t("order.refundedText")}
                  </p>
                </>
              )}
            </div>

            {stage === "waiting" && order.payUrl && (
              <div className="space-y-3">
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

            {stage === "closed" && (
              <div className="flex flex-wrap gap-3">
                <Button asChild>
                  <Link href={shopUrl}>{t("order.backToShop")}</Link>
                </Button>
                <Button asChild variant="outline">
                  <Link href={contactUrl}>{t("order.contactUs")}</Link>
                </Button>
              </div>
            )}

            {stage === "paid" && (
              <div>
                <h3 className="display text-ink border-ink border-t pt-6 text-xl">
                  {t("order.delivery")}
                </h3>
                <p className="text-ink mt-3 font-semibold">{order.shipping.label}</p>
                {order.shipping.details && (
                  <p className="text-ink-soft mt-1 max-w-[52ch] text-pretty">
                    {order.shipping.details}
                  </p>
                )}
              </div>
            )}
          </div>

          <aside className="lg:col-span-5">
            <AmountsSummary
              heading={t("order.summary")}
              lines={order.lines.map((line) => ({
                key: line.name,
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
          </aside>
        </div>
      </Container>
    </>
  );
}
