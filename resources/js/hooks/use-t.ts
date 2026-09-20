import { usePage } from "@inertiajs/react";
import { useCallback } from "react";
import en from "@/i18n/en.json";
import lt from "@/i18n/lt.json";
import type { Locale } from "@/types";

type Messages = typeof en;

type Paths<T> = {
  [K in keyof T & string]: T[K] extends string ? K : `${K}.${Paths<T[K]>}`;
}[keyof T & string];

export type TranslationKey = Paths<Messages>;

export type Translate = (
  key: TranslationKey,
  params?: Record<string, string | number>,
) => string;

// Typing lt as Messages makes the compiler flag any key that exists in
// en.json but not lt.json (and vice versa), so the two can't drift apart.
const messages: Record<Locale, Messages> = { en, lt };

function lookup(tree: unknown, key: string): string | undefined {
  let node = tree;

  for (const segment of key.split(".")) {
    if (typeof node !== "object" || node === null) {
      return undefined;
    }
    node = Reflect.get(node, segment);
  }

  return typeof node === "string" ? node : undefined;
}

/**
 * Translates client-side UI strings for the locale the server resolved for
 * this request (shared `locale.current` prop), falling back to English and
 * then to the key itself. Supports `{name}` interpolation.
 */
export function useT(): Translate {
  const { locale } = usePage().props;
  const current = locale.current;

  return useCallback(
    (key, params) => {
      const template =
        lookup(messages[current], key) ?? lookup(messages.lt, key) ?? key;

      return template.replace(/\{(\w+)\}/g, (placeholder, name: string) =>
        String(params?.[name] ?? placeholder),
      );
    },
    [current],
  );
}
