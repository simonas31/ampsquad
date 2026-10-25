import { Link, router, usePage } from "@inertiajs/react";
import { Trash2 } from "lucide-react";
import { useState } from "react";
import { ProductImage } from "@/components/shop/ProductImage";
import { QuantityStepper } from "@/components/shop/QuantityStepper";
import { useT } from "@/hooks/use-t";
import { CART_PROPS, formatCents } from "@/lib/shop";
import { cn } from "@/lib/utils";
import type { CartLine } from "@/types";

interface CartLineItemProps {
  line: CartLine;
}

/**
 * One line of the cart as a ruled row: photograph, name and unit price with
 * the quantity control, then the line total and a remove button. Changes go
 * straight to the server; the page re-renders with its recalculated totals,
 * so nothing is worked out on the client.
 *
 * A line with less in stock than it asked for shows only what is left, with
 * a note, and counts that. A line whose product is no longer sold keeps its
 * place, dimmed, with a reason instead of the quantity control and no total,
 * since nothing counts. It can only be removed.
 */
export function CartLineItem({ line }: CartLineItemProps) {
  const t = useT();
  const { locale } = usePage().props;
  const [processing, setProcessing] = useState(false);
  const [error, setError] = useState<string>();

  const visit = {
    preserveScroll: true,
    only: CART_PROPS,
    onStart: () => {
      setProcessing(true);
      setError(undefined);
    },
    onFinish: () => setProcessing(false),
    onError: (errors: Record<string, string>) =>
      setError(errors.quantity ?? errors.product_id),
    // Stay on the cart and say so, instead of swapping it for an error page.
    onHttpException: () => {
      setError(t("cart.updateFailed"));

      return false;
    },
    onNetworkError: () => setError(t("cart.updateFailed")),
  };

  const isUnavailable = line.status === "unavailable";

  const name = line.product.url ? (
    <Link
      href={line.product.url}
      className="display text-ink text-xl underline-offset-[6px] hover:underline hover:decoration-signal hover:decoration-[3px]"
    >
      {line.product.name}
    </Link>
  ) : (
    <span className="display text-ink-soft text-xl">{line.product.name}</span>
  );

  return (
    <li className="border-rule flex gap-5 border-t py-6 last:border-b">
      <ProductImage
        imageUrl={line.product.imageThumbUrl}
        alt=""
        ratio="1 / 1"
        sizes="112px"
        className={cn(
          "size-20 shrink-0 sm:size-28",
          isUnavailable && "opacity-50",
        )}
      />

      <div className="flex min-w-0 flex-1 flex-col gap-4 sm:flex-row sm:justify-between sm:gap-8">
        <div className="min-w-0">
          {name}
          <p className="meta text-ink-soft mt-1 tabular-nums">
            {t("cart.unitPrice", {
              price: formatCents(line.unitPriceCents, locale.current),
            })}
          </p>

          {!isUnavailable ? (
            <QuantityStepper
              className="mt-4"
              value={line.quantity}
              min={1}
              max={line.maxQuantity}
              label={t("cart.lineQuantity", { name: line.product.name })}
              decreaseLabel={t("shop.decreaseQuantity")}
              increaseLabel={t("shop.increaseQuantity")}
              disabled={processing}
              onChange={(quantity) =>
                router.patch(line.url, { quantity }, visit)
              }
            />
          ) : (
            <p className="text-destructive mt-3 max-w-[40ch] text-sm">
              {t("cart.unavailable")}
            </p>
          )}

          {line.status === "reduced" && (
            <p className="text-ink mt-3 max-w-[40ch] text-sm">
              {t("cart.reducedLine", {
                count: line.quantity,
                requested: line.requestedQuantity,
              })}
            </p>
          )}

          {error && (
            <p role="alert" className="text-destructive mt-2 text-sm">
              {error}
            </p>
          )}
        </div>

        <div className="flex items-center justify-between gap-6 sm:flex-col sm:items-end sm:justify-start">
          {!isUnavailable && (
            <p className="text-ink text-lg font-semibold tabular-nums">
              {formatCents(line.lineTotalCents, locale.current)}
            </p>
          )}

          <button
            type="button"
            disabled={processing}
            onClick={() => router.delete(line.url, visit)}
            aria-label={t("cart.remove", { name: line.product.name })}
            className="meta text-ink-soft hover:text-ink inline-flex min-h-11 items-center gap-2 transition-colors disabled:opacity-50"
          >
            <Trash2 className="size-4" aria-hidden="true" />
            <span className="hidden sm:inline">{t("cart.removeItem")}</span>
          </button>
        </div>
      </div>
    </li>
  );
}
