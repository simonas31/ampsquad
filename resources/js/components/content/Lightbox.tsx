import { ChevronLeft, ChevronRight, X } from "lucide-react";
import { Dialog } from "radix-ui";
import type { KeyboardEvent } from "react";
import { Button } from "@/components/ui/button";
import { useT } from "@/hooks/use-t";

interface LightboxProps {
  images: string[];
  /** Index of the open image, or null when closed. */
  index: number | null;
  onIndexChange: (index: number | null) => void;
}

export function Lightbox({ images, index, onIndexChange }: LightboxProps) {
  const t = useT();
  const current = index !== null ? images[index] : null;

  function step(direction: 1 | -1) {
    if (index !== null) {
      onIndexChange((index + direction + images.length) % images.length);
    }
  }

  function onKeyDown(event: KeyboardEvent) {
    if (event.key === "ArrowRight") {
      step(1);
    } else if (event.key === "ArrowLeft") {
      step(-1);
    }
  }

  return (
    <Dialog.Root
      open={index !== null}
      onOpenChange={(open) => !open && onIndexChange(null)}
    >
      <Dialog.Portal>
        <Dialog.Overlay className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:fade-in-0 data-[state=closed]:fade-out-0 fixed inset-0 z-50 bg-black/85" />
        <Dialog.Content
          aria-describedby={undefined}
          onKeyDown={onKeyDown}
          className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:fade-in-0 data-[state=closed]:fade-out-0 fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-12"
        >
          <Dialog.Title className="sr-only">
            {t("content.imagePreview")}
          </Dialog.Title>

          {current && (
            <img
              src={current}
              alt={t("common.slideOf", {
                number: (index ?? 0) + 1,
                total: images.length,
              })}
              className="max-h-full max-w-full object-contain"
            />
          )}

          <Dialog.Close asChild>
            <Button
              variant="inverse"
              size="icon"
              className="absolute top-3 right-3 bg-black/50"
              aria-label={t("common.closeMenu")}
            >
              <X aria-hidden="true" />
            </Button>
          </Dialog.Close>

          {images.length > 1 && (
            <>
              <Button
                variant="inverse"
                size="icon"
                className="absolute top-1/2 left-3 -translate-y-1/2 bg-black/50"
                aria-label={t("content.previousImage")}
                onClick={() => step(-1)}
              >
                <ChevronLeft aria-hidden="true" />
              </Button>
              <Button
                variant="inverse"
                size="icon"
                className="absolute top-1/2 right-3 -translate-y-1/2 bg-black/50"
                aria-label={t("content.nextImage")}
                onClick={() => step(1)}
              >
                <ChevronRight aria-hidden="true" />
              </Button>
            </>
          )}
        </Dialog.Content>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
