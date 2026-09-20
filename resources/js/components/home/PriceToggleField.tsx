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
    <div className="has-data-[state=checked]:border-primary has-data-[state=checked]:bg-secondary has-data-[state=checked]:ring-primary hover:border-primary flex items-center justify-between gap-4 rounded-lg border transition-colors has-data-[state=checked]:ring-1">
      <label htmlFor={id} className="flex-1 cursor-pointer p-4 pr-0">
        <span className="block font-semibold">{label}</span>
        <span
          id={priceId}
          className="text-muted-foreground block text-sm tabular-nums"
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
