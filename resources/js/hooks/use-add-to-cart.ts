import { useForm, usePage } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { useT } from "@/hooks/use-t";
import { CART_PROPS } from "@/lib/shop";

/**
 * Adds one product to the cart. Only the product and quantity are sent; the
 * price is always the server's. `added` flips on after a successful add and
 * back off a few seconds later, for the "Added" confirmation.
 *
 * The visit asks the server for the cart props only, so adding from a
 * listing does not re-run the listing's queries. `error` is the server's
 * reason when it refuses (not enough stock, product gone), or a generic
 * message when the request itself failed (offline, throttled, server error).
 */
export function useAddToCart(productId: number) {
  const t = useT();
  const { cartSummary } = usePage().props;
  const form = useForm({ product_id: productId, quantity: 1 });
  const [added, setAdded] = useState(false);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    if (!added) {
      return;
    }

    const timer = window.setTimeout(() => setAdded(false), 3000);

    return () => window.clearTimeout(timer);
  }, [added]);

  function submit() {
    if (!cartSummary) {
      return;
    }

    setAdded(false);
    setFailed(false);

    form.post(cartSummary.itemsUrl, {
      preserveScroll: true,
      only: CART_PROPS,
      onSuccess: () => {
        setAdded(true);
        form.setData("quantity", 1);
      },
      // Stay on the page and say so, instead of Inertia swapping it for an
      // error page over a product the visitor was only trying to add.
      onHttpException: () => {
        setFailed(true);

        return false;
      },
      onNetworkError: () => setFailed(true),
    });
  }

  // The server also sends errors that belong to no field (an expired
  // session), which the form's typed errors do not list.
  const errors: Record<string, string | undefined> = form.errors;

  return {
    quantity: form.data.quantity,
    setQuantity: (quantity: number) => form.setData("quantity", quantity),
    submit,
    processing: form.processing,
    added,
    error:
      errors.quantity ??
      errors.product_id ??
      (errors.page_expired ? t("shop.pageExpired") : undefined) ??
      (failed ? t("shop.addFailed") : undefined),
  };
}
