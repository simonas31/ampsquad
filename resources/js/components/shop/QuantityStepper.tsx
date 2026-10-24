import { Minus, Plus } from "lucide-react";
import { useState } from "react";
import { clampQuantity } from "@/lib/shop";
import { cn } from "@/lib/utils";

interface QuantityStepperProps {
  value: number;
  min: number;
  max: number;
  onChange: (value: number) => void;
  /** Names the group for assistive tech, e.g. "Quantity of Charger check". */
  label: string;
  decreaseLabel: string;
  increaseLabel: string;
  /** Lets the visitor type a number; otherwise it is read-only text between the buttons. */
  editable?: boolean;
  disabled?: boolean;
  className?: string;
}

const stepClasses =
  "text-ink hover:bg-concrete flex size-11 shrink-0 items-center justify-center rounded-full transition-colors disabled:pointer-events-none disabled:opacity-40";

/**
 * A pill with minus and plus either side of the quantity. The buttons stop
 * at the limits the server enforces, so the visitor is never offered a
 * value it would refuse.
 */
export function QuantityStepper({
  value,
  min,
  max,
  onChange,
  label,
  decreaseLabel,
  increaseLabel,
  editable = false,
  disabled = false,
  className,
}: QuantityStepperProps) {
  // While typing, what is shown may differ from the committed value (an
  // empty field, say). null means "show the committed value".
  const [draft, setDraft] = useState<string | null>(null);

  function handleType(raw: string) {
    const digits = raw.replace(/\D/g, "");
    const next =
      digits === "" ? "" : String(clampQuantity(Number(digits), min, max));

    setDraft(next);

    if (next !== "") {
      onChange(Number(next));
    }
  }

  return (
    <div
      role="group"
      aria-label={label}
      className={cn(
        "border-input inline-flex items-center rounded-full border bg-white",
        className,
      )}
    >
      <button
        type="button"
        className={stepClasses}
        aria-label={decreaseLabel}
        disabled={disabled || value <= min}
        onClick={() => onChange(clampQuantity(value - 1, min, max))}
      >
        <Minus className="size-4" aria-hidden="true" />
      </button>

      {editable ? (
        <input
          type="text"
          inputMode="numeric"
          autoComplete="off"
          aria-label={label}
          value={draft ?? String(value)}
          disabled={disabled}
          onChange={(event) => handleType(event.target.value)}
          onBlur={() => setDraft(null)}
          className="text-ink w-12 bg-transparent text-center text-base tabular-nums"
        />
      ) : (
        <output
          aria-live="polite"
          className="text-ink min-w-10 text-center tabular-nums"
        >
          {value}
        </output>
      )}

      <button
        type="button"
        className={stepClasses}
        aria-label={increaseLabel}
        disabled={disabled || value >= max}
        onClick={() => onChange(clampQuantity(value + 1, min, max))}
      >
        <Plus className="size-4" aria-hidden="true" />
      </button>
    </div>
  );
}
