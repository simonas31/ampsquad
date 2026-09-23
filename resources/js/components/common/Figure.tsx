import type { ReactNode } from "react";
import type { Frame } from "@/lib/project-image";
import { cn, riseDelay } from "@/lib/utils";

interface FigureProps {
  frame: Frame | null;
  /** Describes the subject. Empty string only when the figure is decorative. */
  alt: string;
  /**
   * CSS aspect-ratio, e.g. "16 / 10", reserving the box so nothing shifts.
   * Omit it only when the caller sets a responsive `aspect-*` class instead.
   */
  ratio?: string;
  /** Above the fold: loads eagerly and wipes open on page load. */
  priority?: boolean;
  /** Stagger for the page-load wipe, in milliseconds. */
  delay?: number;
  /** Scales the image slightly while an ancestor `.group` is hovered. */
  zoom?: boolean;
  /**
   * Rendered in place of the image when no frame is available. Without it
   * the figure renders nothing at all rather than leaving an empty bed,
   * so removing placeholder photography collapses the slot cleanly.
   */
  fallback?: ReactNode;
  className?: string;
  sizes?: string;
}

/**
 * The only image frame on the site. Photography sits directly on a concrete
 * bed with square corners and no border, because a project photograph is
 * content, not an illustration that needs a container.
 */
export function Figure({
  frame,
  alt,
  ratio,
  priority = false,
  delay = 0,
  zoom = false,
  fallback,
  className,
  sizes,
}: FigureProps) {
  if (!frame && !fallback) {
    return null;
  }

  return (
    <div
      className={cn("bg-concrete relative overflow-hidden", className)}
      style={ratio ? { aspectRatio: ratio } : undefined}
    >
      {frame ? (
        <img
          src={frame.src}
          alt={alt}
          width={frame.width}
          height={frame.height}
          sizes={sizes}
          loading={priority ? "eager" : "lazy"}
          fetchPriority={priority ? "high" : undefined}
          decoding="async"
          style={priority ? riseDelay(delay) : undefined}
          className={cn(
            "h-full w-full object-cover",
            priority && "animate-unmask",
            zoom &&
              "transition-transform duration-[900ms] ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:scale-[1.04] group-focus-visible:scale-[1.04]",
          )}
        />
      ) : (
        fallback
      )}
    </div>
  );
}
