import { useId } from "react";
import { Switch } from "@/components/ui/switch";

interface PriceToggleFieldProps {
  label: string;
  price: string;
  checked: boolean;
  onCheckedChange: (checked: boolean) => void;
}

export function PriceToggleField({
  label,
  price,
  checked,
  onCheckedChange,
}: PriceToggleFieldProps) {
  const id = useId();
  const priceId = `${id}-price`;

  return (
    <div className="border-rule has-data-[state=checked]:border-ink has-data-[state=checked]:bg-plaster hover:border-ink flex items-center justify-between gap-4 border transition-colors">
      <label htmlFor={id} className="min-w-0 flex-1 cursor-pointer p-4 pr-0">
        <span className="text-ink block font-medium">{label}</span>
        <span
          id={priceId}
          className="text-ink-soft mt-1 block font-mono text-xs tabular-nums"
        >
          {price}
        </span>
      </label>
      <div className="p-4 pl-0">
        <Switch
          id={id}
          checked={checked}
          onCheckedChange={onCheckedChange}
          aria-describedby={priceId}
        />
      </div>
    </div>
  );
}
