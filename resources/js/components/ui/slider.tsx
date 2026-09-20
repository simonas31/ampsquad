import { Slider as SliderPrimitive } from "radix-ui";
import type { ComponentProps } from "react";
import { cn } from "@/lib/utils";

interface SliderProps extends ComponentProps<typeof SliderPrimitive.Root> {
  /** Accessible name for the thumb, the element that receives focus. */
  thumbLabel: string;
}

export function Slider({ className, thumbLabel, ...props }: SliderProps) {
  return (
    <SliderPrimitive.Root
      className={cn(
        "relative flex h-11 w-full touch-none items-center select-none data-disabled:opacity-50",
        className,
      )}
      {...props}
    >
      <SliderPrimitive.Track className="bg-secondary relative h-2 grow overflow-hidden rounded-full">
        <SliderPrimitive.Range className="bg-primary absolute h-full" />
      </SliderPrimitive.Track>
      <SliderPrimitive.Thumb
        aria-label={thumbLabel}
        className="border-primary bg-background shadow-card block size-6 rounded-full border-2 transition-shadow hover:shadow-md"
      />
    </SliderPrimitive.Root>
  );
}
