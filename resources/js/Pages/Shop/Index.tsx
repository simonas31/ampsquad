import { Reveal } from "@/components/common/Reveal";
import { Pagination } from "@/components/content/Pagination";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { ProductCard } from "@/components/shop/ProductCard";
import { useT } from "@/hooks/use-t";
import type { Breadcrumb, Paginated, Product } from "@/types";

interface IndexProps {
  products: Paginated<Product>;
  breadcrumbs: Breadcrumb[];
}

export default function Index({ products, breadcrumbs }: IndexProps) {
  const t = useT();

  return (
    <>
      <PageHeader breadcrumbs={breadcrumbs} title={t("nav.shop")} />

      <Container size="wide" className="py-14 lg:py-20">
        {products.data.length > 0 ? (
          <>
            <Reveal>
              <ul className="grid grid-cols-1 gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">
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
          <div className="border-ink border-t py-20">
            <p className="display text-ink-soft max-w-[18ch] text-3xl sm:text-4xl">
              {t("shop.noProducts")}
            </p>
          </div>
        )}
      </Container>
    </>
  );
}
