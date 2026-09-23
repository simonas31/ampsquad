import { cva, type VariantProps } from "class-variance-authority";
import { Slot } from "radix-ui";
import type { ComponentProps } from "react";
import { cn } from "@/lib/utils";

/**
 * Square, mono-set, uppercase. Buttons read as site signage rather than as
 * app chrome, which is why there is no radius and no shadow anywhere in
 * the scale. `:active` drops the label a pixel so the press is felt.
 */
export const buttonVariants = cva(
  "inline-flex shrink-0 items-center justify-center gap-2.5 font-mono text-xs font-medium tracking-[0.12em] whitespace-nowrap uppercase transition-colors select-none active:translate-y-px disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=size-])]:size-4",
  {
    variants: {
      variant: {
        /** Graphite fill. The workhorse on plaster surfaces. */
        default: "bg-ink text-bone hover:bg-ink-raised",
        /** Signal fill. Reserved for the single strongest action in view. */
        accent: "bg-signal text-ink hover:bg-signal-hover",
        /** Hairline box on plaster. */
        outline: "border border-ink bg-transparent text-ink hover:bg-ink hover:text-bone",
        /** Hairline box inside the dark passage. */
        inverse:
          "border border-bone-soft/50 bg-transparent text-bone hover:border-bone hover:bg-bone hover:text-ink",
        secondary: "bg-concrete text-ink hover:bg-rule",
        ghost: "text-ink hover:bg-concrete",
        link: "text-ink underline decoration-signal decoration-2 underline-offset-[6px] hover:decoration-ink",
      },
      size: {
        default: "h-11 px-5",
        sm: "h-9 px-3.5 text-[0.6875rem]",
        lg: "h-14 px-8 text-sm",
        icon: "size-11",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
);

interface ButtonProps
  extends ComponentProps<"button">, VariantProps<typeof buttonVariants> {
  /** Render the child element (e.g. an Inertia <Link>) with the button's styles. */
  asChild?: boolean;
}

export function Button({
  className,
  variant,
  size,
  asChild = false,
  ...props
}: ButtonProps) {
  const Component = asChild ? Slot.Root : "button";

  return (
    <Component
      className={cn(buttonVariants({ variant, size }), className)}
      {...props}
    />
  );
}
