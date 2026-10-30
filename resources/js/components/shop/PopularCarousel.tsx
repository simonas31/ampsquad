import useEmblaCarousel from "embla-carousel-react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { useEffect, useState } from "react";
import { CompactProductCard } from "@/components/shop/CompactProductCard";
import { Button } from "@/components/ui/button";
import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";
import type { Product } from "@/types";

interface PopularCarouselProps {
  products: Product[];
  /** How many cards fit across, as `basis-*` classes; the page decides from the room it has. */
  slideClassName: string;
}

/**
 * The shop's popular products as a row of cards that scrolls sideways, with
 * arrows and one dash per screenful. It does not move on its own, so there
 * is nothing to pause. Arrows that cannot go further switch off.
 */
export function PopularCarousel({
  products,
  slideClassName,
}: PopularCarouselProps) {
  const t = useT();
  const [viewportRef, api] = useEmblaCarousel({
    align: "start",
    slidesToScroll: "auto",
    containScroll: "trimSnaps",
  });
  const [selectedIndex, setSelectedIndex] = useState(0);
  const [snapCount, setSnapCount] = useState(0);
  const [canScrollPrev, setCanScrollPrev] = useState(false);
  const [canScrollNext, setCanScrollNext] = useState(false);

  useEffect(() => {
    if (!api) {
      return;
    }

    const sync = () => {
      setSnapCount(api.scrollSnapList().length);
      setSelectedIndex(api.selectedScrollSnap());
      setCanScrollPrev(api.canScrollPrev());
      setCanScrollNext(api.canScrollNext());
    };

    sync();
    api.on("select", sync).on("reInit", sync);

    return () => {
      api.off("select", sync).off("reInit", sync);
    };
  }, [api]);

  if (products.length === 0) {
    return null;
  }

  return (
    <section
      aria-roledescription="carousel"
      aria-labelledby="shop-popular-heading"
    >
      <div className="border-ink flex items-center justify-between gap-4 border-t pt-5">
        <h2
          id="shop-popular-heading"
          className="display text-ink text-2xl uppercase sm:text-3xl"
        >
          {t("shop.popular.title")}
        </h2>

        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="icon"
            className="size-10"
            onClick={() => api?.scrollPrev()}
            disabled={!canScrollPrev}
            aria-label={t("common.previousSlide")}
          >
            <ChevronLeft aria-hidden="true" />
          </Button>
          <Button
            variant="outline"
            size="icon"
            className="size-10"
            onClick={() => api?.scrollNext()}
            disabled={!canScrollNext}
            aria-label={t("common.nextSlide")}
          >
            <ChevronRight aria-hidden="true" />
          </Button>
        </div>
      </div>

      <div ref={viewportRef} className="mt-6 overflow-hidden">
        <div className="-ml-4 flex">
          {products.map((product, index) => (
            <div
              key={product.id}
              role="group"
              aria-roledescription="slide"
              aria-label={t("common.slideOf", {
                number: index + 1,
                total: products.length,
              })}
              className={cn("min-w-0 shrink-0 grow-0 pl-4", slideClassName)}
            >
              <CompactProductCard product={product} />
            </div>
          ))}
        </div>
      </div>

      {snapCount > 1 && (
        <div className="mt-6 flex justify-center">
          {Array.from({ length: snapCount }, (_, index) => (
            <button
              key={index}
              type="button"
              onClick={() => api?.scrollTo(index)}
              aria-label={t("common.slideOf", {
                number: index + 1,
                total: snapCount,
              })}
              aria-current={index === selectedIndex ? "true" : undefined}
              className="group flex h-6 w-10 items-center justify-center"
            >
              <span
                className={cn(
                  "rounded-full transition-all",
                  index === selectedIndex
                    ? "bg-signal h-1 w-10"
                    : "bg-ink-soft/40 group-hover:bg-ink-soft h-0.5 w-6",
                )}
              />
            </button>
          ))}
        </div>
      )}
    </section>
  );
}
