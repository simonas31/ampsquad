import { usePage } from "@inertiajs/react";
import { CalculatorTeaserSection } from "@/components/home/CalculatorTeaserSection";
import { ContactCtaSection } from "@/components/home/ContactCtaSection";
import { FeaturedProjectsSection } from "@/components/home/FeaturedProjectsSection";
import { HeroSection } from "@/components/home/HeroSection";
import { IntroSection } from "@/components/home/IntroSection";
import { VideoCarouselSection } from "@/components/home/VideoCarouselSection";
import { useNavUrl } from "@/hooks/use-nav-url";
import type {
  CalculatorCategoryTeaser,
  Project,
  SeoData,
  Video,
} from "@/types";

interface HomeProps {
  seo: SeoData;
  hero: { title: string; subtitle: string };
  intro: { title: string; content: string };
  cta: { title: string; buttonLabel: string };
  featuredProjects: Project[];
  videos: Video[];
  calculatorCategories: CalculatorCategoryTeaser[];
}

export default function Home({
  hero,
  intro,
  cta,
  featuredProjects,
  videos,
  calculatorCategories,
}: HomeProps) {
  const { site } = usePage().props;
  const contactUrl = useNavUrl("nav.contact", "/contact");
  const projectsUrl = useNavUrl("nav.projects", "/projects");

  return (
    <>
      <HeroSection
        title={hero.title}
        subtitle={hero.subtitle}
        contactUrl={contactUrl}
        projectsUrl={projectsUrl}
      />
      <IntroSection title={intro.title} content={intro.content} />
      <FeaturedProjectsSection
        projects={featuredProjects}
        projectsUrl={projectsUrl}
      />
      <VideoCarouselSection videos={videos} />
      <CalculatorTeaserSection
        categories={calculatorCategories}
        contactUrl={contactUrl}
      />
      <ContactCtaSection
        title={cta.title}
        buttonLabel={cta.buttonLabel}
        contactUrl={contactUrl}
        contact={site.contact}
      />
    </>
  );
}
