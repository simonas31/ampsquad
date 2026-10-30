import { Link } from "@inertiajs/react";
import { ShoppingBag } from "lucide-react";
import { CopySku } from "@/components/shop/CopySku";
import { ProductImage } from "@/components/shop/ProductImage";
import { ProductPrice } from "@/components/shop/ProductPrice";
import { StockBadge } from "@/components/shop/StockBadge";
import { Button } from "@/components/ui/button";
import { useAddToCart } from "@/hooks/use-add-to-cart";
import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";
import type { Product } from "@/types";

interface CompactProductCardProps {
  product: Product;
  /** `card` stacks the photograph above the details; `row` lays them out side by side from `sm` up. */
  layout?: "card" | "row";
}

/**
 * A product the way a trade catalog shows it: a photograph on white, the
 * manufacturer, the name, the SKU with a copy button, the price, and the
 * stock as a dot, with a one-click add. The photograph is a second link to
 * the product that keyboard and screen reader users skip, since the name
 * already leads there; the SKU's copy button and the add button sit outside
 * both links, so nothing interactive is nested.
 */
export function CompactProductCard({
  product,
  layout = "card",
}: CompactProductCardProps) {
  const t = useT();
  const addToCart = useAddToCart(product.id);
  const isRow = layout === "row";

  return (
    <article
      className={cn(
        "border-rule focus-within:shadow-ink/10 hover:shadow-ink/10 flex h-full flex-col rounded-xl border bg-white p-3 transition-shadow hover:shadow-lg focus-within:shadow-lg",
        isRow && "sm:flex-row sm:items-center sm:gap-5",
      )}
    >
      <Link
        href={product.url}
        tabIndex={-1}
        aria-hidden="true"
        className={cn("block", isRow && "sm:w-32 sm:shrink-0")}
      >
        <ProductImage
          imageUrl={product.imageThumbUrl ?? product.imageUrl}
          alt=""
          ratio="1 / 1"
          fit="contain"
          sizes={
            isRow
              ? "8rem"
              : "(min-width: 1280px) 16vw, (min-width: 640px) 30vw, 50vw"
          }
          className="rounded-lg bg-white"
        />
      </Link>

      <div className="flex min-w-0 flex-1 flex-col gap-1 pt-3 sm:pt-2">
        {product.manufacturer && (
          <p className="text-ink-soft truncate text-[0.8125rem]">
            {product.manufacturer}
          </p>
        )}
        <h3
          className={cn(
            "text-ink line-clamp-2 text-[0.9375rem] leading-snug font-semibold",
            !isRow && "min-h-[2.75em]",
          )}
        >
          <Link
            href={product.url}
            className="decoration-signal underline-offset-4 hover:underline hover:decoration-2 focus-visible:underline focus-visible:decoration-2"
          >
            {product.name}
          </Link>
        </h3>
        {product.sku && <CopySku sku={product.sku} />}
      </div>

      <div
        className={cn(
          "border-rule mt-3 border-t pt-3",
          isRow && "sm:mt-0 sm:w-60 sm:shrink-0 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-5",
        )}
      >
        <div className="flex items-end justify-between gap-3">
          <div className="min-w-0">
            <ProductPrice
              exclVatCents={product.priceExclVatCents}
              inclVatCents={product.priceInclVatCents}
            />
            <StockBadge
              inStock={product.inStock}
              lowStockCount={product.lowStockCount}
              className="mt-2"
            />
          </div>

          <Button
            size="icon"
            onClick={addToCart.submit}
            disabled={addToCart.processing || !product.inStock}
            aria-label={`${t("shop.addToCart")}: ${product.name}`}
            title={t("shop.addToCart")}
          >
            <ShoppingBag aria-hidden="true" />
          </Button>
        </div>

        <div role="status">
          {addToCart.added && (
            <p className="text-ink-soft pt-2 text-xs">{t("shop.added")}</p>
          )}
          {addToCart.error && (
            <p className="text-destructive pt-2 text-xs">{addToCart.error}</p>
          )}
        </div>
      </div>
    </article>
  );
}
