import { Link } from "@inertiajs/react";
import { SlidersHorizontal } from "lucide-react";
import { useState } from "react";
import { Reveal } from "@/components/common/Reveal";
import { Pagination } from "@/components/content/Pagination";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { CategoryTree } from "@/components/shop/CategoryTree";
import { ProductCard } from "@/components/shop/ProductCard";
import { ShopFilterForm } from "@/components/shop/ShopFilterForm";
import { Button } from "@/components/ui/button";
import { useNavigating } from "@/hooks/use-navigating";
import { useT } from "@/hooks/use-t";
import { hasActiveFilters } from "@/lib/shop";
import { cn } from "@/lib/utils";
import type {
  Breadcrumb,
  CategoryNode,
  ManufacturerOption,
  Paginated,
  Product,
  ShopFilters,
} from "@/types";

interface IndexProps {
  products: Paginated<Product>;
  filters: ShopFilters;
  categories: CategoryNode[];
  manufacturers: ManufacturerOption[];
  /** The listing's own URL without a query string; filter links are built from it. */
  shopUrl: string;
  breadcrumbs: Breadcrumb[];
}

export default function Index({
  products,
  filters,
  categories,
  manufacturers,
  shopUrl,
  breadcrumbs,
}: IndexProps) {
  const t = useT();
  // On small screens the filters fold away above the products; from `lg`
  // up they sit beside them and this has no effect.
  const [filtersOpen, setFiltersOpen] = useState(false);
  const isFiltered = hasActiveFilters(filters);
  // A new page of results or a changed filter takes a moment; until it
  // arrives the old products dim, so the page never looks frozen.
  const navigating = useNavigating();

  return (
    <>
      <PageHeader breadcrumbs={breadcrumbs} title={t("nav.shop")} />

      <Container size="wide" className="py-14 lg:py-20">
        <div className="grid grid-cols-1 gap-x-12 gap-y-10 lg:grid-cols-12">
          <aside className="lg:col-span-3">
            <Button
              type="button"
              variant="outline"
              className="w-full lg:hidden"
              aria-expanded={filtersOpen}
              aria-controls="shop-filters"
              onClick={() => setFiltersOpen((open) => !open)}
            >
              <SlidersHorizontal aria-hidden="true" />
              {filtersOpen ? t("shop.filters.hide") : t("shop.filters.show")}
            </Button>

            <div
              id="shop-filters"
              className={cn(
                "space-y-10",
                filtersOpen ? "mt-8 block" : "hidden",
                "lg:mt-0 lg:block",
              )}
            >
              <ShopFilterForm
                filters={filters}
                manufacturers={manufacturers}
                baseUrl={shopUrl}
              />
              <CategoryTree
                nodes={categories}
                filters={filters}
                baseUrl={shopUrl}
              />
            </div>
          </aside>

          <div
            aria-busy={navigating}
            className={cn(
              "transition-opacity duration-200 lg:col-span-9",
              navigating && "opacity-60",
            )}
          >
            {products.data.length > 0 ? (
              <>
                <p role="status" className="meta text-ink-soft mb-8">
                  {t("shop.productCount", { count: products.meta.total })}
                </p>

                {/* Twelve cards stacked on a phone are taller than any screen. */}
                <Reveal threshold={0}>
                  <ul className="grid grid-cols-1 gap-x-8 gap-y-14 sm:grid-cols-2 xl:grid-cols-3">
                    {products.data.map((product) => (
                      <ProductCard key={product.id} product={product} />
                    ))}
                  </ul>
                </Reveal>

                <div className="mt-14">
                  <Pagination pagination={products} />
                </div>
              </>
            ) : (
              <div role="status" className="border-ink border-t py-20">
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
        </div>
      </Container>
    </>
  );
}
