import type { ReactNode } from "react";
import { cn } from "@/lib/utils";

interface SectionHeadingProps {
  title: string;
  description?: string;
  /** A real navigation link opposite the title on wide screens. Never prose. */
  action?: ReactNode;
  /** Use inside the dark passage. */
  tone?: "light" | "dark";
  /** Heading level; the page's own <h1> is rendered by each page, not here. */
  as?: "h2" | "h3";
}

/**
 * The header of a section: the display title, an optional action opposite
 * it, and an optional description stacked beneath. The title hangs from a
 * full-width rule, the same device a drawing sheet uses to separate a title
 * block from its content. There is no eyebrow slot on purpose: where a
 * section sits on the page already says what it is.
 */
export function SectionHeading({
  title,
  description,
  action,
  tone = "light",
  as: Heading = "h2",
}: SectionHeadingProps) {
  const dark = tone === "dark";

  return (
    <div
      className={cn("border-t pt-8", dark ? "border-bone-soft/30" : "border-ink")}
    >
      <div className="flex flex-col gap-8 sm:flex-row sm:items-end sm:justify-between sm:gap-12">
        <Heading
          className={cn(
            "display max-w-3xl text-4xl sm:text-5xl lg:text-6xl",
            dark ? "text-bone" : "text-ink",
          )}
        >
          {title}
        </Heading>
        {action && <div className="shrink-0">{action}</div>}
      </div>
      {description && (
        <p
          className={cn(
            "mt-6 max-w-[52ch] text-lg text-pretty",
            dark ? "text-bone-soft" : "text-ink-soft",
          )}
        >
          {description}
        </p>
      )}
    </div>
  );
}
