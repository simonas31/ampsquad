import { Link, usePage } from "@inertiajs/react";
import { ShoppingBag } from "lucide-react";
import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";

/**
 * The header's way into the cart, with the number of units in it. It only
 * exists while the shop is switched on: the server shares no cart then.
 */
export function CartLink({ className }: { className?: string }) {
  const t = useT();
  const { cartSummary } = usePage().props;

  if (!cartSummary) {
    return null;
  }

  return (
    <Link
      href={cartSummary.url}
      aria-label={t("cart.linkLabel", { count: cartSummary.count })}
      className={cn(
        "text-ink hover:bg-concrete relative inline-flex size-11 shrink-0 items-center justify-center rounded-full transition-colors",
        className,
      )}
    >
      <ShoppingBag className="size-5" aria-hidden="true" />
      {cartSummary.count > 0 && (
        <span
          aria-hidden="true"
          className="bg-signal text-ink absolute top-0.5 right-0 flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-xs font-semibold tabular-nums"
        >
          {cartSummary.count > 99 ? "99+" : cartSummary.count}
        </span>
      )}
    </Link>
  );
}
