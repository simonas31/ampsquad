import { router } from "@inertiajs/react";
import { Search } from "lucide-react";
import { Breadcrumbs } from "@/components/content/Breadcrumbs";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { BrandPanel } from "@/components/shop/BrandPanel";
import { CategoryChips } from "@/components/shop/CategoryChips";
import { CategoryTiles } from "@/components/shop/CategoryTiles";
import type { ShopListingProps } from "@/components/shop/designs/types";
import { PopularCarousel } from "@/components/shop/PopularCarousel";
import { ShopFilterForm } from "@/components/shop/ShopFilterForm";
import { ShopResults } from "@/components/shop/ShopResults";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useT } from "@/hooks/use-t";
import { shopUrl as buildShopUrl } from "@/lib/shop";

/**
 * Design B, built around browsing. The plain shop opens with one big search
 * box, a tile for every category, the popular shelf, the banner, and then
 * the catalog with its filters in a bar above it (no sidebar). Any search,
 * filter or later page drops the opening and shows the filter bar, the
 * categories one step down from where the visitor is, and the results.
 */
export function TilesLayout({
  products,
  filters,
  categories,
  manufacturers,
  shopUrl,
  breadcrumbs,
  popularProducts,
  categoryMenu,
}: ShopListingProps) {
  const t = useT();
  const isLanding = popularProducts !== null && categoryMenu !== null;

  function search(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    const query = String(new FormData(event.currentTarget).get("q") ?? "");

    router.get(buildShopUrl(shopUrl, { search: query }));
  }

  return (
    <>
      {isLanding ? (
        <Container size="wide" className="pt-6 lg:pt-8">
          <Breadcrumbs items={breadcrumbs} />
          <h1 className="sr-only">{t("nav.shop")}</h1>

          <form
            role="search"
            aria-label={t("shop.filters.search")}
            onSubmit={search}
            className="bg-plaster mt-6 flex flex-col gap-3 rounded-2xl p-3 sm:flex-row sm:p-4"
          >
            <label htmlFor="shop-hero-search" className="sr-only">
              {t("shop.filters.search")}
            </label>
            <Input
              id="shop-hero-search"
              type="search"
              name="q"
              placeholder={t("shop.filters.searchPlaceholder")}
              maxLength={100}
              className="h-14 flex-1 text-base"
            />
            <Button type="submit" size="lg">
              <Search aria-hidden="true" />
              {t("shop.filters.apply")}
            </Button>
          </form>

          <div className="mt-12">
            <CategoryTiles nodes={categoryMenu} baseUrl={shopUrl} />
          </div>

          <div className="mt-14">
            <PopularCarousel
              products={popularProducts}
              slideClassName="basis-[70%] min-[28rem]:basis-1/2 md:basis-1/3 lg:basis-1/4 xl:basis-1/5 2xl:basis-1/6"
            />
          </div>

          <BrandPanel className="mt-14 min-h-72" />
        </Container>
      ) : (
        <PageHeader breadcrumbs={breadcrumbs} title={t("nav.shop")} />
      )}

      <Container size="wide" className="py-12 lg:py-16">
        <div className="bg-plaster rounded-2xl p-5 lg:p-6">
          <ShopFilterForm
            filters={filters}
            manufacturers={manufacturers}
            baseUrl={shopUrl}
            layout="bar"
          />
        </div>

        {!isLanding && (
          <div className="mt-8">
            <CategoryChips
              nodes={categories}
              filters={filters}
              baseUrl={shopUrl}
            />
          </div>
        )}

        <div className="mt-10">
          <ShopResults
            products={products}
            filters={filters}
            shopUrl={shopUrl}
            gridClassName="grid-cols-1 min-[28rem]:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5"
            heading={isLanding ? t("shop.allProducts") : undefined}
          />
        </div>
      </Container>
    </>
  );
}
