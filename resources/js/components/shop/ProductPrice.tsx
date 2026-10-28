import { usePage } from "@inertiajs/react";
import { useT } from "@/hooks/use-t";
import { formatCents } from "@/lib/shop";
import { cn } from "@/lib/utils";

interface ProductPriceProps {
  netCents: number;
  grossCents: number;
  /** `lg` is the product page's price, `sm` a listing card's. */
  size?: "sm" | "lg";
  className?: string;
}

/**
 * A price the way the shop states it: with VAT first, marked as such, and
 * the price without VAT beneath it. Both come from the server; nothing here
 * works out VAT.
 */
export function ProductPrice({
  netCents,
  grossCents,
  size = "sm",
  className,
}: ProductPriceProps) {
  const t = useT();
  const { locale } = usePage().props;

  return (
    <div className={className}>
      <p
        className={cn(
          "text-ink tabular-nums",
          size === "lg" ? "display text-4xl" : "font-semibold",
        )}
      >
        {formatCents(grossCents, locale.current)}{" "}
        <span className="meta text-ink-soft font-normal">
          {t("shop.withVat")}
        </span>
      </p>
      <p className="meta text-ink-soft mt-1 tabular-nums">
        {t("shop.withoutVat", {
          price: formatCents(netCents, locale.current),
        })}
      </p>
    </div>
  );
}
