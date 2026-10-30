import { Link } from "@inertiajs/react";
import { ArrowUpRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useNavUrl } from "@/hooks/use-nav-url";
import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";

/**
 * The shop's banner: a navy panel with the pitch and two ways in (ask for a
 * quote, or work out a price). It stands where a trade catalog puts its
 * promotional banner. The copy lives in the translation files; there is
 * nothing to manage in the admin.
 */
export function BrandPanel({ className }: { className?: string }) {
  const t = useT();
  const quoteUrl = useNavUrl("nav.contact", "/contact");
  const calculatorUrl = useNavUrl("nav.priceCalculator", "/price-calculator");

  return (
    <section
      className={cn(
        "on-ink bg-ink text-bone relative isolate flex min-h-80 flex-col justify-end overflow-hidden rounded-xl p-8 lg:p-12",
        className,
      )}
    >
      <div
        aria-hidden="true"
        className="bg-ink-raised absolute -top-32 -right-24 -z-10 size-[28rem] rounded-full"
      />
      <div
        aria-hidden="true"
        className="border-signal absolute -top-16 -right-8 -z-10 size-72 rounded-full border-[2.5rem]"
      />

      <p className="meta text-bone-soft">{t("shop.banner.eyebrow")}</p>
      <h2 className="display mt-3 max-w-[18ch] text-4xl sm:text-5xl">
        {t("shop.banner.title")}
      </h2>
      <p className="text-bone-soft mt-4 max-w-[48ch] text-pretty">
        {t("shop.banner.text")}
      </p>

      <div className="mt-8 flex flex-wrap gap-3">
        <Button asChild variant="accent">
          <Link href={quoteUrl}>
            {t("shop.banner.quote")}
            <ArrowUpRight aria-hidden="true" />
          </Link>
        </Button>
        <Button asChild variant="inverse">
          <Link href={calculatorUrl}>{t("shop.banner.calculator")}</Link>
        </Button>
      </div>
    </section>
  );
}
