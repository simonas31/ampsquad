import { Link } from "@inertiajs/react";
import { Zap } from "lucide-react";
import { Container } from "@/components/layout/Container";
import { Button } from "@/components/ui/button";
import { useT } from "@/hooks/use-t";

interface HeroSectionProps {
  title: string;
  subtitle: string;
  contactUrl: string;
  projectsUrl: string;
}

export function HeroSection({
  title,
  subtitle,
  contactUrl,
  projectsUrl,
}: HeroSectionProps) {
  const t = useT();

  return (
    <section className="surface-navy bg-navy text-navy-foreground relative overflow-hidden">
      <div
        className="bg-blueprint absolute inset-0 mask-[radial-gradient(ellipse_at_top_right,black,transparent_70%)]"
        aria-hidden="true"
      />
      <div
        className="bg-accent/20 pointer-events-none absolute -top-32 -right-32 size-112 rounded-full blur-3xl"
        aria-hidden="true"
      />
      <Zap
        className="text-accent/10 pointer-events-none absolute top-1/2 right-[6%] hidden size-104 -translate-y-1/2 lg:block"
        strokeWidth={0.6}
        aria-hidden="true"
      />

      <Container className="animate-in fade-in slide-in-from-bottom-4 relative py-20 duration-700 sm:py-28 lg:py-36">
        <span
          className="bg-accent mb-6 block h-1 w-14 rounded-full"
          aria-hidden="true"
        />
        <h1 className="max-w-3xl text-4xl text-white sm:text-5xl lg:text-6xl">
          {title}
        </h1>
        <p className="text-navy-muted mt-6 max-w-xl text-lg text-pretty sm:text-xl">
          {subtitle}
        </p>
        <div className="mt-10 flex flex-wrap gap-3">
          <Button asChild variant="accent" size="lg">
            <Link href={contactUrl}>{t("home.hero.cta")}</Link>
          </Button>
          <Button asChild variant="inverse" size="lg">
            <Link href={projectsUrl}>{t("home.hero.secondaryCta")}</Link>
          </Button>
        </div>
      </Container>
    </section>
  );
}
