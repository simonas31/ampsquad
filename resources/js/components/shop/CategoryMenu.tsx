import { Link } from "@inertiajs/react";
import { ChevronDown, ChevronRight, Menu, Minus, Plus } from "lucide-react";
import { useRef, useState } from "react";
import { useT } from "@/hooks/use-t";
import { categoryIcon } from "@/lib/category-icons";
import { shopUrl } from "@/lib/shop";
import { cn } from "@/lib/utils";
import type { CategoryNode } from "@/types";

interface CategoryMenuProps {
  /** Every category with its sub-categories opened out. */
  nodes: CategoryNode[];
  /** The shop listing's own URL, without a query string. */
  baseUrl: string;
}

/**
 * How many categories the menu lists before the rest go behind its
 * "All categories" button, and the height of one entry (`min-h-11`), which
 * together fix the height the expanded list scrolls within.
 */
const MENU_LIMIT = 12;
const ENTRY_HEIGHT = "2.75rem";

const titleClasses =
  "bg-ink text-bone flex h-14 w-full items-center gap-3 rounded-t-xl px-5 text-left text-sm font-semibold tracking-wide uppercase";

/**
 * The shop's categories as a navy-headed list, each with an icon and, from
 * `lg` up, a panel that opens beside it on hover or keyboard focus with the
 * sub-categories and the ones below those. Every entry is an ordinary link
 * to its category, so the menu works without the panels: on a phone the
 * list folds away behind its title and the panels are left out, and a
 * category's own page lists what is under it.
 *
 * Past MENU_LIMIT categories the rest are held back behind a button at the
 * foot of the list. It opens them in the same box, which then scrolls, so
 * the menu never grows taller than the banner beside it. The panels still
 * show over a scrolling list: they are positioned against the menu, not
 * the list, so the list's overflow does not cut them off.
 */
export function CategoryMenu({ nodes, baseUrl }: CategoryMenuProps) {
  const t = useT();
  const [open, setOpen] = useState(false);
  const [showAll, setShowAll] = useState(false);
  const listRef = useRef<HTMLUListElement>(null);
  const hasOverflow = nodes.length > MENU_LIMIT;

  function toggleAll() {
    if (showAll) {
      listRef.current?.scrollTo({ top: 0 });
    }

    setShowAll((isShown) => !isShown);
  }

  return (
    <nav aria-label={t("shop.menu.title")} className="relative">
      <div className={cn(titleClasses, "hidden lg:flex")}>
        <Menu className="size-5" aria-hidden="true" />
        {t("shop.menu.title")}
      </div>
      <button
        type="button"
        aria-expanded={open}
        aria-controls="shop-menu-list"
        onClick={() => setOpen((isOpen) => !isOpen)}
        className={cn(titleClasses, "lg:hidden", !open && "rounded-b-xl")}
      >
        <Menu className="size-5" aria-hidden="true" />
        {t("shop.menu.title")}
        <ChevronDown
          className={cn(
            "ml-auto size-5 transition-transform",
            open && "rotate-180",
          )}
          aria-hidden="true"
        />
      </button>

      <div
        id="shop-menu-list"
        className={cn(
          "border-rule rounded-b-xl border border-t-0 bg-white py-1",
          open ? "block" : "hidden",
          "lg:block",
        )}
      >
        <ul
          id="shop-menu-categories"
          ref={listRef}
          className={cn(showAll && "overscroll-contain overflow-y-auto")}
          style={
            showAll
              ? { maxHeight: `calc(${MENU_LIMIT} * ${ENTRY_HEIGHT})` }
              : undefined
          }
        >
          {(showAll ? nodes : nodes.slice(0, MENU_LIMIT)).map((node) => (
            <MenuItem key={node.id} node={node} baseUrl={baseUrl} />
          ))}
        </ul>

        {hasOverflow && (
          <button
            type="button"
            aria-expanded={showAll}
            aria-controls="shop-menu-categories"
            onClick={toggleAll}
            className="text-ink hover:bg-plaster flex min-h-11 w-full items-center gap-3 px-4 text-left text-[0.9375rem] font-medium"
          >
            {showAll ? (
              <Minus
                className="text-ink-soft size-5 shrink-0"
                aria-hidden="true"
              />
            ) : (
              <Plus
                className="text-ink-soft size-5 shrink-0"
                aria-hidden="true"
              />
            )}
            {showAll
              ? t("shop.menu.fewerCategories")
              : t("shop.menu.allCategories")}
          </button>
        )}
      </div>
    </nav>
  );
}

function MenuItem({ node, baseUrl }: { node: CategoryNode; baseUrl: string }) {
  const t = useT();
  const Icon = categoryIcon(node.name);
  const hasChildren = node.children.length > 0;

  return (
    <li className="group/item">
      <Link
        href={shopUrl(baseUrl, { category: node.slug })}
        className="text-ink hover:bg-plaster group-focus-within/item:bg-plaster flex min-h-11 items-center gap-3 px-4 text-[0.9375rem] font-medium"
      >
        <Icon className="text-ink-soft size-5 shrink-0" aria-hidden="true" />
        <span className="min-w-0 flex-1 truncate">{node.name}</span>
        {hasChildren && (
          <ChevronRight
            className="text-ink-soft hidden size-4 shrink-0 lg:block"
            aria-hidden="true"
          />
        )}
      </Link>

      {hasChildren && (
        <div className="border-rule shadow-ink/15 absolute top-0 left-full z-30 hidden h-full w-[min(48rem,calc(100vw-24rem))] overflow-y-auto rounded-r-xl border bg-white p-6 shadow-xl lg:group-focus-within/item:block lg:group-hover/item:block">
          <Link
            href={shopUrl(baseUrl, { category: node.slug })}
            className="display text-ink block border-b pb-3 text-xl hover:underline"
          >
            {t("shop.menu.seeAll", { name: node.name })}
          </Link>

          <ul className="mt-5 grid gap-x-8 gap-y-6 xl:grid-cols-2 2xl:grid-cols-3">
            {node.children.map((child) => (
              <li key={child.id}>
                <Link
                  href={shopUrl(baseUrl, { category: child.slug })}
                  className="text-ink font-semibold hover:underline"
                >
                  {child.name}
                  <span className="text-ink-soft ml-2 text-sm font-normal tabular-nums">
                    {child.count}
                  </span>
                </Link>

                {child.children.length > 0 && (
                  <ul className="mt-2 space-y-1">
                    {child.children.map((grandchild) => (
                      <li key={grandchild.id}>
                        <Link
                          href={shopUrl(baseUrl, { category: grandchild.slug })}
                          className="text-ink-soft hover:text-ink text-sm hover:underline"
                        >
                          {grandchild.name}
                        </Link>
                      </li>
                    ))}
                  </ul>
                )}
              </li>
            ))}
          </ul>
        </div>
      )}
    </li>
  );
}
