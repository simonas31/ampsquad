import { Link } from "@inertiajs/react";
import type { ReactNode } from "react";
import { Pagination } from "@/components/content/Pagination";
import { CompactProductCard } from "@/components/shop/CompactProductCard";
import { Button } from "@/components/ui/button";
import { useNavigating } from "@/hooks/use-navigating";
import { useT } from "@/hooks/use-t";
import { hasActiveFilters } from "@/lib/shop";
import { cn } from "@/lib/utils";
import type { Paginated, Product, ShopFilters } from "@/types";

interface ShopResultsProps {
  products: Paginated<Product>;
  filters: ShopFilters;
  /** The listing's own URL without a query string. */
  shopUrl: string;
  /** The grid's columns as `grid-cols-*` classes; a list is always one column. */
  gridClassName: string;
  view?: "grid" | "list";
  /** Heading above the count, for a results block that follows other sections. */
  heading?: string;
  /** Controls opposite the count, such as a view switch. */
  toolbar?: ReactNode;
}

/**
 * A page of results: the count, the products as cards (or rows), the page
 * links, and what to say when nothing matches. While a new page or a changed
 * filter is on its way the old products dim, so the page never looks frozen.
 */
export function ShopResults({
  products,
  filters,
  shopUrl,
  gridClassName,
  view = "grid",
  heading,
  toolbar,
}: ShopResultsProps) {
  const t = useT();
  const navigating = useNavigating();
  const isFiltered = hasActiveFilters(filters);

  return (
    <div
      aria-busy={navigating}
      className={cn("transition-opacity duration-200", navigating && "opacity-60")}
    >
      {heading && (
        <h2 className="display text-ink border-ink border-t pt-5 text-2xl uppercase sm:text-3xl">
          {heading}
        </h2>
      )}

      {products.data.length > 0 ? (
        <>
          <div
            className={cn(
              "flex flex-wrap items-center justify-between gap-4",
              heading && "mt-6",
            )}
          >
            <p role="status" className="meta text-ink-soft">
              {t("shop.productCount", { count: products.meta.total })}
            </p>
            {toolbar}
          </div>

          <ul
            className={cn(
              "mt-6 grid gap-4",
              view === "list" ? "grid-cols-1" : gridClassName,
            )}
          >
            {products.data.map((product) => (
              <li key={product.id}>
                <CompactProductCard
                  product={product}
                  layout={view === "list" ? "row" : "card"}
                />
              </li>
            ))}
          </ul>

          <div className="mt-12">
            <Pagination pagination={products} />
          </div>
        </>
      ) : (
        <div role="status" className="border-ink mt-6 border-t py-20">
          <p className="display text-ink-soft max-w-[18ch] text-3xl sm:text-4xl">
            {isFiltered ? t("shop.noResults") : t("shop.noProducts")}
          </p>

          {isFiltered && (
            <Button asChild variant="link" className="mt-6">
              <Link href={shopUrl}>{t("shop.clearFilters")}</Link>
            </Button>
          )}
        </div>
      )}
    </div>
  );
}
