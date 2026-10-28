import { Link, usePage } from "@inertiajs/react";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { CartLineItem } from "@/components/shop/CartLineItem";
import { CartSummary } from "@/components/shop/CartSummary";
import { Button } from "@/components/ui/button";
import { useNavUrl } from "@/hooks/use-nav-url";
import { useT } from "@/hooks/use-t";
import type { Breadcrumb, CartData } from "@/types";

interface CartProps {
  cart: CartData;
  breadcrumbs: Breadcrumb[];
}

export default function Cart({ cart, breadcrumbs }: CartProps) {
  const t = useT();
  const shopUrl = useNavUrl("nav.shop", "/shop");
  // Why checkout sent the visitor back here (a price changed, the last units
  // went to someone else), said in the server's own words.
  const { errors } = usePage().props;

  return (
    <>
      <PageHeader breadcrumbs={breadcrumbs} title={t("nav.cart")} />

      <Container size="wide" className="py-14 lg:py-20">
        {errors.checkout && (
          <p
            role="alert"
            className="border-destructive text-ink mb-8 border-l-2 py-1 pl-4 text-sm"
          >
            {errors.checkout}
          </p>
        )}

        {cart.items.length > 0 ? (
          <div className="grid grid-cols-1 gap-x-10 gap-y-12 lg:grid-cols-12">
            <div className="lg:col-span-8">
              <div role="status">
                {cart.hasReducedItems && (
                  <p className="border-signal text-ink mb-8 border-l-2 py-1 pl-4 text-sm">
                    {t("cart.reducedNotice")}
                  </p>
                )}
                {cart.hasUnavailableItems && (
                  <p className="border-signal text-ink mb-8 border-l-2 py-1 pl-4 text-sm">
                    {t("cart.unavailableNotice")}
                  </p>
                )}
              </div>

              <ul>
                {cart.items.map((line) => (
                  <CartLineItem key={line.id} line={line} />
                ))}
              </ul>
            </div>

            <div className="lg:col-span-4">
              <CartSummary cart={cart} />
            </div>
          </div>
        ) : (
          <div className="border-ink border-t py-20">
            <p className="display text-ink-soft max-w-[18ch] text-3xl sm:text-4xl">
              {t("cart.empty")}
            </p>
            <Button asChild className="mt-10">
              <Link href={shopUrl}>{t("cart.continueShopping")}</Link>
            </Button>
          </div>
        )}
      </Container>
    </>
  );
}
