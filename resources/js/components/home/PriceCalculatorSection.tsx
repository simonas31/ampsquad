import { usePage } from "@inertiajs/react";
import { useState } from "react";
import { Reveal } from "@/components/common/Reveal";
import { SectionHeading } from "@/components/common/SectionHeading";
import { PriceChoiceGroup } from "@/components/home/PriceChoiceGroup";
import { PriceCountField } from "@/components/home/PriceCountField";
import {
  PriceSummary,
  type PriceSummaryLine,
} from "@/components/home/PriceSummary";
import { PriceToggleField } from "@/components/home/PriceToggleField";
import { Container } from "@/components/layout/Container";
import { Card } from "@/components/ui/card";
import { TranslationKey, useT } from "@/hooks/use-t";
import {
  BATHROOM_LIMITS,
  BATHROOM_PRICE,
  DEFAULT_SELECTION,
  FACADE_LIGHTING_PRICE,
  PANEL_PRICES,
  PANEL_TYPES,
  PROPERTY_TYPES,
  PROPERTY_TYPE_PRICES,
  ROOM_LIMITS,
  ROOM_PRICE,
  calculatePriceLines,
  calculateTotal,
  type PriceLine,
  type PriceSelection,
} from "@/lib/price-calculator";
import { formatPrice } from "@/lib/utils";

interface PriceCalculatorSectionProps {
  contactUrl: string;
}

export function PriceCalculatorSection({
  contactUrl,
}: PriceCalculatorSectionProps) {
  const t = useT();
  const { locale } = usePage().props;
  const [selection, setSelection] = useState<PriceSelection>(DEFAULT_SELECTION);

  const money = (amount: number) => formatPrice(amount, locale.current);

  const update = <K extends keyof PriceSelection>(
    key: K,
    value: PriceSelection[K],
  ) => setSelection((current) => ({ ...current, [key]: value }));

  const lineLabel = (line: PriceLine): string => {
    switch (line.key) {
      case "propertyType":
        return t(
          `home.priceCalculator.propertyType.${selection.propertyType}` as TranslationKey,
        );
      case "rooms":
        return t("home.priceCalculator.rooms.line" as TranslationKey, {
          count: selection.rooms,
        });
      case "bathrooms":
        return t("home.priceCalculator.bathrooms.line" as TranslationKey, {
          count: selection.bathrooms,
        });
      case "facadeLighting":
        return t("home.priceCalculator.facadeLighting.label" as TranslationKey);
      case "panel":
        return t("home.priceCalculator.panel.line" as TranslationKey, {
          name: t(
            `home.priceCalculator.panel.${selection.panel}` as TranslationKey,
          ),
        });
    }
  };

  const summaryLines: PriceSummaryLine[] = calculatePriceLines(selection).map(
    (line) => ({
      key: line.key,
      label: lineLabel(line),
      amount: money(line.amount),
    }),
  );

  const countHint = (count: number, unitPrice: number) =>
    `${count} × ${money(unitPrice)} = ${money(count * unitPrice)}`;

  return (
    <section className="pb-16 sm:pb-24">
      <Container>
        <Reveal>
          <SectionHeading
            title={t("home.priceCalculator.title" as TranslationKey)}
            description={t("home.priceCalculator.subtitle" as TranslationKey)}
          />
        </Reveal>

        <div className="mt-10 grid items-start gap-6 lg:grid-cols-[1fr_22rem]">
          <Card className="space-y-8 p-5 sm:p-8">
            <PriceChoiceGroup
              label={t(
                "home.priceCalculator.propertyType.label" as TranslationKey,
              )}
              value={selection.propertyType}
              onValueChange={(value) => update("propertyType", value)}
              options={PROPERTY_TYPES.map((type) => ({
                value: type,
                label: t(
                  `home.priceCalculator.propertyType.${type}` as TranslationKey,
                ),
                price: money(PROPERTY_TYPE_PRICES[type]),
              }))}
            />

            <div className="grid gap-8 md:grid-cols-2">
              <PriceCountField
                label={t("home.priceCalculator.rooms.label" as TranslationKey)}
                value={selection.rooms}
                priceHint={countHint(selection.rooms, ROOM_PRICE)}
                onValueChange={(value) => update("rooms", value)}
                {...ROOM_LIMITS}
              />
              <PriceCountField
                label={t(
                  "home.priceCalculator.bathrooms.label" as TranslationKey,
                )}
                value={selection.bathrooms}
                priceHint={countHint(selection.bathrooms, BATHROOM_PRICE)}
                onValueChange={(value) => update("bathrooms", value)}
                {...BATHROOM_LIMITS}
              />
            </div>

            <PriceChoiceGroup
              label={t("home.priceCalculator.panel.label" as TranslationKey)}
              value={selection.panel}
              onValueChange={(value) => update("panel", value)}
              options={PANEL_TYPES.map((type) => ({
                value: type,
                label: t(
                  `home.priceCalculator.panel.${type}` as TranslationKey,
                ),
                price: money(PANEL_PRICES[type]),
              }))}
            />

            <div className="space-y-3">
              <p className="text-sm font-semibold">
                {t("home.priceCalculator.extras" as TranslationKey)}
              </p>
              <PriceToggleField
                label={t(
                  "home.priceCalculator.facadeLighting.label" as TranslationKey,
                )}
                price={`+ ${money(FACADE_LIGHTING_PRICE)}`}
                checked={selection.facadeLighting}
                onCheckedChange={(checked) => update("facadeLighting", checked)}
              />
            </div>
          </Card>

          <PriceSummary
            total={formatPrice(calculateTotal(selection), locale.current, 2)}
            lines={summaryLines}
            contactUrl={contactUrl}
          />
        </div>
      </Container>
    </section>
  );
}
