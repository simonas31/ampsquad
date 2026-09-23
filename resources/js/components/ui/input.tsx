import type { ComponentProps } from "react";
import { cn } from "@/lib/utils";

/**
 * Ruled inputs: a single baseline under the value, no box. The control
 * inherits the surface behind it, so a form reads as lines drawn on the
 * page rather than as widgets dropped onto it. Forms are always placed on
 * a plaster or paper surface, so one tone covers every use.
 */
const controlClasses =
  "border-input focus:border-ink aria-invalid:border-destructive text-ink placeholder:text-ink-soft w-full border-0 border-b bg-transparent px-0 text-lg transition-colors disabled:cursor-not-allowed disabled:opacity-50";

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
