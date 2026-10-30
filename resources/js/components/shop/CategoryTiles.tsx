import { Link } from "@inertiajs/react";
import { useT } from "@/hooks/use-t";
import { categoryIcon } from "@/lib/category-icons";
import { shopUrl } from "@/lib/shop";
import type { CategoryNode } from "@/types";

interface CategoryTilesProps {
  /** Every category with its sub-categories opened out. */
  nodes: CategoryNode[];
  /** The shop listing's own URL, without a query string. */
  baseUrl: string;
}

/** Sub-categories named on a tile; the category's own page lists the rest. */
const CHIPS_PER_TILE = 3;

/**
 * The top-level categories as a grid of tiles: an icon, the name, how many
 * products, and the first few sub-categories as links of their own. The
 * whole tile leads to the category through its name (a stretched link), and
 * the sub-category links sit above that, so none is nested in another.
 */
export function CategoryTiles({ nodes, baseUrl }: CategoryTilesProps) {
  const t = useT();

  return (
    <section aria-labelledby="shop-tiles-heading">
      <h2
        id="shop-tiles-heading"
        className="display text-ink border-ink border-t pt-5 text-2xl uppercase sm:text-3xl"
      >
        {t("shop.menu.browse")}
      </h2>

      <ul className="mt-6 grid grid-cols-1 gap-3 min-[30rem]:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 sm:gap-4">
        {nodes.map((node) => {
          const Icon = categoryIcon(node.name);

          return (
            <li
              key={node.id}
              className="group border-rule hover:border-ink focus-within:border-ink relative flex flex-col rounded-2xl border bg-white p-5 transition-colors"
            >
              <span className="bg-plaster group-hover:bg-signal group-focus-within:bg-signal text-ink flex size-12 items-center justify-center rounded-full transition-colors">
                <Icon className="size-6" aria-hidden="true" />
              </span>

              <h3 className="mt-4 text-lg leading-snug font-semibold">
                <Link
                  href={shopUrl(baseUrl, { category: node.slug })}
                  className="text-ink after:absolute after:inset-0 after:rounded-2xl"
                >
                  {node.name}
                </Link>
              </h3>
              <p className="meta text-ink-soft mt-1 tabular-nums">
                {t("shop.productCount", { count: node.count })}
              </p>

              {node.children.length > 0 && (
                <ul className="mt-4 flex flex-wrap gap-x-3 gap-y-1">
                  {node.children.slice(0, CHIPS_PER_TILE).map((child) => (
                    <li key={child.id}>
                      <Link
                        href={shopUrl(baseUrl, { category: child.slug })}
                        className="text-ink-soft hover:text-ink relative z-10 text-sm underline-offset-4 hover:underline"
                      >
                        {child.name}
                      </Link>
                    </li>
                  ))}
                </ul>
              )}
            </li>
          );
        })}
      </ul>
    </section>
  );
}
