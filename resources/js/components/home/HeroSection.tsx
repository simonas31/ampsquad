import { Link } from "@inertiajs/react";
import { Figure } from "@/components/common/Figure";
import { Container } from "@/components/layout/Container";
import { Button } from "@/components/ui/button";
import { useT } from "@/hooks/use-t";
import { subjectFrame } from "@/lib/project-image";
import { riseDelay } from "@/lib/utils";

interface HeroSectionProps {
  title: string;
  subtitle: string;
  contactUrl: string;
  projectsUrl: string;
}

/**
 * The opening spread: the company statement set across the full measure,
 * then the supporting line and the two ways into the site held in the first
 * four columns, with the photograph taking the rest. The page-load sequence
 * runs once here (headline, supporting line, actions, then the photograph
 * wiping open) and nowhere else above the fold.
 */
export function HeroSection({
  title,
  subtitle,
  contactUrl,
  projectsUrl,
}: HeroSectionProps) {
  const t = useT();
  const frame = subjectFrame("hero-installation", "hero");

  return (
    <section className="border-rule border-b">
      <Container size="wide" className="pt-10 pb-14 lg:pt-14 lg:pb-20">
        <h1
          className="display animate-rise text-ink text-[clamp(2.25rem,5.2vw,4.75rem)]"
          style={riseDelay(60)}
        >
          {title}
        </h1>

        <div className="mt-10 grid grid-cols-1 items-end gap-x-10 gap-y-8 lg:mt-12 lg:grid-cols-12">
          <div className="lg:col-span-4">
            <p
              className="animate-rise text-ink-soft max-w-[42ch] text-lg text-pretty"
              style={riseDelay(180)}
            >
              {subtitle}
            </p>
            <div
              className="animate-rise mt-8 flex flex-wrap gap-3"
              style={riseDelay(280)}
            >
              <Button asChild variant="accent" size="lg">
                <Link href={contactUrl}>{t("common.requestQuote")}</Link>
              </Button>
              <Button asChild variant="outline" size="lg">
                <Link href={projectsUrl}>
                  {t("home.featuredProjects.viewAll")}
                </Link>
              </Button>
            </div>
          </div>

          <Figure
            frame={frame}
            alt=""
            priority
            delay={360}
            sizes="(min-width: 1024px) 62vw, 100vw"
            className="aspect-[3/2] lg:col-span-8 lg:aspect-[2/1]"
          />
        </div>
      </Container>
    </section>
  );
}
