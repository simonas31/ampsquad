import { usePage } from "@inertiajs/react";
import type { NavigationLink } from "@/types";

/**
 * Looks up a localized URL from the shared navigation links, so components
 * link to pages by their stable label key instead of hardcoding paths.
 */
export function useNavUrl(
  labelKey: NavigationLink["labelKey"],
  fallback: string,
): string {
  const { navigation } = usePage().props;

  return navigation.find((link) => link.labelKey === labelKey)?.url ?? fallback;
}
