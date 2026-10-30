import { useState } from "react";
import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";

export type ShopDesign = "a" | "b" | "c";

const STORAGE_KEY = "shop-design";

const designs: ShopDesign[] = ["a", "b", "c"];

const isDesign = (value: unknown): value is ShopDesign =>
  designs.some((design) => design === value);

/** From `?design=` on the first visit, else what was chosen last, else A. */
function readDesign(): ShopDesign {
  try {
    const fromUrl = new URLSearchParams(window.location.search).get("design");
    const wanted = fromUrl ?? window.localStorage.getItem(STORAGE_KEY);

    return isDesign(wanted) ? wanted : "a";
  } catch {
    return "a";
  }
}

export function useShopDesign(): [ShopDesign, (design: ShopDesign) => void] {
  const [design, setDesign] = useState<ShopDesign>(readDesign);

  function choose(next: ShopDesign) {
    setDesign(next);

    try {
      window.localStorage.setItem(STORAGE_KEY, next);
    } catch {
      // Private window or blocked storage: the choice just is not remembered.
    }
  }

  return [design, choose];
}

/**
 * TEMPORARY. A pill pinned to the bottom of the shop page for comparing the
 * layout designs on real data. Once one is chosen this, the other layouts and
 * their shared state are deleted.
 */
export function DesignSwitcher({
  design,
  onChange,
}: {
  design: ShopDesign;
  onChange: (design: ShopDesign) => void;
}) {
  const t = useT();

  return (
    <div
      role="group"
      aria-label={t("shop.design.group")}
      className="bg-ink text-bone shadow-ink/30 fixed bottom-4 left-1/2 z-50 flex -translate-x-1/2 items-center gap-1 rounded-full p-1.5 shadow-xl"
    >
      <span className="text-bone-soft pr-1 pl-3 text-xs">
        {t("shop.design.label")}
      </span>
      {designs.map((id) => (
        <button
          key={id}
          type="button"
          aria-pressed={design === id}
          onClick={() => onChange(id)}
          className={cn(
            "min-h-9 rounded-full px-4 text-sm font-semibold transition-colors",
            design === id ? "bg-signal text-ink" : "hover:bg-ink-raised",
          )}
        >
          {id.toUpperCase()} · {t(`shop.design.${id}`)}
        </button>
      ))}
    </div>
  );
}
