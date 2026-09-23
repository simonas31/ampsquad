import { Link } from "@inertiajs/react";
import { Card } from "@/components/ui/card";
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
 * The running total, set at display size because the figure is the whole
 * point of the section. On small screens it sticks to the bottom of the
 * viewport as a compact bar (total plus the quote request) so the price
 * stays visible while options change; from lg up it becomes the full
 * breakdown in a sticky column.
 */
export function PriceSummary({ total, lines, contactUrl }: PriceSummaryProps) {
  const t = useT();

  return (
    <Card
      aria-label={t("home.priceCalculator.summary.title" as TranslationKey)}
      role="region"
      className="border-ink sticky bottom-3 z-10 border-2 p-4 lg:top-28 lg:bottom-auto lg:p-6"
    >
      <div className="flex flex-wrap items-center justify-between gap-4 lg:flex-col lg:items-stretch lg:gap-6">
        <div className="min-w-0">
          <p className="meta text-ink-soft">
            {t("home.priceCalculator.summary.title" as TranslationKey)}
          </p>
          <p
            aria-live="polite"
            aria-atomic="true"
            className="display text-ink mt-2 text-3xl tabular-nums sm:text-4xl lg:text-4xl"
          >
            {total}
          </p>
          <p className="meta text-ink-soft mt-2">
            {t("home.priceCalculator.summary.vat" as TranslationKey)}
          </p>
        </div>

        <Button asChild variant="accent" size="lg" className="grow lg:w-full">
          <Link href={contactUrl}>{t("common.requestQuote")}</Link>
        </Button>
      </div>

      <dl className="border-rule mt-6 hidden space-y-3 border-t pt-6 lg:block">
        {lines.map((line) => (
          <div key={line.key} className="flex justify-between gap-4 text-sm">
            <dt className="text-ink-soft">{line.label}</dt>
            <dd className="text-ink font-mono tabular-nums">{line.amount}</dd>
          </div>
        ))}
      </dl>
    </Card>
  );
}
