import { BlockRenderer } from "@/components/content/BlockRenderer";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import type { Breadcrumb, Page } from "@/types";

interface ShowProps {
  page: Page;
  breadcrumbs: Breadcrumb[];
}

export default function Show({ page, breadcrumbs }: ShowProps) {
  return (
    <>
      <PageHeader breadcrumbs={breadcrumbs} title={page.title} size="narrow" />

      <Container size="narrow" className="py-12 lg:py-16">
        {page.featuredImageUrl && (
          <img
            src={page.featuredImageUrl}
            alt=""
            className="shadow-card mb-10 w-full rounded-lg"
          />
        )}

        <BlockRenderer blocks={page.blocks} />
      </Container>
    </>
  );
}
