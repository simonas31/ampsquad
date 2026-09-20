import { Link, usePage } from "@inertiajs/react";
import { ArrowLeft, Calendar, MapPin, User } from "lucide-react";
import { BlockRenderer } from "@/components/content/BlockRenderer";
import { Gallery } from "@/components/content/Gallery";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { Badge } from "@/components/ui/badge";
import { useT } from "@/hooks/use-t";
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

  const details = [
    { icon: MapPin, label: t("content.location"), value: project.location },
    { icon: User, label: t("content.client"), value: project.clientName },
    {
      icon: Calendar,
      label: t("content.completed"),
      value: project.completedAt
        ? formatDate(project.completedAt, locale.current)
        : null,
    },
  ].filter((detail) => detail.value);

  return (
    <>
      <PageHeader breadcrumbs={breadcrumbs} title={project.title}>
        <div className="mt-6 space-y-5">
          <Badge variant="accent">{project.category.name}</Badge>

          {details.length > 0 && (
            <dl className="text-muted-foreground grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
              {details.map(({ icon: Icon, label, value }) => (
                <div key={label}>
                  <dt className="text-foreground flex items-center gap-1.5 font-semibold">
                    <Icon
                      className="text-accent-text size-4"
                      aria-hidden="true"
                    />
                    {label}
                  </dt>
                  <dd className="mt-0.5">{value}</dd>
                </div>
              ))}
            </dl>
          )}

          {project.tags.length > 0 && (
            <ul className="flex flex-wrap gap-2">
              {project.tags.map((tag) => (
                <li key={tag.id}>
                  <Badge variant="outline">{tag.name}</Badge>
                </li>
              ))}
            </ul>
          )}
        </div>
      </PageHeader>

      <Container size="narrow" className="py-12 lg:py-16">
        {project.featuredImageUrl && (
          <img
            src={project.featuredImageUrl}
            alt=""
            className="shadow-card mb-10 w-full rounded-lg"
          />
        )}

        {project.blocks && <BlockRenderer blocks={project.blocks} />}

        {project.gallery.length > 0 && (
          <div className="mt-10">
            <Gallery
              items={project.gallery.map((image, index) => ({
                thumbnail: image.thumbnail,
                full: image.large,
                alt: `${project.title} ${index + 1}`,
              }))}
            />
          </div>
        )}

        {backUrl && (
          <div className="mt-12 border-t pt-6">
            <Link
              href={backUrl}
              className="text-primary group inline-flex items-center gap-2 text-sm font-semibold hover:underline underline-offset-4"
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
