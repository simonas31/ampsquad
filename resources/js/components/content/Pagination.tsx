import { Link } from "@inertiajs/react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";
import type { Paginated } from "@/types";

const itemClasses =
  "meta flex size-11 items-center justify-center border transition-colors";

export function Pagination({
  pagination,
}: {
  pagination: Pick<Paginated<unknown>, "links" | "meta">;
}) {
  const t = useT();
  const { links, meta } = pagination;

  if (meta.last_page <= 1) {
    return null;
  }

  // meta.links wraps the numbered pages with Laravel's own "previous" and
  // "next" entries (whose labels are HTML) — those come from `links`
  // instead, rendered as icon buttons, so only the pages in between are used.
  const pages = meta.links.slice(1, -1);

  const stepClasses = (enabled: boolean) =>
    cn(
      itemClasses,
      enabled
        ? "border-rule text-ink hover:border-ink"
        : "border-rule text-ink-soft/40",
    );

  return (
    <nav
      aria-label={t("pagination.label")}
      className="flex flex-wrap items-center gap-2"
    >
      {links.prev ? (
        <Link
          href={links.prev}
          aria-label={t("pagination.previous")}
          className={stepClasses(true)}
        >
          <ChevronLeft className="size-4" aria-hidden="true" />
        </Link>
      ) : (
        <span aria-hidden="true" className={stepClasses(false)}>
          <ChevronLeft className="size-4" />
        </span>
      )}

      {pages.map((page, index) =>
        page.url ? (
          <Link
            key={index}
            href={page.url}
            aria-label={t("pagination.goToPage", { number: page.label })}
            aria-current={page.active ? "page" : undefined}
            className={cn(
              itemClasses,
              page.active
                ? "border-ink bg-ink text-bone"
                : "border-rule text-ink hover:border-ink",
            )}
          >
            {page.label}
          </Link>
        ) : (
          <span
            key={index}
            className={cn(itemClasses, "border-transparent text-ink-soft")}
          >
            {page.label}
          </span>
        ),
      )}

      {links.next ? (
        <Link
          href={links.next}
          aria-label={t("pagination.next")}
          className={stepClasses(true)}
        >
          <ChevronRight className="size-4" aria-hidden="true" />
        </Link>
      ) : (
        <span aria-hidden="true" className={stepClasses(false)}>
          <ChevronRight className="size-4" />
        </span>
      )}
    </nav>
  );
}
