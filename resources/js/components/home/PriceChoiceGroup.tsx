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
 * Single-choice option cards. Radix supplies the radio semantics and arrow
 * key navigation; the selected card is marked by border, fill and a check
 * icon, so the state never relies on color alone.
 */
export function PriceChoiceGroup<T extends string>({
  label,
  value,
  options,
  onValueChange,
}: PriceChoiceGroupProps<T>) {
  const labelId = useId();

  return (
    <div className="space-y-3">
      <p id={labelId} className="text-sm font-semibold">
        {label}
      </p>
      <RadioGroup.Root
        aria-labelledby={labelId}
        value={value}
        onValueChange={(next) => onValueChange(next as T)}
        className="grid gap-3 sm:grid-cols-3"
      >
        {options.map((option) => (
          <RadioGroup.Item
            key={option.value}
            value={option.value}
            className="group data-[state=checked]:border-primary data-[state=checked]:bg-secondary data-[state=checked]:ring-primary hover:border-primary flex items-center justify-between gap-3 rounded-lg border p-4 text-left transition-colors data-[state=checked]:ring-1"
          >
            <span>
              <span className="block font-semibold">{option.label}</span>
              <span className="text-muted-foreground block text-sm tabular-nums">
                {option.price}
              </span>
            </span>
            <span
              className="border-input group-data-[state=checked]:border-primary group-data-[state=checked]:bg-primary flex size-5 shrink-0 items-center justify-center rounded-full border transition-colors"
              aria-hidden="true"
            >
              <Check className="text-primary-foreground size-3 opacity-0 transition-opacity group-data-[state=checked]:opacity-100" />
            </span>
          </RadioGroup.Item>
        ))}
      </RadioGroup.Root>
    </div>
  );
}
