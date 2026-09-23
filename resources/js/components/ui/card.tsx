import type { ComponentProps } from "react";
import { cn } from "@/lib/utils";

/**
 * A bordered panel, not an elevated card: hairline rule, square corners, no
 * shadow. Used only where a boundary carries real meaning (the calculator's
 * input surface), never to group content that spacing could group.
 */
export function Card({ className, ...props }: ComponentProps<"div">) {
  return (
    <div
      className={cn("border-rule bg-paper text-ink border", className)}
      {...props}
    />
  );
}
