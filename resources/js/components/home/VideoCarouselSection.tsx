import Autoplay from "embla-carousel-autoplay";
import useEmblaCarousel from "embla-carousel-react";
import { ChevronLeft, ChevronRight, Pause, Play } from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { Reveal } from "@/components/common/Reveal";
import { SectionHeading } from "@/components/common/SectionHeading";
import { Container } from "@/components/layout/Container";
import { Button } from "@/components/ui/button";
import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";
import type { Video } from "@/types";
import { VideoCarouselItem } from "./VideoCarouselItem";

const prefersReducedMotion = () =>
  window.matchMedia("(prefers-reduced-motion: reduce)").matches;

export function VideoCarouselSection({ videos }: { videos: Video[] }) {
  const t = useT();
  const [autoplay] = useState(() =>
    prefersReducedMotion()
      ? null
      : Autoplay({
          delay: 4500,
          stopOnInteraction: false,
          stopOnMouseEnter: true,
        }),
  );
  const [viewportRef, api] = useEmblaCarousel(
    { loop: true },
    autoplay ? [autoplay] : [],
  );
  const [selectedIndex, setSelectedIndex] = useState(0);
  const [slideCount, setSlideCount] = useState(0);
  const [isPlaying, setIsPlaying] = useState(autoplay !== null);

  useEffect(() => {
    if (!api) {
      return;
    }

    const sync = () => {
      setSlideCount(api.scrollSnapList().length);
      setSelectedIndex(api.selectedScrollSnap());
    };
    const onPlay = () => setIsPlaying(true);
    const onStop = () => setIsPlaying(false);

    sync();
    api.on("select", sync).on("reInit", sync);
    api.on("autoplay:play", onPlay).on("autoplay:stop", onStop);

    return () => {
      api.off("select", sync).off("reInit", sync);
      api.off("autoplay:play", onPlay).off("autoplay:stop", onStop);
    };
  }, [api]);

  // Autoplaying content must be pausable (WCAG 2.2.2) — in addition to
  // pausing on hover/focus, this gives an explicit control.
  const toggleAutoplay = useCallback(() => {
    if (!autoplay) {
      return;
    }

    if (autoplay.isPlaying()) {
      autoplay.stop();
    } else {
      autoplay.play();
    }
  }, [autoplay]);

  if (videos.length === 0) {
    return null;
  }

  return (
    <section
      className="py-16 sm:py-24"
      aria-roledescription="carousel"
      aria-label={t("home.videos.title")}
    >
      <Container>
        <Reveal>
          <SectionHeading
            title={t("home.videos.title")}
            action={
              <div className="flex items-center gap-2">
                {autoplay && (
                  <Button
                    variant="outline"
                    size="icon"
                    onClick={toggleAutoplay}
                    aria-label={
                      isPlaying
                        ? t("common.pauseSlideshow")
                        : t("common.playSlideshow")
                    }
                  >
                    {isPlaying ? (
                      <Pause aria-hidden="true" />
                    ) : (
                      <Play aria-hidden="true" />
                    )}
                  </Button>
                )}
                <Button
                  variant="outline"
                  size="icon"
                  onClick={() => api?.scrollPrev()}
                  aria-label={t("common.previousSlide")}
                >
                  <ChevronLeft aria-hidden="true" />
                </Button>
                <Button
                  variant="outline"
                  size="icon"
                  onClick={() => api?.scrollNext()}
                  aria-label={t("common.nextSlide")}
                >
                  <ChevronRight aria-hidden="true" />
                </Button>
              </div>
            }
          />
        </Reveal>

        <Reveal delay={100} className="mt-10">
          <div ref={viewportRef} className="overflow-hidden">
            <div className="-ml-4 flex">
              {videos.map((video, index) => (
                <div
                  key={video.id}
                  role="group"
                  aria-roledescription="slide"
                  aria-label={t("common.slideOf", {
                    number: index + 1,
                    total: videos.length,
                  })}
                  className="min-w-0 shrink-0 grow-0 basis-full pl-4 md:basis-1/2 lg:basis-1/3"
                >
                  <VideoCarouselItem video={video} />
                </div>
              ))}
            </div>
          </div>

          {slideCount > 1 && (
            <div className="mt-6 flex items-center justify-center">
              {Array.from({ length: slideCount }, (_, index) => (
                <button
                  key={index}
                  type="button"
                  onClick={() => api?.scrollTo(index)}
                  aria-label={t("common.goToSlide", { number: index + 1 })}
                  aria-current={index === selectedIndex}
                  className="group flex size-6 items-center justify-center"
                >
                  <span
                    className={cn(
                      "block h-2 rounded-full transition-all",
                      index === selectedIndex
                        ? "bg-accent w-6"
                        : "bg-input group-hover:bg-muted-foreground w-2",
                    )}
                  />
                </button>
              ))}
            </div>
          )}
        </Reveal>
      </Container>
    </section>
  );
}
