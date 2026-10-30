import { SlidersHorizontal } from "lucide-react";
import { useState } from "react";
import { CategoryTree } from "@/components/shop/CategoryTree";
import { ShopFilterForm } from "@/components/shop/ShopFilterForm";
import { Button } from "@/components/ui/button";
import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";
import type { CategoryNode, ManufacturerOption, ShopFilters } from "@/types";

interface ShopSidebarProps {
  filters: ShopFilters;
  categories: CategoryNode[];
  manufacturers: ManufacturerOption[];
  /** The listing's own URL without a query string. */
  baseUrl: string;
  className?: string;
}

/**
 * The filter form and the category list as a column beside the results. On
 * small screens they fold away behind a button above the products; from `lg`
 * up the button has no effect.
 */
export function ShopSidebar({
  filters,
  categories,
  manufacturers,
  baseUrl,
  className,
}: ShopSidebarProps) {
  const t = useT();
  const [filtersOpen, setFiltersOpen] = useState(false);

  return (
    <aside className={className}>
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
          baseUrl={baseUrl}
        />
        <CategoryTree nodes={categories} filters={filters} baseUrl={baseUrl} />
      </div>
    </aside>
  );
}
