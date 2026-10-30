import { Breadcrumbs } from "@/components/content/Breadcrumbs";
import type { ShopListingProps } from "@/components/shop/designs/types";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { BrandPanel } from "@/components/shop/BrandPanel";
import { CategoryMenu } from "@/components/shop/CategoryMenu";
import { PopularCarousel } from "@/components/shop/PopularCarousel";
import { ShopResults } from "@/components/shop/ShopResults";
import { ShopSidebar } from "@/components/shop/ShopSidebar";
import { useT } from "@/hooks/use-t";

/**
 * Design A, after a trade catalog's front page. The plain shop opens with
 * the category menu (hover panels for sub-categories) beside the banner,
 * the popular products shelf under them, and then the whole catalog with
 * its filters in a sidebar. Any search, filter or later page drops the
 * opening and is only the sidebar and the results.
 */
export function PortalLayout({
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

  return (
    <>
      {isLanding ? (
        <Container size="wide" className="pt-6 lg:pt-8">
          <Breadcrumbs items={breadcrumbs} />
          <h1 className="sr-only">{t("nav.shop")}</h1>

          <div className="mt-6 grid gap-6 lg:grid-cols-[17.5rem_minmax(0,1fr)]">
            <CategoryMenu nodes={categoryMenu} baseUrl={shopUrl} />
            <BrandPanel />
          </div>

          <div className="mt-14">
            <PopularCarousel
              products={popularProducts}
              slideClassName="basis-[70%] min-[28rem]:basis-1/2 md:basis-1/3 lg:basis-1/4 xl:basis-1/5 2xl:basis-1/6"
            />
          </div>
        </Container>
      ) : (
        <PageHeader breadcrumbs={breadcrumbs} title={t("nav.shop")} />
      )}

      <Container size="wide" className="py-12 lg:py-16">
        <div className="grid grid-cols-1 gap-x-10 gap-y-10 lg:grid-cols-12">
          <ShopSidebar
            filters={filters}
            categories={categories}
            manufacturers={manufacturers}
            baseUrl={shopUrl}
            className="lg:col-span-3"
          />

          <div className="lg:col-span-9">
            <ShopResults
              products={products}
              filters={filters}
              shopUrl={shopUrl}
              gridClassName="grid-cols-1 min-[28rem]:grid-cols-2 xl:grid-cols-3"
              heading={isLanding ? t("shop.allProducts") : undefined}
            />
          </div>
        </div>
      </Container>
    </>
  );
}
