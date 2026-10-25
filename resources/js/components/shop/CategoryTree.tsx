import { Link } from "@inertiajs/react";
import { useT } from "@/hooks/use-t";
import { shopUrl } from "@/lib/shop";
import { cn } from "@/lib/utils";
import type { CategoryNode, ShopFilters } from "@/types";

interface CategoryTreeProps {
  nodes: CategoryNode[];
  filters: ShopFilters;
  /** The shop listing's own URL, without a query string. */
  baseUrl: string;
}

const linkClasses =
  "flex min-h-9 items-baseline justify-between gap-3 rounded-lg px-2 py-1.5 text-[0.9375rem] transition-colors hover:bg-concrete";

/**
 * The category filter as a nested list of links. Each link keeps the other
 * filters and drops the page, so choosing a category narrows what is already
 * searched. Only the chosen branch is open; the server sends nothing deeper.
 */
export function CategoryTree({ nodes, filters, baseUrl }: CategoryTreeProps) {
  const t = useT();
  const isAll = filters.category === null;

  return (
    <nav aria-label={t("shop.filters.categories")}>
      <h2 className="meta text-ink-soft mb-3">{t("shop.filters.categories")}</h2>

      <ul className="space-y-0.5">
        <li>
          <Link
            href={shopUrl(baseUrl, { ...filters, category: null })}
            aria-current={isAll ? "page" : undefined}
            className={cn(linkClasses, isAll ? "text-ink font-semibold" : "text-ink-soft")}
          >
            {t("shop.filters.allCategories")}
          </Link>
        </li>

        <Branch nodes={nodes} filters={filters} baseUrl={baseUrl} />
      </ul>
    </nav>
  );
}

function Branch({ nodes, filters, baseUrl }: CategoryTreeProps) {
  return nodes.map((node) => (
    <li key={node.id}>
      <Link
        href={shopUrl(baseUrl, { ...filters, category: node.slug })}
        aria-current={node.isSelected ? "page" : undefined}
        className={cn(
          linkClasses,
          node.isSelected ? "bg-concrete text-ink font-semibold" : "text-ink",
        )}
      >
        <span className="min-w-0 text-pretty">{node.name}</span>
        <span className="meta text-ink-soft tabular-nums">{node.count}</span>
      </Link>

      {node.children.length > 0 && (
        <ul className="border-rule mt-0.5 ml-3 space-y-0.5 border-l pl-2">
          <Branch nodes={node.children} filters={filters} baseUrl={baseUrl} />
        </ul>
      )}
    </li>
  ));
}
