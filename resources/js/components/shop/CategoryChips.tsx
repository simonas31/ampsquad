import { Link } from "@inertiajs/react";
import { ChevronRight } from "lucide-react";
import { Fragment } from "react";
import { useT } from "@/hooks/use-t";
import { categoryPath, shopUrl } from "@/lib/shop";
import { cn } from "@/lib/utils";
import type { CategoryNode, ShopFilters } from "@/types";

interface CategoryChipsProps {
  /** The tree the server opened out for the chosen category. */
  nodes: CategoryNode[];
  filters: ShopFilters;
  /** The shop listing's own URL, without a query string. */
  baseUrl: string;
}

const chipClasses =
  "border-rule hover:border-ink inline-flex min-h-9 items-center gap-2 rounded-full border bg-white px-4 text-sm transition-colors";

/**
 * Where the visitor is in the categories and where they can go next: the
 * trail down to the chosen category, then the categories directly under it
 * (the top level when none is chosen), as pills with their counts. Every
 * link keeps the other filters.
 */
export function CategoryChips({ nodes, filters, baseUrl }: CategoryChipsProps) {
  const t = useT();
  const path = categoryPath(nodes, filters.category);
  const next = path.at(-1)?.children ?? nodes;

  return (
    <nav aria-label={t("shop.filters.categories")} className="space-y-3">
      {path.length > 0 && (
        <ol className="meta text-ink-soft flex flex-wrap items-center gap-x-2 gap-y-1">
          <li>
            <Link
              href={shopUrl(baseUrl, { ...filters, category: null })}
              className="hover:text-ink underline-offset-4 hover:underline"
            >
              {t("shop.filters.allCategories")}
            </Link>
          </li>
          {path.map((node, index) => (
            <Fragment key={node.id}>
              <li aria-hidden="true">
                <ChevronRight className="size-4" />
              </li>
              <li>
                {index === path.length - 1 ? (
                  <span aria-current="page" className="text-ink">
                    {node.name}
                  </span>
                ) : (
                  <Link
                    href={shopUrl(baseUrl, { ...filters, category: node.slug })}
                    className="hover:text-ink underline-offset-4 hover:underline"
                  >
                    {node.name}
                  </Link>
                )}
              </li>
            </Fragment>
          ))}
        </ol>
      )}

      {next.length > 0 && (
        <ul className="flex flex-wrap gap-2">
          {next.map((node) => (
            <li key={node.id}>
              <Link
                href={shopUrl(baseUrl, { ...filters, category: node.slug })}
                className={cn(chipClasses, "text-ink")}
              >
                {node.name}
                <span className="text-ink-soft tabular-nums">{node.count}</span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </nav>
  );
}
