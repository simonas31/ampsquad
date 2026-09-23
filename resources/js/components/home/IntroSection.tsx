import { Reveal } from "@/components/common/Reveal";
import { Container } from "@/components/layout/Container";

/**
 * The positioning statement. It gets no photograph and no columns: after a
 * photograph-led hero, a single block of type at reading size is the
 * strongest possible change of pace, and it lets the admin-authored copy
 * run at whatever length it needs.
 */
export function IntroSection({
  title,
  content,
}: {
  title: string;
  content: string;
}) {
  return (
    <section className="bg-plaster py-20 lg:py-28">
      <Container size="wide">
        <Reveal className="max-w-[46rem]">
          <h2 className="display text-ink text-3xl sm:text-4xl">{title}</h2>
          <p className="text-ink mt-8 text-2xl leading-[1.35] text-pretty sm:text-3xl sm:leading-[1.3]">
            {content}
          </p>
        </Reveal>
      </Container>
    </section>
  );
}
