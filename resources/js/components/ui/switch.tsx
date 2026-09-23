import { Switch as SwitchPrimitive } from "radix-ui";
import type { ComponentProps } from "react";
import { cn } from "@/lib/utils";

export function Switch({
  className,
  ...props
}: ComponentProps<typeof SwitchPrimitive.Root>) {
  return (
    <SwitchPrimitive.Root
      className={cn(
        // Track and knob keep their round form for the same reason the slider
        // thumb does: the control models a physical throw.
        "bg-input data-[state=checked]:bg-ink inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors disabled:cursor-not-allowed disabled:opacity-50",
        className,
      )}
      {...props}
    >
      <SwitchPrimitive.Thumb className="bg-paper block size-5 translate-x-0.5 rounded-full transition-transform data-[state=checked]:translate-x-5.5" />
    </SwitchPrimitive.Root>
  );
}
