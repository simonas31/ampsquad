import { Link, usePage } from "@inertiajs/react";
import { ShoppingBag } from "lucide-react";
import { ProductImage } from "@/components/shop/ProductImage";
import { Button } from "@/components/ui/button";
import { useAddToCart } from "@/hooks/use-add-to-cart";
import { useT } from "@/hooks/use-t";
import { formatCents } from "@/lib/shop";
import type { Product } from "@/types";

/**
 * A product as an image, a name that links to its page, a line of copy and
 * the price beside a one-click add. The photograph and name are one link;
 * the add button sits outside it so the card never nests interactive
 * elements.
 */
export function ProductCard({ product }: { product: Product }) {
  const t = useT();
  const { locale } = usePage().props;
  const addToCart = useAddToCart(product.id);

  return (
    <li className="group flex flex-col">
      <Link href={product.url} className="block">
        <ProductImage
          imageUrl={product.imageThumbUrl ?? product.imageUrl}
          alt=""
          ratio="4 / 3"
          zoom
          sizes="(min-width: 1024px) 30vw, (min-width: 640px) 45vw, 100vw"
        />
        <h3 className="display text-ink group-hover:decoration-signal group-focus-within:decoration-signal mt-5 text-2xl underline-offset-[8px] group-hover:underline group-hover:decoration-[3px] group-focus-within:underline">
          {product.name}
        </h3>
      </Link>

      {product.excerpt && (
        <p className="text-ink-soft mt-2 text-pretty">{product.excerpt}</p>
      )}

      <div className="mt-auto flex items-center justify-between gap-4 pt-5">
        <p className="text-ink font-semibold tabular-nums">
          {formatCents(product.priceCents, locale.current)}
        </p>

        <Button
          size="sm"
          onClick={addToCart.submit}
          disabled={addToCart.processing}
        >
          <ShoppingBag aria-hidden="true" />
          {addToCart.processing ? t("shop.adding") : t("shop.addToCart")}
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
