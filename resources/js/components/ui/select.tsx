import { ChevronDown } from "lucide-react";
import type { ComponentProps } from "react";
import { controlClasses } from "@/components/ui/input";
import { cn } from "@/lib/utils";

/**
 * A native select dressed like the other inputs. The browser's own list
 * stays in charge of opening, keyboard and touch behaviour, which is what
 * makes it dependable with a few hundred options.
 */
export function Select({
  className,
  children,
  ...props
}: ComponentProps<"select">) {
  return (
    <div className="relative">
      <select
        className={cn(controlClasses, "h-12 appearance-none pr-11", className)}
        {...props}
      >
        {children}
      </select>
      <ChevronDown
        className="text-ink-soft pointer-events-none absolute top-1/2 right-4 size-4 -translate-y-1/2"
        aria-hidden="true"
      />
    </div>
  );
}
