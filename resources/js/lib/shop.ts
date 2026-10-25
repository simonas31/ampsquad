import { formatPrice } from "@/lib/utils";
import type { Locale, ShopFilters } from "@/types";

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
