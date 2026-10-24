import { formatPrice } from "@/lib/utils";
import type { Locale } from "@/types";

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
