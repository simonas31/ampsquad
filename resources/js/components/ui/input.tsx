import type { ComponentProps } from "react";
import { cn } from "@/lib/utils";

const controlClasses =
  "border-input bg-background text-foreground placeholder:text-muted-foreground aria-invalid:border-destructive w-full rounded-md border px-3 text-base transition-colors disabled:cursor-not-allowed disabled:opacity-50";

export function Input({
  className,
  type = "text",
  ...props
}: ComponentProps<"input">) {
  return (
    <input
      type={type}
      className={cn(controlClasses, "h-11", className)}
      {...props}
    />
  );
}

export function Textarea({ className, ...props }: ComponentProps<"textarea">) {
  return (
    <textarea
      className={cn(controlClasses, "min-h-32 py-2.5", className)}
      {...props}
    />
  );
}
