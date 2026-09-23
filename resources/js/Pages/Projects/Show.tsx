import { Link, usePage } from "@inertiajs/react";
import { ArrowLeft } from "lucide-react";
import { Figure } from "@/components/common/Figure";
import { BlockRenderer } from "@/components/content/BlockRenderer";
import { Gallery } from "@/components/content/Gallery";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { useT } from "@/hooks/use-t";
import { projectFrame } from "@/lib/project-image";
import { formatDate } from "@/lib/utils";
import type { Breadcrumb, Project } from "@/types";

interface ShowProps {
  project: Project;
  breadcrumbs: Breadcrumb[];
}

export default function Show({ project, breadcrumbs }: ShowProps) {
  const t = useT();
  const { locale } = usePage().props;
  const backUrl = breadcrumbs[1]?.url;
  const frame = projectFrame(project, "hero");

  const details = [
    { label: t("content.location"), value: project.location },
    { label: t("content.client"), value: project.clientName },
    {
      label: t("content.completed"),
      value: project.completedAt
        ? formatDate(project.completedAt, locale.current)
        : null,
    },
    { label: t("content.status"), value: project.category.name },
  ].filter((detail) => detail.value);

  return (
    <>
      <PageHeader
        breadcrumbs={breadcrumbs}
        title={project.title}
        description={project.excerpt ?? undefined}
      >
        <dl className="border-rule mt-12 grid grid-cols-2 gap-x-8 gap-y-7 border-t pt-8 lg:grid-cols-4">
          {details.map(({ label, value }) => (
            <div key={label}>
              <dt className="meta text-ink-soft">{label}</dt>
              <dd className="text-ink mt-1.5">{value}</dd>
            </div>
          ))}
        </dl>
      </PageHeader>

      {frame && (
        <Container size="wide" className="pt-10 lg:pt-14">
          <Figure
            frame={frame}
            alt=""
            priority
            sizes="100vw"
            className="aspect-[3/2] lg:aspect-[2/1]"
          />
        </Container>
      )}

      <Container size="narrow" className="py-14 lg:py-20">
        {project.blocks && <BlockRenderer blocks={project.blocks} />}

        {project.gallery.length > 0 && (
          <div className="mt-12">
            <Gallery
              items={project.gallery.map((image, index) => ({
                thumbnail: image.thumbnail,
                full: image.large,
                alt: `${project.title} ${index + 1}`,
              }))}
            />
          </div>
        )}

        {project.tags.length > 0 && (
          <ul className="border-rule mt-12 flex flex-wrap gap-x-6 gap-y-2 border-t pt-6">
            {project.tags.map((tag) => (
              <li key={tag.id} className="meta text-ink-soft">
                {tag.name}
              </li>
            ))}
          </ul>
        )}

        {backUrl && (
          <div className="border-rule mt-12 border-t pt-6">
            <Link
              href={backUrl}
              className="meta text-ink group inline-flex items-center gap-2 hover:underline"
            >
              <ArrowLeft
                className="size-4 transition-transform duration-200 group-hover:-translate-x-1"
                aria-hidden="true"
              />
              {t("content.backToProjects")}
            </Link>
          </div>
        )}
      </Container>
    </>
  );
}
