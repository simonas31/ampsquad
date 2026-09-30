import type { ComponentProps } from "react";
import { cn } from "@/lib/utils";

/**
 * Soft boxed inputs: a white field with a rounded, visible border. White
 * keeps the field legible on every surface the form is placed on (paper,
 * plaster), and the focus state thickens the border with a faint halo.
 */
const controlClasses =
  "border-input focus:border-ink aria-invalid:border-destructive text-ink placeholder:text-ink-soft focus:ring-ink/10 w-full rounded-xl border bg-white px-4 text-base transition-colors outline-none focus:ring-4 disabled:cursor-not-allowed disabled:opacity-50";

export function Input({
  className,
  type = "text",
  ...props
}: ComponentProps<"input">) {
  return (
    <input
      type={type}
      className={cn(controlClasses, "h-12", className)}
      {...props}
    />
  );
}

export function Textarea({ className, ...props }: ComponentProps<"textarea">) {
  return (
    <textarea
      className={cn(controlClasses, "min-h-32 py-3", className)}
      {...props}
    />
  );
}
