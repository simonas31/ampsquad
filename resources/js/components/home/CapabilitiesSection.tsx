import { Figure } from "@/components/common/Figure";
import { Reveal } from "@/components/common/Reveal";
import { SectionHeading } from "@/components/common/SectionHeading";
import { Container } from "@/components/layout/Container";
import { useT } from "@/hooks/use-t";
import { HeroIcon } from "@/lib/heroicon";
import { subjectFrame } from "@/lib/project-image";
import { cn } from "@/lib/utils";
import type { CalculatorCategoryTeaser } from "@/types";

/**
 * Each capability gets a different column span, a different photograph
 * proportion and a different vertical start, so the section reads as a
 * composition rather than a row of equal tiles. The pattern repeats every
 * three entries if more capabilities are added in the admin panel.
 */
const COMPOSITION = [
  {
    column: "lg:col-span-5 lg:col-start-1",
    ratio: "4 / 5",
    frame: "portrait",
    offset: "",
  },
  {
    column: "lg:col-span-4 lg:col-start-9",
    ratio: "1 / 1",
    frame: "square",
    offset: "lg:mt-28",
  },
  {
    column: "lg:col-span-6 lg:col-start-2",
    ratio: "16 / 9",
    frame: "strip",
    offset: "",
  },
] as const;

export function CapabilitiesSection({
  categories,
}: {
  categories: CalculatorCategoryTeaser[];
}) {
  const t = useT();

  if (categories.length === 0) {
    return null;
  }

  return (
    <section className="border-rule border-t py-20 lg:py-28">
      <Container size="wide">
        <Reveal>
          <SectionHeading title={t("home.capabilities.title")} />
        </Reveal>

        <ul className="mt-14 grid grid-cols-1 gap-x-10 gap-y-16 lg:mt-20 lg:grid-cols-12">
          {categories.map((category, index) => {
            const composition = COMPOSITION[index % COMPOSITION.length];

            return (
              <li
                key={category.id}
                className={cn(composition.column, composition.offset)}
              >
                <Reveal delay={(index % 3) * 90}>
                  <Figure
                    frame={subjectFrame(
                      `capability-${category.id}`,
                      composition.frame,
                    )}
                    alt=""
                    ratio={composition.ratio}
                    sizes="(min-width: 1024px) 45vw, 100vw"
                  />

                  <div className="mt-7 flex items-start gap-3.5">
                    <HeroIcon
                      name={category.icon}
                      className="text-ink mt-1.5 size-5 shrink-0"
                    />
                    <h3 className="display text-ink text-3xl lg:text-4xl">
                      {category.name}
                    </h3>
                  </div>

                  {category.options.length > 0 && (
                    <div className="border-rule mt-6 border-t pt-4">
                      <p className="meta text-ink-soft">
                        {t("home.capabilities.scope")}
                      </p>
                      <ul className="meta text-ink mt-2.5 flex flex-wrap gap-x-6 gap-y-1">
                        {category.options.map((option) => (
                          <li key={option}>{option}</li>
                        ))}
                      </ul>
                    </div>
                  )}
                </Reveal>
              </li>
            );
          })}
        </ul>
      </Container>
    </section>
  );
}
