import { Link } from "@inertiajs/react";
import { Reveal } from "@/components/common/Reveal";
import { SectionHeading } from "@/components/common/SectionHeading";
import { ProjectLead } from "@/components/content/ProjectLead";
import { ProjectRow } from "@/components/content/ProjectRow";
import { Container } from "@/components/layout/Container";
import { Button } from "@/components/ui/button";
import { useT } from "@/hooks/use-t";
import type { Project } from "@/types";

interface FeaturedProjectsSectionProps {
  projects: Project[];
  projectsUrl: string;
}

/**
 * The portfolio, curated rather than gridded: the first project is given a
 * full composition of its own and the rest follow as ruled index entries.
 * That hierarchy is the point. A grid would say every project is equally
 * interesting, which is never true of a body of work.
 */
export function FeaturedProjectsSection({
  projects,
  projectsUrl,
}: FeaturedProjectsSectionProps) {
  const t = useT();

  if (projects.length === 0) {
    return null;
  }

  const [lead, ...rest] = projects;

  return (
    <section className="py-20 lg:py-28">
      <Container size="wide">
        <Reveal>
          <SectionHeading
            title={t("home.featuredProjects.title")}
            action={
              <Button asChild variant="link">
                <Link href={projectsUrl}>
                  {t("home.featuredProjects.viewAll")}
                </Link>
              </Button>
            }
          />
        </Reveal>

        <Reveal delay={80} className="mt-14 lg:mt-20">
          <ProjectLead project={lead} />
        </Reveal>

        {rest.length > 0 && (
          <Reveal delay={140} className="mt-16 lg:mt-20">
            <ul>
              {rest.map((project) => (
                <ProjectRow key={project.id} project={project} />
              ))}
            </ul>
          </Reveal>
        )}
      </Container>
    </section>
  );
}
