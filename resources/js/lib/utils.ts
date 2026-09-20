import type { ClassValue } from "clsx";
import { clsx } from "clsx";
import { twMerge } from "tailwind-merge";
import type { Locale } from "@/types";

const INTL_LOCALES: Record<Locale, string> = { lt: "lt-LT", en: "en-IE" };

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

/** Builds a `tel:` link from a display phone number ("+370 600 00000"). */
export function telHref(phone: string): string {
  return `tel:${phone.replace(/[^\d+]/g, "")}`;
}

/** True when the URL points at a different origin than the current page. */
export function isExternalUrl(url: string): boolean {
  return new URL(url, window.location.href).origin !== window.location.origin;
}

/** Formats a date-only ISO string ("2026-05-16") for the given locale. */
export function formatDate(date: string, locale: string): string {
  // Date-only strings parse as UTC midnight, so format in UTC too —
  // otherwise timezones west of UTC would show the previous day.
  return new Intl.DateTimeFormat(locale, {
    dateStyle: "long",
    timeZone: "UTC",
  }).format(new Date(date));
}

/** Formats a euro amount for the site locale ("1 967,00 €" in lt, "€1,967.00" in en). */
export function formatPrice(
  amount: number,
  locale: Locale,
  fractionDigits = 0,
): string {
  return new Intl.NumberFormat(INTL_LOCALES[locale], {
    style: "currency",
    currency: "EUR",
    minimumFractionDigits: fractionDigits,
    maximumFractionDigits: fractionDigits,
  }).format(amount);
}
