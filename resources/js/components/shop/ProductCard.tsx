import { Link } from "@inertiajs/react";
import { ShoppingBag } from "lucide-react";
import { ProductImage } from "@/components/shop/ProductImage";
import { ProductPrice } from "@/components/shop/ProductPrice";
import { Button } from "@/components/ui/button";
import { useAddToCart } from "@/hooks/use-add-to-cart";
import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";
import type { Product } from "@/types";

/**
 * A product as an image, a name that links to its page, a line of copy and
 * the price beside a one-click add. The photograph and name are one link;
 * the add button sits outside it so the card never nests interactive
 * elements. A sold-out product keeps its card, marked on the photograph,
 * with the button switched off.
 */
export function ProductCard({ product }: { product: Product }) {
  const t = useT();
  const addToCart = useAddToCart(product.id);

  return (
    <li className="group flex flex-col">
      <Link href={product.url} className="block">
        <div className="relative">
          <ProductImage
            imageUrl={product.imageThumbUrl ?? product.imageUrl}
            alt=""
            ratio="4 / 3"
            zoom
            sizes="(min-width: 1280px) 22vw, (min-width: 640px) 45vw, 100vw"
          />

          {!product.inStock && (
            <span className="meta bg-ink text-bone absolute top-3 left-3 rounded-full px-3 py-1">
              {t("shop.outOfStock")}
            </span>
          )}
        </div>

        {product.manufacturer && (
          <p className="meta text-ink-soft mt-5">{product.manufacturer}</p>
        )}
        <h2
          className={cn(
            "display text-ink group-hover:decoration-signal group-focus-within:decoration-signal text-2xl underline-offset-[8px] group-hover:underline group-hover:decoration-[3px] group-focus-within:underline",
            product.manufacturer ? "mt-1" : "mt-5",
          )}
        >
          {product.name}
        </h2>
      </Link>

      {product.excerpt && (
        <p className="text-ink-soft mt-2 line-clamp-3 text-pretty">
          {product.excerpt}
        </p>
      )}

      <div className="mt-auto flex items-center justify-between gap-4 pt-5">
        <ProductPrice
          exclVatCents={product.priceExclVatCents}
          inclVatCents={product.priceInclVatCents}
        />

        <Button
          size="sm"
          className="min-h-11"
          onClick={addToCart.submit}
          disabled={addToCart.processing || !product.inStock}
        >
          <ShoppingBag aria-hidden="true" />
          {product.inStock
            ? addToCart.processing
              ? t("shop.adding")
              : t("shop.addToCart")
            : t("shop.outOfStock")}
        </Button>
      </div>

      <div role="status" className="min-h-5 pt-2">
        {addToCart.added && (
          <p className="meta text-ink-soft">{t("shop.added")}</p>
        )}
        {addToCart.error && (
          <p className="text-destructive text-sm">{addToCart.error}</p>
        )}
      </div>
    </li>
  );
}
