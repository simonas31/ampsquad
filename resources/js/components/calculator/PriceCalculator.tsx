import { usePage } from "@inertiajs/react";
import { useState } from "react";
import { PriceChoiceGroup } from "@/components/calculator/PriceChoiceGroup";
import { PriceCountField } from "@/components/calculator/PriceCountField";
import {
  PriceSummary,
  type PriceSummaryLine,
} from "@/components/calculator/PriceSummary";
import { PriceToggleField } from "@/components/calculator/PriceToggleField";
import { Card } from "@/components/ui/card";
import { TranslationKey, useT } from "@/hooks/use-t";
import {
  BATHROOM_LIMITS,
  BATHROOM_PRICE,
  DEFAULT_SELECTION,
  PANEL_PRICES,
  PANEL_TYPES,
  PROPERTY_TYPES,
  PROPERTY_TYPE_PRICES,
  ROOM_LIMITS,
  ROOM_PRICE,
  calculatePriceLines,
  calculateTotal,
  facadeLightingPrice,
  selectPropertyType,
  type PriceLine,
  type PriceSelection,
} from "@/lib/price-calculator";
import { formatPrice } from "@/lib/utils";

interface PriceCalculatorProps {
  contactUrl: string;
}

/**
 * The options on one soft white card with the running total beside it. The
 * panel prices follow the property type, and facade lighting only exists for
 * cottages and houses, so the extras block disappears for an apartment
 * rather than offering something that cannot be priced.
 */
export function PriceCalculator({ contactUrl }: PriceCalculatorProps) {
  const t = useT();
  const { locale } = usePage().props;
  const [selection, setSelection] = useState<PriceSelection>(DEFAULT_SELECTION);

  const money = (amount: number) => formatPrice(amount, locale.current);
  const facadePrice = facadeLightingPrice(selection.propertyType);

  const update = <K extends keyof PriceSelection>(
    key: K,
    value: PriceSelection[K],
  ) => setSelection((current) => ({ ...current, [key]: value }));

  const lineLabel = (line: PriceLine): string => {
    switch (line.key) {
      case "propertyType":
        return t(
          `priceCalculator.propertyType.${selection.propertyType}` as TranslationKey,
        );
      case "rooms":
        return t("priceCalculator.rooms.line" as TranslationKey, {
          count: selection.rooms,
        });
      case "bathrooms":
        return t("priceCalculator.bathrooms.line" as TranslationKey, {
          count: selection.bathrooms,
        });
      case "facadeLighting":
        return t("priceCalculator.facadeLighting.label" as TranslationKey);
      case "panel":
        return t("priceCalculator.panel.line" as TranslationKey, {
          name: t(`priceCalculator.panel.${selection.panel}` as TranslationKey),
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
    <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
      <Card className="divide-rule divide-y p-6 sm:p-8 lg:p-10">
        <div className="pb-8">
          <PriceChoiceGroup
            label={t("priceCalculator.propertyType.label" as TranslationKey)}
            value={selection.propertyType}
            onValueChange={(value) =>
              setSelection((current) => selectPropertyType(current, value))
            }
            options={PROPERTY_TYPES.map((type) => ({
              value: type,
              label: t(`priceCalculator.propertyType.${type}` as TranslationKey),
              price: money(PROPERTY_TYPE_PRICES[type]),
            }))}
          />
        </div>

        <div className="grid gap-8 py-8 md:grid-cols-2">
          <PriceCountField
            label={t("priceCalculator.rooms.label" as TranslationKey)}
            value={selection.rooms}
            priceHint={countHint(selection.rooms, ROOM_PRICE)}
            onValueChange={(value) => update("rooms", value)}
            {...ROOM_LIMITS}
          />
          <PriceCountField
            label={t("priceCalculator.bathrooms.label" as TranslationKey)}
            value={selection.bathrooms}
            priceHint={countHint(selection.bathrooms, BATHROOM_PRICE)}
            onValueChange={(value) => update("bathrooms", value)}
            {...BATHROOM_LIMITS}
          />
        </div>

        <div className={facadePrice === null ? "pt-8" : "py-8"}>
          <PriceChoiceGroup
            label={t("priceCalculator.panel.label" as TranslationKey)}
            value={selection.panel}
            onValueChange={(value) => update("panel", value)}
            options={PANEL_TYPES.map((type) => ({
              value: type,
              label: t(`priceCalculator.panel.${type}` as TranslationKey),
              price: money(PANEL_PRICES[selection.propertyType][type]),
            }))}
          />
        </div>

        {facadePrice !== null && (
          <div className="space-y-4 pt-8">
            <p className="meta text-ink-soft">
              {t("priceCalculator.extras" as TranslationKey)}
            </p>
            <PriceToggleField
              label={t("priceCalculator.facadeLighting.label" as TranslationKey)}
              price={`+ ${money(facadePrice)}`}
              checked={selection.facadeLighting}
              onCheckedChange={(checked) => update("facadeLighting", checked)}
            />
          </div>
        )}
      </Card>

      <PriceSummary
        total={formatPrice(calculateTotal(selection), locale.current, 2)}
        lines={summaryLines}
        contactUrl={contactUrl}
      />
    </div>
  );
}
