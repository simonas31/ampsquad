import { usePage } from "@inertiajs/react";
import type { AriaAttributes } from "react";
import type { NavigationLink } from "@/types";

/**
 * Nav links come back as full absolute URLs (`http://localhost/projects`)
 * while Inertia's page.url is a relative path — compare pathnames so the
 * current nav item can be highlighted regardless of locale prefix.
 *
 * The home link only matches exactly; every other link also matches its
 * child pages (e.g. "Projects" stays highlighted on a project detail page),
 * reported as `aria-current="true"` rather than `"page"`.
 */
export function useActiveLink(): (
  link: NavigationLink,
) => AriaAttributes["aria-current"] {
  const { url } = usePage();
  const currentPath = url.split("?")[0].replace(/\/$/, "");

  return (link) => {
    const linkPath = new URL(link.url).pathname.replace(/\/$/, "");

    if (linkPath === currentPath) {
      return "page";
    }

    if (
      link.labelKey !== "nav.home" &&
      currentPath.startsWith(`${linkPath}/`)
    ) {
      return "true";
    }

    return undefined;
  };
}
