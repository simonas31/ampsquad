import { Reveal } from "@/components/common/Reveal";
import { SectionHeading } from "@/components/common/SectionHeading";
import { Container } from "@/components/layout/Container";

export function IntroSection({
  title,
  content,
}: {
  title: string;
  content: string;
}) {
  return (
    <section className="py-16 sm:py-24">
      <Container>
        <Reveal className="grid gap-8 lg:grid-cols-2 lg:gap-16">
          <SectionHeading title={title} />
          <p className="text-muted-foreground text-lg text-pretty lg:pt-6">
            {content}
          </p>
        </Reveal>
      </Container>
    </section>
  );
}
