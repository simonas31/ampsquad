import { usePage } from "@inertiajs/react";
import { useT } from "@/hooks/use-t";
import type { NavigationLink } from "@/types";

export interface MenuLink {
  url: string;
  label: string;
  /** Set for the fixed navigation links, absent for admin-managed pages. */
  labelKey?: NavigationLink["labelKey"];
}

/**
 * The links for one menu: the fixed navigation followed by the pages the
 * admin flagged for that placement (the "show in header" / "show in footer"
 * checkboxes on a page).
 */
export function useMenuLinks(placement: "header" | "footer"): MenuLink[] {
  const t = useT();
  const { navigation, pageLinks } = usePage().props;

  return [
    ...navigation.map((link) => ({
      url: link.url,
      label: t(link.labelKey),
      labelKey: link.labelKey,
    })),
    ...pageLinks[placement].map((page) => ({
      url: page.url,
      label: page.title,
    })),
  ];
}
