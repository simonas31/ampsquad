import type { ComponentProps } from "react";
import { cn } from "@/lib/utils";

const widths = {
  /** Editorial full measure: project imagery and display type. */
  wide: "max-w-[105rem]",
  default: "max-w-7xl",
  /** Reading measure for admin-authored prose. */
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
        "mx-auto w-full px-5 sm:px-8 lg:px-12",
        widths[size],
        className,
      )}
      {...props}
    />
  );
}
