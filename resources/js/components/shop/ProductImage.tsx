import { Package } from "lucide-react";
import { Figure } from "@/components/common/Figure";

interface ProductImageProps {
  imageUrl: string | null;
  /** Describes the product. Empty string where the name sits right beside it. */
  alt: string;
  ratio?: string;
  priority?: boolean;
  zoom?: boolean;
  sizes?: string;
  className?: string;
}

/**
 * A product photograph in the site's image frame. Products without an
 * uploaded image get a quiet icon on the same bed, so the grid keeps its
 * rhythm instead of collapsing.
 */
export function ProductImage({
  imageUrl,
  alt,
  ratio,
  priority,
  zoom,
  sizes,
  className,
}: ProductImageProps) {
  return (
    <Figure
      frame={
        imageUrl
          ? { src: imageUrl, width: 1200, height: 900, isPlaceholder: false }
          : null
      }
      alt={alt}
      ratio={ratio}
      priority={priority}
      zoom={zoom}
      sizes={sizes}
      className={className}
      fallback={
        <div className="text-ink-soft/40 flex h-full w-full items-center justify-center">
          <Package className="size-1/4 max-h-12 min-h-5" aria-hidden="true" />
        </div>
      }
    />
  );
}
