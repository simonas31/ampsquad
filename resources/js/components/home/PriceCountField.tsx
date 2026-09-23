import { Minus, Plus } from "lucide-react";
import { useId } from "react";
import { Button } from "@/components/ui/button";
import { Slider } from "@/components/ui/slider";
import { TranslationKey, useT } from "@/hooks/use-t";
import { clampCount, type CountLimits } from "@/lib/price-calculator";

interface PriceCountFieldProps extends CountLimits {
  label: string;
  value: number;
  /** Shows how the count turns into money, e.g. "3 × €297 = €891". */
  priceHint: string;
  onValueChange: (value: number) => void;
}

/** A bounded whole-number input: slider for quick changes, buttons for exact ones. */
export function PriceCountField({
  label,
  value,
  min,
  max,
  priceHint,
  onValueChange,
}: PriceCountFieldProps) {
  const t = useT();
  const labelId = useId();
  const limits = { min, max };

  return (
    <div className="space-y-3">
      <div className="flex items-baseline justify-between gap-4">
        <p id={labelId} className="meta text-ink-soft">
          {label}
        </p>
        <p className="display text-ink text-3xl tabular-nums">{value}</p>
      </div>
      <div className="flex items-center gap-3">
        <Button
          type="button"
          variant="outline"
          size="icon"
          aria-label={t("home.priceCalculator.decrease" as TranslationKey, {
            label,
          })}
          disabled={value <= min}
          onClick={() => onValueChange(clampCount(value - 1, limits))}
        >
          <Minus aria-hidden="true" />
        </Button>
        <Slider
          thumbLabel={label}
          min={min}
          max={max}
          step={1}
          value={[value]}
          onValueChange={([next]) => onValueChange(clampCount(next, limits))}
        />
        <Button
          type="button"
          variant="outline"
          size="icon"
          aria-label={t("home.priceCalculator.increase" as TranslationKey, {
            label,
          })}
          disabled={value >= max}
          onClick={() => onValueChange(clampCount(value + 1, limits))}
        >
          <Plus aria-hidden="true" />
        </Button>
      </div>
      <p className="text-ink-soft font-mono text-xs tabular-nums">
        {priceHint}
      </p>
    </div>
  );
}
