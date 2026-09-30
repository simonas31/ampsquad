import { Info } from "lucide-react";
import { PriceCalculator } from "@/components/calculator/PriceCalculator";
import { Container } from "@/components/layout/Container";
import { PageHeader } from "@/components/layout/PageHeader";
import { useNavUrl } from "@/hooks/use-nav-url";
import { useT } from "@/hooks/use-t";
import type { Breadcrumb } from "@/types";

export default function PriceCalculatorPage({
  breadcrumbs,
}: {
  breadcrumbs: Breadcrumb[];
}) {
  const t = useT();
  const contactUrl = useNavUrl("nav.contact", "/contact");

  return (
    <>
      <PageHeader
        breadcrumbs={breadcrumbs}
        title={t("nav.priceCalculator")}
        description={t("priceCalculator.subtitle")}
      >
        <p
          role="note"
          className="bg-signal/15 text-ink mt-8 flex max-w-[62ch] items-start gap-3 rounded-2xl px-5 py-4 text-base"
        >
          <Info className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
          {t("priceCalculator.notice")}
        </p>
      </PageHeader>

      <section className="bg-plaster py-12 lg:py-16">
        <Container size="wide">
          <div className="mx-auto max-w-6xl">
            <PriceCalculator contactUrl={contactUrl} />
          </div>
        </Container>
      </section>
    </>
  );
}
