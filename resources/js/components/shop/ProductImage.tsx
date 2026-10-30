import { Package } from "lucide-react";
import { useState } from "react";
import { Figure } from "@/components/common/Figure";

interface ProductImageProps {
  imageUrl: string | null;
  /** Describes the product. Empty string where the name sits right beside it. */
  alt: string;
  ratio?: string;
  priority?: boolean;
  zoom?: boolean;
  fit?: "cover" | "contain";
  sizes?: string;
  className?: string;
}

/**
 * A product photograph in the site's image frame. Products without an
 * image, and products whose image cannot be loaded (catalog products link
 * to the supplier's site, which can move or drop a file), get a quiet icon
 * on the same bed, so the grid keeps its rhythm instead of collapsing.
 */
export function ProductImage({
  imageUrl,
  alt,
  ratio,
  priority,
  zoom,
  fit,
  sizes,
  className,
}: ProductImageProps) {
  // Remembers which URL failed rather than a flag, so a different image
  // (the same card reused for another product) gets its own chance to load.
  const [failedUrl, setFailedUrl] = useState<string | null>(null);
  const src = imageUrl !== null && imageUrl !== failedUrl ? imageUrl : null;

  return (
    <Figure
      frame={
        src ? { src, width: 1200, height: 900, isPlaceholder: false } : null
      }
      alt={alt}
      ratio={ratio}
      priority={priority}
      zoom={zoom}
      fit={fit}
      sizes={sizes}
      className={className}
      onError={() => setFailedUrl(imageUrl)}
      // Catalog photographs are hotlinked from the supplier's site, which may
      // refuse requests that name another site as the referrer, and has no
      // business learning which of our pages its images are shown on.
      referrerPolicy="no-referrer"
      fallback={
        <div className="text-ink-soft/40 flex h-full w-full items-center justify-center">
          <Package className="size-1/4 max-h-12 min-h-5" aria-hidden="true" />
        </div>
      }
    />
  );
}
