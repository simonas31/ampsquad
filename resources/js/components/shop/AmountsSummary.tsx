import { usePage } from "@inertiajs/react";
import { ProductImage } from "@/components/shop/ProductImage";
import { Card } from "@/components/ui/card";
import { useT } from "@/hooks/use-t";
import { formatCents } from "@/lib/shop";

interface AmountsSummaryProps {
  heading: string;
  lines: Array<{
    key: string | number;
    name: string;
    quantity: number;
    totalInclVatCents: number;
    imageUrl: string | null;
  }>;
  /** The products, without VAT. */
  subtotalExclVatCents: number;
  /** Delivery without VAT, and what it is called; omitted until one is chosen. */
  shipping?: { name: string; priceExclVatCents: number };
  vatRate: number;
  vatCents: number;
  totalInclVatCents: number;
}

/**
 * What an order is made of and what it comes to: its products, the delivery,
 * the VAT and the total, all as the server worked them out. Used by checkout
 * (before the order exists) and by the order's own page (after), so the two
 * can never read differently.
 */
export function AmountsSummary({
  heading,
  lines,
  subtotalExclVatCents,
  shipping,
  vatRate,
  vatCents,
  totalInclVatCents,
}: AmountsSummaryProps) {
  const t = useT();
  const { locale } = usePage().props;
  const money = (cents: number) => formatCents(cents, locale.current);

  return (
    <Card className="p-6">
      <h2 className="display text-ink text-2xl">{heading}</h2>

      <ul className="mt-6">
        {lines.map((line) => (
          <li
            key={line.key}
            className="border-rule flex items-center gap-4 border-t py-3 first:border-t-0 first:pt-0"
          >
            <ProductImage
              imageUrl={line.imageUrl}
              alt=""
              ratio="1 / 1"
              sizes="56px"
              className="size-14 shrink-0"
            />
            <span className="text-ink min-w-0 flex-1">
              <span className="block break-words">{line.name}</span>
              <span className="meta text-ink-soft">
                {t("checkout.quantity", { count: line.quantity })}
              </span>
            </span>
            <span className="text-ink shrink-0 tabular-nums">
              {money(line.totalInclVatCents)}
            </span>
          </li>
        ))}
      </ul>

      <dl className="border-rule mt-3 space-y-3 border-t pt-6">
        <div className="text-ink-soft flex justify-between gap-4">
          <dt>{t("checkout.products")}</dt>
          <dd className="tabular-nums">{money(subtotalExclVatCents)}</dd>
        </div>
        {shipping && (
          <div className="text-ink-soft flex justify-between gap-4">
            <dt>
              {t("checkout.delivery")}
              <span className="meta block">{shipping.name}</span>
            </dt>
            <dd className="tabular-nums">{money(shipping.priceExclVatCents)}</dd>
          </div>
        )}
        <div className="text-ink-soft flex justify-between gap-4">
          <dt>{t("checkout.vat", { rate: vatRate })}</dt>
          <dd className="tabular-nums">{money(vatCents)}</dd>
        </div>
        <div className="text-ink border-rule flex items-baseline justify-between gap-4 border-t pt-3">
          <dt className="font-semibold">{t("checkout.total")}</dt>
          <dd className="display text-3xl tabular-nums">
            {money(totalInclVatCents)}
          </dd>
        </div>
      </dl>
    </Card>
  );
}
