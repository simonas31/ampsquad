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
    <section className="bg-surface">
      <Container size="narrow" className="py-24 sm:py-32">
        <span
          className="bg-accent mb-6 block h-1 w-14 rounded-full"
          aria-hidden="true"
        />
        <h1 className="text-primary text-4xl sm:text-5xl">
          {t(`errors.${key}.title`)}
        </h1>
        <p className="text-muted-foreground mt-5 max-w-xl text-lg">
          {t(`errors.${key}.description`)}
        </p>
        <Button asChild variant="accent" size="lg" className="mt-8">
          <Link href={homeUrl}>{t("errors.backHome")}</Link>
        </Button>
      </Container>
    </section>
  );
}
