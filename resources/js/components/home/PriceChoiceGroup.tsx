import { Check } from "lucide-react";
import { RadioGroup } from "radix-ui";
import { useId } from "react";

interface PriceChoiceOption<T extends string> {
  value: T;
  label: string;
  price: string;
}

interface PriceChoiceGroupProps<T extends string> {
  label: string;
  value: T;
  options: PriceChoiceOption<T>[];
  onValueChange: (value: T) => void;
}

/**
 * Single-choice options as ruled boxes. Radix supplies the radio semantics
 * and arrow key navigation; the chosen option is marked by a heavier border,
 * a filled marker and a check, so the state never rests on colour alone.
 */
export function PriceChoiceGroup<T extends string>({
  label,
  value,
  options,
  onValueChange,
}: PriceChoiceGroupProps<T>) {
  const labelId = useId();

  return (
    <div className="space-y-4">
      <p id={labelId} className="meta text-ink-soft">
        {label}
      </p>
      <RadioGroup.Root
        aria-labelledby={labelId}
        value={value}
        onValueChange={(next) => onValueChange(next as T)}
        className="grid gap-px sm:grid-cols-3"
      >
        {options.map((option) => (
          <RadioGroup.Item
            key={option.value}
            value={option.value}
            className="group border-rule data-[state=checked]:border-ink data-[state=checked]:bg-plaster hover:border-ink flex items-center justify-between gap-3 border p-4 text-left transition-colors"
          >
            <span>
              <span className="text-ink block font-medium">{option.label}</span>
              <span className="text-ink-soft mt-1 block font-mono text-xs tabular-nums">
                {option.price}
              </span>
            </span>
            <span
              className="border-input group-data-[state=checked]:border-ink group-data-[state=checked]:bg-signal flex size-5 shrink-0 items-center justify-center border transition-colors"
              aria-hidden="true"
            >
              <Check className="text-ink size-3 opacity-0 transition-opacity group-data-[state=checked]:opacity-100" />
            </span>
          </RadioGroup.Item>
        ))}
      </RadioGroup.Root>
    </div>
  );
}
