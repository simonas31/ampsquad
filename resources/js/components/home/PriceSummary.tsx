import { Link } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { TranslationKey, useT } from "@/hooks/use-t";

export interface PriceSummaryLine {
  key: string;
  label: string;
  amount: string;
}

interface PriceSummaryProps {
  total: string;
  lines: PriceSummaryLine[];
  contactUrl: string;
}

/**
 * The running total. On small screens it sticks to the bottom of the
 * viewport as a compact bar (total + CTA) so the price stays visible while
 * options change; from lg up it becomes a full receipt in a sticky sidebar.
 */
export function PriceSummary({ total, lines, contactUrl }: PriceSummaryProps) {
  const t = useT();

  return (
    <aside
      aria-label={t("home.priceCalculator.summary.title" as TranslationKey)}
      className="surface-navy bg-navy text-navy-foreground shadow-card-hover sticky bottom-3 z-10 rounded-xl p-4 lg:top-28 lg:bottom-auto lg:p-6"
    >
      <div className="flex items-center justify-between gap-4 lg:flex-col lg:items-stretch lg:gap-6">
        <div>
          <p className="text-navy-muted text-sm">
            {t("home.priceCalculator.summary.title" as TranslationKey)}
          </p>
          <p
            aria-live="polite"
            aria-atomic="true"
            className="text-2xl font-semibold tabular-nums sm:text-3xl lg:text-4xl"
          >
            {total}
          </p>
          <p className="text-navy-muted text-sm">
            {t("home.priceCalculator.summary.vat" as TranslationKey)}
          </p>
        </div>

        <Button asChild variant="accent" size="lg" className="lg:w-full">
          <Link href={contactUrl}>{t("home.calculator.cta")}</Link>
        </Button>
      </div>

      <dl className="mt-6 hidden space-y-3 border-t border-white/15 pt-6 text-sm lg:block">
        {lines.map((line) => (
          <div key={line.key} className="flex justify-between gap-4">
            <dt className="text-navy-muted">{line.label}</dt>
            <dd className="font-medium tabular-nums">{line.amount}</dd>
          </div>
        ))}
      </dl>
    </aside>
  );
}
