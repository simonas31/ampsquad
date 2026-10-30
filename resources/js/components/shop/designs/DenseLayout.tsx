import { LayoutGrid, List } from "lucide-react";
import { useState } from "react";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import type { ShopListingProps } from "@/components/shop/designs/types";
import { PopularCarousel } from "@/components/shop/PopularCarousel";
import { ShopResults } from "@/components/shop/ShopResults";
import { ShopSidebar } from "@/components/shop/ShopSidebar";
import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";

type View = "grid" | "list";

/**
 * Design C, for people who know what they are after. The filter sidebar is
 * always there; the plain shop adds a short popular shelf above the catalog,
 * and the catalog can be read as a grid or as one row per product with its
 * SKU and price in line.
 */
export function DenseLayout({
  products,
  filters,
  categories,
  manufacturers,
  shopUrl,
  breadcrumbs,
  popularProducts,
}: ShopListingProps) {
  const t = useT();
  const [view, setView] = useState<View>("grid");
  const isLanding = popularProducts !== null;

  return (
    <>
      <PageHeader breadcrumbs={breadcrumbs} title={t("nav.shop")} />

      <Container size="wide" className="py-12 lg:py-16">
        <div className="grid grid-cols-1 gap-x-10 gap-y-10 lg:grid-cols-12">
          <ShopSidebar
            filters={filters}
            categories={categories}
            manufacturers={manufacturers}
            baseUrl={shopUrl}
            className="lg:col-span-3"
          />

          <div className="min-w-0 space-y-14 lg:col-span-9">
            {isLanding && (
              <PopularCarousel
                products={popularProducts}
                slideClassName="basis-[70%] min-[28rem]:basis-1/2 md:basis-1/3 xl:basis-1/4"
              />
            )}

            <ShopResults
              products={products}
              filters={filters}
              shopUrl={shopUrl}
              view={view}
              gridClassName="grid-cols-1 min-[28rem]:grid-cols-2 xl:grid-cols-3"
              heading={isLanding ? t("shop.allProducts") : undefined}
              toolbar={<ViewSwitch view={view} onChange={setView} />}
            />
          </div>
        </div>
      </Container>
    </>
  );
}

function ViewSwitch({
  view,
  onChange,
}: {
  view: View;
  onChange: (view: View) => void;
}) {
  const t = useT();
  const options = [
    { view: "grid", label: t("shop.view.grid"), Icon: LayoutGrid },
    { view: "list", label: t("shop.view.list"), Icon: List },
  ] as const;

  return (
    <div
      role="group"
      aria-label={t("shop.view.label")}
      className="border-rule flex rounded-full border bg-white p-0.5"
    >
      {options.map(({ view: option, label, Icon }) => (
        <button
          key={option}
          type="button"
          aria-pressed={view === option}
          onClick={() => onChange(option)}
          className={cn(
            "flex min-h-9 items-center gap-2 rounded-full px-4 text-sm font-medium transition-colors",
            view === option ? "bg-ink text-bone" : "text-ink hover:bg-concrete",
          )}
        >
          <Icon className="size-4" aria-hidden="true" />
          {label}
        </button>
      ))}
    </div>
  );
}
