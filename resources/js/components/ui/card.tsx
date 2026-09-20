import type { ComponentProps } from "react";
import { cn } from "@/lib/utils";

export function Card({ className, ...props }: ComponentProps<"div">) {
  return (
    <div
      className={cn(
        "bg-card text-card-foreground shadow-card overflow-hidden rounded-lg border",
        className,
      )}
      {...props}
    />
  );
}
