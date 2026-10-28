import { usePage } from "@inertiajs/react";
import { useT } from "@/hooks/use-t";
import { formatCents } from "@/lib/shop";
import { cn } from "@/lib/utils";
import type { ShippingMethodOption } from "@/types";

interface ShippingOptionsProps {
  /** The id of the group, so an error summary can send focus to it. */
  id: string;
  methods: ShippingMethodOption[];
  value: number | null;
  onChange: (id: number) => void;
  error?: string;
}

/**
 * The ways to get the order, as a group of radio buttons. Each says what it
 * costs with VAT, or that it is free, and what the shop says about it (the
 * delivery time, or where to collect). The whole card is the target, and the
 * chosen one is marked by its border as well as by the radio, so it does not
 * rely on colour alone.
 */
export function ShippingOptions({
  id,
  methods,
  value,
  onChange,
  error,
}: ShippingOptionsProps) {
  const t = useT();
  const { locale } = usePage().props;
  const errorId = `${id}-error`;

  return (
    <fieldset
      id={id}
      tabIndex={-1}
      aria-describedby={error ? errorId : undefined}
      aria-invalid={error ? true : undefined}
      className="space-y-3 outline-none"
    >
      <legend className="display text-ink text-2xl">
        {t("checkout.deliverySection")}
      </legend>
      <p className="text-ink-soft text-sm">{t("checkout.deliveryHint")}</p>

      {methods.map((method) => {
        const checked = method.id === value;
        const inputId = `${id}-${method.id}`;

        return (
          <label
            key={method.id}
            htmlFor={inputId}
            className={cn(
              "flex cursor-pointer gap-4 rounded-2xl border bg-white p-4 transition-colors has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-ink/20",
              checked ? "border-ink ring-ink/10 ring-4" : "border-input hover:border-ink",
            )}
          >
            <input
              id={inputId}
              type="radio"
              name={id}
              value={method.id}
              checked={checked}
              onChange={() => onChange(method.id)}
              className="accent-ink mt-1 size-5 shrink-0"
            />
            <span className="min-w-0 flex-1">
              <span className="flex flex-wrap items-baseline justify-between gap-x-4">
                <span className="text-ink font-semibold">{method.name}</span>
                <span className="text-ink tabular-nums">
                  {method.priceInclVatCents === 0
                    ? t("checkout.free")
                    : formatCents(method.priceInclVatCents, locale.current)}
                </span>
              </span>
              {method.description && (
                <span className="text-ink-soft mt-1 block text-sm">
                  {method.description}
                </span>
              )}
            </span>
          </label>
        );
      })}

      {error && (
        <p id={errorId} className="text-destructive text-sm">
          {error}
        </p>
      )}
    </fieldset>
  );
}
