import { Link, usePage } from "@inertiajs/react";
import { Container } from "@/components/layout/Container";
import { Button } from "@/components/ui/button";
import { useT } from "@/hooks/use-t";
import { formatNumber, riseDelay, telHref } from "@/lib/utils";
import type { HeroStat } from "@/types";

/**
 * The client supplies the photograph as a static file. Until it is in place
 * the navy background colour shows through, so the hero is never blank.
 */
const HERO_IMAGE = "/images/hero.jpg";

interface HeroSectionProps {
  title: string;
  subtitle: string;
  stats: HeroStat[];
  projectsUrl: string;
}

/**
 * One full-screen photograph with the company statement centred over it: the
 * headline, the supporting line, the two ways into the site and a short row
 * of headline figures. The floating header sits on top of it, which is why
 * the home page is the only one that starts at the very top of the viewport.
 * The page-load sequence runs once here and nowhere else above the fold.
 */
export function HeroSection({
  title,
  subtitle,
  stats,
  projectsUrl,
}: HeroSectionProps) {
  const t = useT();
  const { site, locale } = usePage().props;

  return (
    <section
      className="on-ink bg-ink text-bone relative isolate flex min-h-svh items-center justify-center overflow-hidden bg-cover bg-center"
      style={{ backgroundImage: `url(${HERO_IMAGE})` }}
    >
      {/* A dark veil keeps white type legible on any photograph. */}
      <div
        aria-hidden="true"
        className="from-ink/75 via-ink/60 to-ink/85 absolute inset-0 -z-10 bg-linear-to-b"
      />

      <Container
        size="default"
        className="flex flex-col items-center pt-32 pb-14 text-center lg:pt-36 lg:pb-16"
      >
        <h1
          className="display animate-rise text-bone max-w-[22ch] text-[clamp(2.25rem,6vw,5rem)]"
          style={riseDelay(60)}
        >
          {title}
        </h1>

        <p
          className="animate-rise text-bone/90 mt-6 max-w-[58ch] text-lg text-pretty lg:text-xl"
          style={riseDelay(180)}
        >
          {subtitle}
        </p>

        <div
          className="animate-rise mt-9 flex flex-wrap justify-center gap-3"
          style={riseDelay(280)}
        >
          <Button asChild variant="accent" size="lg">
            <a href={telHref(site.contact.phone)}>{site.contact.phone}</a>
          </Button>
          <Button asChild variant="inverse" size="lg">
            <Link href={projectsUrl}>{t("home.featuredProjects.viewAll")}</Link>
          </Button>
        </div>

        {stats.length > 0 && (
          <dl
            className="animate-rise border-bone/25 mt-14 grid w-full max-w-4xl gap-y-8 border-t pt-10 sm:mt-16 sm:grid-flow-col sm:auto-cols-fr sm:gap-y-0"
            style={riseDelay(380)}
          >
            {stats.map((stat) => (
              <div
                key={stat.label}
                className="sm:border-bone/25 flex flex-col gap-2 px-4 sm:border-l sm:first:border-l-0"
              >
                <dt className="text-bone/80 order-2 text-xs font-medium tracking-widest uppercase">
                  {stat.label}
                </dt>
                <dd className="display text-bone order-1 text-4xl tabular-nums lg:text-5xl">
                  {formatNumber(stat.value, locale.current)}{" "}
                  <span className="text-signal">{stat.unit}</span>
                </dd>
              </div>
            ))}
          </dl>
        )}
      </Container>
    </section>
  );
}
