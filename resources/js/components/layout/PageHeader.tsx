import type { ReactNode } from "react";
import { Breadcrumbs } from "@/components/content/Breadcrumbs";
import type { Breadcrumb } from "@/types";
import { Container } from "./Container";

interface PageHeaderProps {
  breadcrumbs: Breadcrumb[];
  title: string;
  description?: string;
  /** Extra header content below the title (e.g. project metadata). */
  children?: ReactNode;
  size?: "default" | "narrow";
}

/** Shared top band for inner pages: breadcrumbs, the page's <h1> and optional details. */
export function PageHeader({
  breadcrumbs,
  title,
  description,
  children,
  size = "default",
}: PageHeaderProps) {
  return (
    <div className="bg-surface border-b">
      <Container size={size} className="py-8 lg:py-12">
        <Breadcrumbs items={breadcrumbs} />
        <h1 className="text-primary mt-5 text-3xl sm:text-4xl lg:text-5xl">
          {title}
        </h1>
        {description && (
          <p className="text-muted-foreground mt-4 max-w-2xl text-lg text-pretty">
            {description}
          </p>
        )}
        {children}
      </Container>
    </div>
  );
}
