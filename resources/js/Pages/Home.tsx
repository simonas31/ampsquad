import { usePage } from "@inertiajs/react";
import { CapabilitiesSection } from "@/components/home/CapabilitiesSection";
import { FeaturedProjectsSection } from "@/components/home/FeaturedProjectsSection";
import { HeroSection } from "@/components/home/HeroSection";
import { InquirySection } from "@/components/home/InquirySection";
import { IntroSection } from "@/components/home/IntroSection";
import { VideoCarouselSection } from "@/components/home/VideoCarouselSection";
import { useNavUrl } from "@/hooks/use-nav-url";
import type {
  CapabilityTeaser,
  HeroStat,
  Project,
  SeoData,
  Video,
} from "@/types";

interface HomeProps {
  seo: SeoData;
  hero: {
    title: string;
    subtitle: string;
    stats: HeroStat[];
    imageUrl: string | null;
  };
  intro: { title: string; content: string };
  cta: { title: string; buttonLabel: string };
  featuredProjects: Project[];
  videos: Video[];
  capabilities: CapabilityTeaser[];
}

/**
 * The page runs a full-screen statement, then positioning, portfolio,
 * capability, evidence and enquiry. The hero is one photograph under the
 * floating header; the tonal rhythm after it groups the reading sections on
 * plaster and the looking sections on paper, then turns navy from the
 * enquiry through the footer.
 */
export default function Home({
  hero,
  intro,
  cta,
  featuredProjects,
  videos,
  capabilities,
}: HomeProps) {
  const { site } = usePage().props;
  const contactUrl = useNavUrl("nav.contact", "/contact");
  const projectsUrl = useNavUrl("nav.projects", "/projects");

  return (
    <>
      <HeroSection
        title={hero.title}
        subtitle={hero.subtitle}
        stats={hero.stats}
        imageUrl={hero.imageUrl}
        projectsUrl={projectsUrl}
      />
      <IntroSection title={intro.title} content={intro.content} />
      <FeaturedProjectsSection
        projects={featuredProjects}
        projectsUrl={projectsUrl}
      />
      <CapabilitiesSection capabilities={capabilities} />
      <VideoCarouselSection videos={videos} />
      <InquirySection
        title={cta.title}
        contactUrl={contactUrl}
        contact={site.contact}
      />
    </>
  );
}
