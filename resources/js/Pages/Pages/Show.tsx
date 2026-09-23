import { Figure } from "@/components/common/Figure";
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

      {page.featuredImageUrl && (
        <Container size="wide" className="pt-10 lg:pt-14">
          <Figure
            frame={{
              src: page.featuredImageUrl,
              width: 1800,
              height: 900,
              isPlaceholder: false,
            }}
            alt=""
            priority
            sizes="100vw"
            className="aspect-[3/2] lg:aspect-[2/1]"
          />
        </Container>
      )}

      <Container size="narrow" className="py-14 lg:py-20">
        <BlockRenderer blocks={page.blocks} />
      </Container>
    </>
  );
}
