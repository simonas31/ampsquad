import type { ReactNode } from "react";
import { cn } from "@/lib/utils";

interface SectionHeadingProps {
  title: string;
  description?: string;
  /** Rendered opposite the title on wide screens (e.g. a "view all" link). */
  action?: ReactNode;
  /** Use on navy surfaces. */
  inverse?: boolean;
  /** Heading level; the page's own <h1> is rendered by each page, not here. */
  as?: "h2" | "h3";
}

export function SectionHeading({
  title,
  description,
  action,
  inverse = false,
  as: Heading = "h2",
}: SectionHeadingProps) {
  return (
    <div className="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
      <div className="max-w-2xl">
        <span
          className="bg-accent mb-5 block h-1 w-12 rounded-full"
          aria-hidden="true"
        />
        <Heading className="text-3xl sm:text-4xl">{title}</Heading>
        {description && (
          <p
            className={cn(
              "mt-4 text-lg text-pretty",
              inverse ? "text-navy-muted" : "text-muted-foreground",
            )}
          >
            {description}
          </p>
        )}
      </div>
      {action}
    </div>
  );
}
