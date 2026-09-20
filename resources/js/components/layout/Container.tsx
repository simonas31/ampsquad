import type { ComponentProps } from "react";
import { cn } from "@/lib/utils";

const widths = {
  default: "max-w-7xl",
  narrow: "max-w-3xl",
} as const;

interface ContainerProps extends ComponentProps<"div"> {
  size?: keyof typeof widths;
}

export function Container({
  size = "default",
  className,
  ...props
}: ContainerProps) {
  return (
    <div
      className={cn(
        "mx-auto w-full px-4 sm:px-6 lg:px-8",
        widths[size],
        className,
      )}
      {...props}
    />
  );
}
