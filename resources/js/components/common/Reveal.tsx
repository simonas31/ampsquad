import { useEffect, useRef, useState, type ComponentProps } from "react";
import { cn } from "@/lib/utils";

interface RevealProps extends ComponentProps<"div"> {
  /** Stagger delay in milliseconds, for items revealed together. */
  delay?: number;
}

function prefersReducedMotion(): boolean {
  return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

/**
 * Fades its children in the first time they scroll into view, then stops
 * observing — sections animate in once and stay put, they don't re-trigger
 * on scroll-back. Reduced-motion users get the content immediately, so
 * nothing depends on the observer firing.
 */
export function Reveal({ delay = 0, className, style, ...props }: RevealProps) {
  const ref = useRef<HTMLDivElement>(null);
  const [visible, setVisible] = useState(prefersReducedMotion);

  useEffect(() => {
    const node = ref.current;

    if (visible || !node) {
      return;
    }

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry?.isIntersecting) {
          setVisible(true);
          observer.disconnect();
        }
      },
      { threshold: 0.15 },
    );

    observer.observe(node);

    return () => observer.disconnect();
  }, [visible]);

  return (
    <div
      ref={ref}
      className={cn(
        "transition-[opacity,transform] duration-[850ms] ease-[cubic-bezier(0.16,1,0.3,1)]",
        visible ? "translate-y-0 opacity-100" : "translate-y-4 opacity-0",
        className,
      )}
      style={{ ...style, transitionDelay: visible ? `${delay}ms` : "0ms" }}
      {...props}
    />
  );
}
