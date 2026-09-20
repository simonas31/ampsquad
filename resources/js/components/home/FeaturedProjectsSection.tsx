import { Link } from "@inertiajs/react";
import { ArrowRight } from "lucide-react";
import { Reveal } from "@/components/common/Reveal";
import { SectionHeading } from "@/components/common/SectionHeading";
import { ContentCard } from "@/components/content/ContentCard";
import { Container } from "@/components/layout/Container";
import { Button } from "@/components/ui/button";
import { useT } from "@/hooks/use-t";
import type { Project } from "@/types";

interface FeaturedProjectsSectionProps {
  projects: Project[];
  projectsUrl: string;
}

export function FeaturedProjectsSection({
  projects,
  projectsUrl,
}: FeaturedProjectsSectionProps) {
  const t = useT();

  if (projects.length === 0) {
    return null;
  }

  return (
    <section className="bg-surface py-16 sm:py-24">
      <Container>
        <Reveal>
          <SectionHeading
            title={t("home.featuredProjects.title")}
            action={
              <Button
                asChild
                variant="outline"
                className="self-start sm:self-auto"
              >
                <Link href={projectsUrl}>
                  {t("home.featuredProjects.viewAll")}
                  <ArrowRight aria-hidden="true" />
                </Link>
              </Button>
            }
          />
        </Reveal>

        <ul className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {projects.map((project, index) => (
            <li key={project.id}>
              <Reveal delay={index * 75} className="h-full">
                <ContentCard project={project} />
              </Reveal>
            </li>
          ))}
        </ul>
      </Container>
    </section>
  );
}
