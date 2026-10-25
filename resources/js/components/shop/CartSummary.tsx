import { Link, router, usePage } from "@inertiajs/react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { useNavUrl } from "@/hooks/use-nav-url";
import { useT } from "@/hooks/use-t";
import { CART_PROPS, formatCents } from "@/lib/shop";
import type { CartData } from "@/types";

/**
 * The cart's total and what to do next. There is no checkout yet, so the
 * next step is to get in touch; when checkout exists this button becomes
 * the way into it.
 *
 * Everything here comes from the `cart` the page was given, including where
 * to empty it (`clearUrl`). The shared header summary is a different prop
 * (`cartSummary`) and is not needed.
 */
export function CartSummary({ cart }: { cart: CartData }) {
  const t = useT();
  const { locale } = usePage().props;
  const contactUrl = useNavUrl("nav.contact", "/contact");
  const [confirming, setConfirming] = useState(false);
  const [clearing, setClearing] = useState(false);
  const [failure, setFailure] = useState<string>();

  function clear() {
    router.delete(cart.clearUrl, {
      preserveScroll: true,
      only: CART_PROPS,
      onStart: () => {
        setClearing(true);
        setFailure(undefined);
      },
      onFinish: () => setClearing(false),
      onError: (errors) =>
        setFailure(
          errors.page_expired ? t("shop.pageExpired") : t("cart.updateFailed"),
        ),
      onHttpException: () => {
        setFailure(t("cart.updateFailed"));

        return false;
      },
      onNetworkError: () => setFailure(t("cart.updateFailed")),
    });
  }

  return (
    <Card className="p-6 lg:sticky lg:top-28">
      <h2 className="display text-ink text-2xl">{t("cart.summary")}</h2>

      <dl className="border-rule mt-6 space-y-3 border-t pt-6">
        <div className="text-ink-soft flex justify-between gap-4">
          <dt>{t("cart.items")}</dt>
          <dd className="tabular-nums">{cart.totalQuantity}</dd>
        </div>
        <div className="text-ink flex items-baseline justify-between gap-4">
          <dt className="font-semibold">{t("cart.total")}</dt>
          <dd className="display text-3xl tabular-nums">
            {formatCents(cart.subtotalCents, locale.current)}
          </dd>
        </div>
      </dl>

      <p className="text-ink-soft mt-6 text-sm text-pretty">
        {t("cart.checkoutSoon")}
      </p>

      <Button asChild variant="accent" size="lg" className="mt-6 w-full">
        <Link href={contactUrl}>{t("cart.contactToOrder")}</Link>
      </Button>

      {/* One click on a link this close to the order button must not empty
          the cart, so it asks first. Cancel is focused: Enter does no harm. */}
      {confirming ? (
        <div
          role="group"
          aria-labelledby="clear-cart-question"
          className="mt-4"
        >
          <p id="clear-cart-question" className="text-ink text-center text-sm">
            {t("cart.clearConfirm")}
          </p>
          <div className="mt-3 flex justify-center gap-3">
            <Button
              type="button"
              variant="outline"
              size="sm"
              autoFocus
              disabled={clearing}
              onClick={() => setConfirming(false)}
            >
              {t("cart.clearCancel")}
            </Button>
            <Button type="button" size="sm" disabled={clearing} onClick={clear}>
              {t("cart.clearYes")}
            </Button>
          </div>
        </div>
      ) : (
        <button
          type="button"
          onClick={() => setConfirming(true)}
          className="meta text-ink-soft hover:text-ink mt-4 min-h-11 w-full text-center underline underline-offset-4 transition-colors"
        >
          {t("cart.clear")}
        </button>
      )}

      {failure && (
        <p role="alert" className="text-destructive mt-3 text-center text-sm">
          {failure}
        </p>
      )}
    </Card>
  );
}
