import { cva, type VariantProps } from "class-variance-authority";
import { Slot } from "radix-ui";
import type { ComponentProps } from "react";
import { cn } from "@/lib/utils";

/**
 * Pill-shaped and set in the body face at sentence case, so buttons read as
 * friendly rather than as signage. `:active` drops the label a pixel so the
 * press is felt.
 */
export const buttonVariants = cva(
  "inline-flex shrink-0 items-center justify-center gap-2 rounded-full text-sm font-semibold whitespace-nowrap transition-colors select-none active:translate-y-px disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=size-])]:size-4",
  {
    variants: {
      variant: {
        /** Navy fill. The workhorse on light surfaces. */
        default: "bg-ink text-bone hover:bg-ink-raised",
        /** Orange fill. Reserved for the single strongest action in view. */
        accent: "bg-signal text-ink hover:bg-signal-hover",
        /** Outlined pill on light surfaces. */
        outline:
          "border border-ink bg-transparent text-ink hover:bg-ink hover:text-bone",
        /** Outlined pill on navy or on photography. */
        inverse:
          "border border-bone/60 bg-transparent text-bone hover:border-bone hover:bg-bone hover:text-ink",
        secondary: "bg-concrete text-ink hover:bg-rule",
        ghost: "text-ink hover:bg-concrete",
        link: "rounded-none text-ink underline decoration-signal decoration-2 underline-offset-[6px] hover:decoration-ink",
      },
      size: {
        default: "h-11 px-5",
        sm: "h-9 px-4 text-[0.8125rem]",
        lg: "h-14 px-8 text-base",
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
