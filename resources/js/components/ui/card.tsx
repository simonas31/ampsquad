import type { ComponentProps } from "react";
import { cn } from "@/lib/utils";

/**
 * A soft white panel: rounded corners, a hairline rule and a low, diffuse
 * shadow. Used only where a boundary carries real meaning (the calculator's
 * input surface), never to group content that spacing could group.
 */
export function Card({ className, ...props }: ComponentProps<"div">) {
  return (
    <div
      className={cn("border-rule shadow-ink/5 rounded-3xl border bg-white text-ink shadow-lg", className)}
      {...props}
    />
  );
}
