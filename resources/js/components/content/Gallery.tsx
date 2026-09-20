import { useState } from "react";
import { Lightbox } from "./Lightbox";

export interface GalleryItem {
  thumbnail: string;
  full: string;
  alt: string;
}

/** Thumbnail grid that opens each image full-size in a lightbox. */
export function Gallery({ items }: { items: GalleryItem[] }) {
  const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);

  return (
    <>
      <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
        {items.map((item, index) => (
          <li key={item.full}>
            <button
              type="button"
              onClick={() => setLightboxIndex(index)}
              className="block w-full overflow-hidden rounded-md"
            >
              <img
                src={item.thumbnail}
                alt={item.alt}
                loading="lazy"
                className="aspect-square w-full object-cover transition-transform duration-300 hover:scale-105"
              />
            </button>
          </li>
        ))}
      </ul>

      <Lightbox
        images={items.map((item) => item.full)}
        index={lightboxIndex}
        onIndexChange={setLightboxIndex}
      />
    </>
  );
}
