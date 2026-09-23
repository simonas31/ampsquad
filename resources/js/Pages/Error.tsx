import { Link } from "@inertiajs/react";
import { Container } from "@/components/layout/Container";
import { Button } from "@/components/ui/button";
import { useNavUrl } from "@/hooks/use-nav-url";
import { useT } from "@/hooks/use-t";

export default function Error({ status }: { status: 403 | 404 | 500 | 503 }) {
  const t = useT();
  const homeUrl = useNavUrl("nav.home", "/");
  const key = `${status}` as const;

  return (
    <section>
      <Container size="wide" className="py-24 lg:py-32">
        <p className="display text-concrete text-[clamp(6rem,20vw,16rem)] leading-[0.8]">
          {status}
        </p>
        <h1 className="display text-ink border-ink mt-10 max-w-[20ch] border-t pt-8 text-3xl sm:text-4xl">
          {/* The translation carries its own "404: " prefix, which the
              display figure above already states. */}
          {t(`errors.${key}.title`).replace(/^\d+:\s*/, "")}
        </h1>
        <p className="text-ink-soft mt-6 max-w-[48ch] text-lg text-pretty">
          {t(`errors.${key}.description`)}
        </p>
        <Button asChild variant="outline" size="lg" className="mt-10">
          <Link href={homeUrl}>{t("errors.backHome")}</Link>
        </Button>
      </Container>
    </section>
  );
}
