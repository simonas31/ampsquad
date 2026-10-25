import { Link, usePage } from "@inertiajs/react";
import { ArrowLeft, ShoppingBag } from "lucide-react";
import { Reveal } from "@/components/common/Reveal";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { ProductImage } from "@/components/shop/ProductImage";
import { QuantityStepper } from "@/components/shop/QuantityStepper";
import { Button } from "@/components/ui/button";
import { useAddToCart } from "@/hooks/use-add-to-cart";
import { useT } from "@/hooks/use-t";
import { formatCents } from "@/lib/shop";
import { cn } from "@/lib/utils";
import type { Breadcrumb, Product } from "@/types";

interface ShowProps {
  product: Product;
  breadcrumbs: Breadcrumb[];
}

export default function Show({ product, breadcrumbs }: ShowProps) {
  const t = useT();
  const { locale, cartSummary } = usePage().props;
  const addToCart = useAddToCart(product.id);
  const backUrl = breadcrumbs[1]?.url;

  function submit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    addToCart.submit();
  }

  return (
    <>
      <PageHeader
        breadcrumbs={breadcrumbs}
        title={product.name}
        description={product.excerpt ?? undefined}
      />

      <Container size="wide" className="py-14 lg:py-20">
        <div className="grid grid-cols-1 gap-x-10 gap-y-12 lg:grid-cols-12">
          <Reveal className="lg:col-span-7">
            <ProductImage
              imageUrl={product.imageUrl}
              alt={product.name}
              ratio="4 / 3"
              priority
              sizes="(min-width: 1024px) 55vw, 100vw"
            />
          </Reveal>

          <Reveal delay={100} className="lg:col-span-4 lg:col-start-9">
            <p className="display text-ink text-4xl tabular-nums">
              {formatCents(product.priceCents, locale.current)}
            </p>

            <p
              className={cn(
                "meta mt-3",
                product.inStock ? "text-ink-soft" : "text-destructive",
              )}
            >
              {product.inStock ? t("shop.inStock") : t("shop.outOfStock")}
            </p>

            {product.description && (
              <p className="text-ink-soft mt-6 text-lg whitespace-pre-line text-pretty">
                {product.description}
              </p>
            )}

            <dl className="border-rule mt-8 grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 border-t pt-6 text-[0.9375rem] empty:hidden">
              {product.sku && (
                <>
                  <dt className="meta text-ink-soft">{t("shop.sku")}</dt>
                  <dd className="text-ink break-all">{product.sku}</dd>
                </>
              )}
              {product.manufacturer && (
                <>
                  <dt className="meta text-ink-soft">{t("shop.manufacturer")}</dt>
                  <dd className="text-ink">{product.manufacturer}</dd>
                </>
              )}
              {product.category && (
                <>
                  <dt className="meta text-ink-soft">{t("shop.category")}</dt>
                  <dd>
                    <Link
                      href={product.category.url}
                      className="text-ink underline underline-offset-4 hover:no-underline"
                    >
                      {product.category.name}
                    </Link>
                  </dd>
                </>
              )}
            </dl>

            <form
              onSubmit={submit}
              className="border-rule mt-8 space-y-5 border-t pt-8"
            >
              <QuantityStepper
                editable
                value={addToCart.quantity}
                min={1}
                max={Math.max(1, product.maxQuantity)}
                label={t("shop.quantity")}
                decreaseLabel={t("shop.decreaseQuantity")}
                increaseLabel={t("shop.increaseQuantity")}
                disabled={addToCart.processing || !product.inStock}
                onChange={addToCart.setQuantity}
              />

              <Button
                type="submit"
                variant="accent"
                size="lg"
                className="w-full"
                disabled={addToCart.processing || !product.inStock}
              >
                <ShoppingBag aria-hidden="true" />
                {product.inStock
                  ? addToCart.processing
                    ? t("shop.adding")
                    : t("shop.addToCart")
                  : t("shop.outOfStock")}
              </Button>

              <div role="status" className="min-h-6">
                {addToCart.added && cartSummary && (
                  <p className="meta text-ink">
                    {t("shop.added")}.{" "}
                    <Link
                      href={cartSummary.url}
                      className="underline underline-offset-4 hover:no-underline"
                    >
                      {t("shop.viewCart")}
                    </Link>
                  </p>
                )}
                {addToCart.error && (
                  <p className="text-destructive text-sm">{addToCart.error}</p>
                )}
              </div>
            </form>
          </Reveal>
        </div>

        {backUrl && (
          <div className="border-rule mt-14 border-t pt-6">
            <Link
              href={backUrl}
              className="meta text-ink group inline-flex items-center gap-2 hover:underline"
            >
              <ArrowLeft
                className="size-4 transition-transform duration-200 group-hover:-translate-x-1"
                aria-hidden="true"
              />
              {t("shop.backToShop")}
            </Link>
          </div>
        )}
      </Container>
    </>
  );
}
