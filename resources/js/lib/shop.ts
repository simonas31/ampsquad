import { formatPrice } from "@/lib/utils";
import type {
  CartData,
  CategoryNode,
  Locale,
  OrderStatus,
  ShopFilters,
} from "@/types";

/**
 * The props a cart change can alter: the cart page's own `cart` and the
 * header's `cartSummary`. A cart visit asks for just these, so the page it
 * returns to does not re-run its own queries (a listing's products, facets
 * and category tree) every time something is added or removed.
 */
export const CART_PROPS = ["cart", "cartSummary"];

/**
 * Keeps a quantity a whole number inside the range the server accepts.
 * Anything that is not a number falls back to the minimum. The server
 * validates again, so this only stops the controls offering values it
 * would refuse.
 */
export function clampQuantity(value: number, min: number, max: number): number {
  if (!Number.isFinite(value)) {
    return min;
  }

  return Math.min(max, Math.max(min, Math.trunc(value)));
}

/** Formats an amount held in whole cents ("19,99 €" in lt, "€19.99" in en). */
export function formatCents(cents: number, locale: Locale): string {
  return formatPrice(cents / 100, locale, 2);
}

/**
 * The shop listing's URL for some filters, written the way the server reads
 * them. Empty and default values are left out so the plain shop keeps its
 * plain URL, and there is never a page parameter: changing a filter starts
 * again from the first page.
 */
export function shopUrl(base: string, filters: Partial<ShopFilters>): string {
  const params = new URLSearchParams();
  const search = filters.search?.trim();
  const minPrice = filters.minPrice?.trim();
  const maxPrice = filters.maxPrice?.trim();

  if (search) {
    params.set("q", search);
  }

  if (filters.category) {
    params.set("category", filters.category);
  }

  if (filters.manufacturer) {
    params.set("manufacturer", String(filters.manufacturer));
  }

  if (filters.inStock) {
    params.set("in_stock", "1");
  }

  if (minPrice) {
    params.set("min_price", minPrice);
  }

  if (maxPrice) {
    params.set("max_price", maxPrice);
  }

  if (filters.sort && filters.sort !== "availability") {
    params.set("sort", filters.sort);
  }

  const query = params.toString();

  return query ? `${base}?${query}` : base;
}

/** Whether the listing differs from the plain shop in any way, sort included. */
export function hasActiveFilters(filters: ShopFilters): boolean {
  return shopUrl("", filters) !== "";
}

/**
 * The chosen category and each of its ancestors, outermost first, found in
 * the tree the server opened out. Empty for no category, or for one the
 * tree does not hold.
 */
export function categoryPath(
  nodes: CategoryNode[],
  slug: string | null,
): CategoryNode[] {
  if (slug === null) {
    return [];
  }

  for (const node of nodes) {
    if (node.slug === slug) {
      return [node];
    }

    const below = categoryPath(node.children, slug);

    if (below.length > 0) {
      return [node, ...below];
    }
  }

  return [];
}

/**
 * Whether the cart can go to checkout as it is: something to order, and no
 * line that is cut short or gone, since checkout refuses those rather than
 * charge for something else. The server decides again; this only stops the
 * button offering a way in that is closed.
 */
export function canCheckout(cart: CartData): boolean {
  return (
    cart.items.length > 0 &&
    cart.totalQuantity > 0 &&
    !cart.hasReducedItems &&
    !cart.hasUnavailableItems
  );
}

/** How often an order that waits for its payment is asked about, and how many times. */
export const ORDER_POLL_INTERVAL_MS = 5000;
export const ORDER_POLL_LIMIT = 60;

/**
 * Where an order stands as the customer needs to hear it. Coming back from
 * the bank proves nothing: only the server saying paid does.
 */
export type OrderStage = "paid" | "waiting" | "closed" | "refunded";

export function orderStage(status: OrderStatus): OrderStage {
  switch (status) {
    case "paid":
    case "shipped":
      return "paid";
    case "pending_payment":
      return "waiting";
    case "refunded":
      return "refunded";
    case "cancelled":
    case "expired":
      return "closed";
  }
}

/**
 * Whether to ask the server about the order again: only while it waits for
 * its payment, and not for ever. After the limit the page stops and says
 * the confirmation will come by email.
 */
export function shouldPollOrder(
  status: OrderStatus,
  attempts: number,
): boolean {
  return status === "pending_payment" && attempts < ORDER_POLL_LIMIT;
}

/** The time of day of an ISO 8601 moment ("14:35"), in the visitor's time zone unless one is given. */
export function formatClock(
  iso: string,
  locale: Locale,
  timeZone?: string,
): string {
  return new Intl.DateTimeFormat(locale === "lt" ? "lt-LT" : "en-IE", {
    hour: "2-digit",
    minute: "2-digit",
    hourCycle: "h23",
    timeZone,
  }).format(new Date(iso));
}

/** The date of an ISO 8601 moment ("2026 m. spalio 30 d." / "30 Oct 2026"), in the visitor's time zone unless one is given. */
export function formatDate(
  iso: string,
  locale: Locale,
  timeZone?: string,
): string {
  return new Intl.DateTimeFormat(locale === "lt" ? "lt-LT" : "en-IE", {
    dateStyle: "medium",
    timeZone,
  }).format(new Date(iso));
}

/** The date and the time of day of an ISO 8601 moment. */
export function formatDateTime(
  iso: string,
  locale: Locale,
  timeZone?: string,
): string {
  return `${formatDate(iso, locale, timeZone)}, ${formatClock(iso, locale, timeZone)}`;
}
