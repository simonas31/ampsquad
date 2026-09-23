import type { ReactNode } from "react";
import { Breadcrumbs } from "@/components/content/Breadcrumbs";
import type { Breadcrumb } from "@/types";
import { Container } from "./Container";

interface PageHeaderProps {
  breadcrumbs: Breadcrumb[];
  title: string;
  description?: string;
  /** Extra header content below the title (e.g. project particulars). */
  children?: ReactNode;
  size?: "wide" | "default" | "narrow";
}

/**
 * The opening band of an inner page: trail, the page's own <h1> at display
 * size, and whatever particulars the page wants beneath it. It carries no
 * background of its own, so the page starts on plaster and the rule under
 * it does the separating.
 */
export function PageHeader({
  breadcrumbs,
  title,
  description,
  children,
  size = "wide",
}: PageHeaderProps) {
  return (
    <div className="border-rule border-b">
      <Container size={size} className="pt-8 pb-12 lg:pt-10 lg:pb-16">
        <Breadcrumbs items={breadcrumbs} />
        <h1 className="display text-ink mt-8 text-[clamp(1.875rem,5vw,4.5rem)]">
          {title}
        </h1>
        {description && (
          <p className="text-ink-soft mt-6 max-w-[48ch] text-lg text-pretty">
            {description}
          </p>
        )}
        {children}
      </Container>
    </div>
  );
}
