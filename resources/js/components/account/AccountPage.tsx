import type { ReactNode } from "react";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import type { Breadcrumb } from "@/types";
import { AccountNav } from "./AccountNav";

interface AccountPageProps {
  breadcrumbs: Breadcrumb[];
  title: string;
  description?: string;
  children: ReactNode;
}

/**
 * What every page of the account area is made of: the usual opening band,
 * then the account's sections beside the page's own content.
 */
export function AccountPage({
  breadcrumbs,
  title,
  description,
  children,
}: AccountPageProps) {
  return (
    <>
      <PageHeader
        breadcrumbs={breadcrumbs}
        title={title}
        description={description}
        size="default"
      />

      <Container size="default" className="py-10 lg:py-16">
        <div className="grid grid-cols-1 gap-x-12 gap-y-8 lg:grid-cols-12">
          <aside className="lg:col-span-3">
            <AccountNav />
          </aside>
          <div className="max-w-3xl min-w-0 lg:col-span-9">{children}</div>
        </div>
      </Container>
    </>
  );
}
