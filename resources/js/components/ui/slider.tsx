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
      <SliderPrimitive.Track className="bg-concrete relative h-1.5 grow overflow-hidden">
        <SliderPrimitive.Range className="bg-ink absolute h-full" />
      </SliderPrimitive.Track>
      <SliderPrimitive.Thumb
        aria-label={thumbLabel}
        /* The thumb keeps its round form: it models a physical travel along
           the track, which is the documented exception to square corners. */
        className="border-ink bg-paper block size-6 rounded-full border-2 transition-colors hover:bg-signal"
      />
    </SliderPrimitive.Root>
  );
}
