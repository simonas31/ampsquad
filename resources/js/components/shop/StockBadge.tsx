import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";

interface StockBadgeProps {
  inStock: boolean;
  /** How many are left when only a few are; null for a well-stocked product. */
  lowStockCount: number | null;
  className?: string;
}

/**
 * Availability as a coloured dot and a word, so it never relies on colour
 * alone: green when in stock, amber when only a few are left, red when sold
 * out.
 */
export function StockBadge({
  inStock,
  lowStockCount,
  className,
}: StockBadgeProps) {
  const t = useT();
  const isLow = inStock && lowStockCount !== null;

  return (
    <p
      className={cn(
        "text-ink-soft flex items-center gap-2 text-[0.8125rem]",
        className,
      )}
    >
      <span
        aria-hidden="true"
        className={cn(
          "size-2 shrink-0 rounded-full",
          !inStock && "bg-destructive",
          isLow && "bg-amber-500",
          inStock && !isLow && "bg-emerald-600",
        )}
      />
      {!inStock
        ? t("shop.outOfStock")
        : isLow
          ? t("shop.lowStock", { count: lowStockCount })
          : t("shop.inStock")}
    </p>
  );
}
