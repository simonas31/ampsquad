import { Link } from "@inertiajs/react";
import { Check } from "lucide-react";
import { Reveal } from "@/components/common/Reveal";
import { SectionHeading } from "@/components/common/SectionHeading";
import { Container } from "@/components/layout/Container";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { useT } from "@/hooks/use-t";
import { HeroIcon } from "@/lib/heroicon";
import type { CalculatorCategoryTeaser } from "@/types";

interface CalculatorTeaserSectionProps {
  categories: CalculatorCategoryTeaser[];
  contactUrl: string;
}

export function CalculatorTeaserSection({
  categories,
  contactUrl,
}: CalculatorTeaserSectionProps) {
  const t = useT();

  if (categories.length === 0) {
    return null;
  }

  return (
    <section className="bg-surface py-16 sm:py-24">
      <Container>
        <Reveal>
          <SectionHeading
            title={t("home.calculator.title")}
            description={t("home.calculator.subtitle")}
            action={
              <Button
                asChild
                variant="accent"
                size="lg"
                className="self-start sm:self-auto"
              >
                <Link href={contactUrl}>{t("home.calculator.cta")}</Link>
              </Button>
            }
          />
        </Reveal>

        <ul className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {categories.map((category, index) => (
            <li key={category.id}>
              <Reveal delay={index * 75} className="h-full">
                <Card className="hover:shadow-card-hover h-full border-t-4 border-t-accent p-6 transition-shadow duration-300">
                  <span className="bg-secondary text-primary mb-4 inline-flex size-12 items-center justify-center rounded-lg">
                    <HeroIcon name={category.icon} className="size-6" />
                  </span>
                  <h3 className="text-primary text-xl">{category.name}</h3>
                  {category.options.length > 0 && (
                    <ul className="text-muted-foreground mt-4 space-y-2 text-sm">
                      {category.options.map((option) => (
                        <li key={option} className="flex items-start gap-2">
                          <Check
                            className="text-accent-text mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                          />
                          {option}
                        </li>
                      ))}
                    </ul>
                  )}
                </Card>
              </Reveal>
            </li>
          ))}
        </ul>
      </Container>
    </section>
  );
}
