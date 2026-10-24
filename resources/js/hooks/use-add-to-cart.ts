import { useForm, usePage } from "@inertiajs/react";
import { useEffect, useState } from "react";

/**
 * Adds one product to the cart. Only the product and quantity are sent; the
 * price is always the server's. `added` flips on after a successful add and
 * back off a few seconds later, for the "Added" confirmation.
 */
export function useAddToCart(productId: number) {
  const { cart } = usePage().props;
  const form = useForm({ product_id: productId, quantity: 1 });
  const [added, setAdded] = useState(false);

  useEffect(() => {
    if (!added) {
      return;
    }

    const timer = window.setTimeout(() => setAdded(false), 3000);

    return () => window.clearTimeout(timer);
  }, [added]);

  function submit() {
    if (!cart) {
      return;
    }

    setAdded(false);

    form.post(cart.itemsUrl, {
      preserveScroll: true,
      onSuccess: () => {
        setAdded(true);
        form.setData("quantity", 1);
      },
    });
  }

  return {
    quantity: form.data.quantity,
    setQuantity: (quantity: number) => form.setData("quantity", quantity),
    submit,
    processing: form.processing,
    added,
    error: form.errors.quantity ?? form.errors.product_id,
  };
}
