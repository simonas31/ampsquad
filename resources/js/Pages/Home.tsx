import { usePage } from "@inertiajs/react";
import { CapabilitiesSection } from "@/components/home/CapabilitiesSection";
import { FeaturedProjectsSection } from "@/components/home/FeaturedProjectsSection";
import { HeroSection } from "@/components/home/HeroSection";
import { InquirySection } from "@/components/home/InquirySection";
import { IntroSection } from "@/components/home/IntroSection";
import { PriceCalculatorSection } from "@/components/home/PriceCalculatorSection";
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

/**
 * The page runs statement, portfolio, capability, estimate, evidence,
 * enquiry. Each section uses a different layout family, and the tonal
 * rhythm groups them: plaster for the two reading sections, paper for the
 * two looking sections, then graphite from the enquiry through the footer.
 */
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
      <CapabilitiesSection categories={calculatorCategories} />
      <PriceCalculatorSection contactUrl={contactUrl} />
      <VideoCarouselSection videos={videos} />
      <InquirySection
        title={cta.title}
        contactUrl={contactUrl}
        contact={site.contact}
      />
    </>
  );
}
