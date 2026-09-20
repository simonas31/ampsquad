import { Link } from "@inertiajs/react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";
import type { Paginated } from "@/types";

const itemClasses =
  "flex size-10 items-center justify-center rounded-md text-sm font-semibold transition-colors";

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

  return (
    <nav
      aria-label={t("pagination.label")}
      className="flex flex-wrap items-center justify-center gap-1.5"
    >
      {links.prev ? (
        <Link
          href={links.prev}
          aria-label={t("pagination.previous")}
          className={cn(itemClasses, "hover:bg-secondary")}
        >
          <ChevronLeft className="size-5" aria-hidden="true" />
        </Link>
      ) : (
        <span
          aria-hidden="true"
          className={cn(itemClasses, "text-muted-foreground/50")}
        >
          <ChevronLeft className="size-5" />
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
                ? "bg-primary text-primary-foreground"
                : "hover:bg-secondary",
            )}
          >
            {page.label}
          </Link>
        ) : (
          <span
            key={index}
            className={cn(itemClasses, "text-muted-foreground")}
          >
            {page.label}
          </span>
        ),
      )}

      {links.next ? (
        <Link
          href={links.next}
          aria-label={t("pagination.next")}
          className={cn(itemClasses, "hover:bg-secondary")}
        >
          <ChevronRight className="size-5" aria-hidden="true" />
        </Link>
      ) : (
        <span
          aria-hidden="true"
          className={cn(itemClasses, "text-muted-foreground/50")}
        >
          <ChevronRight className="size-5" />
        </span>
      )}
    </nav>
  );
}
