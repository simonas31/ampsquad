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
      <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
        {items.map((item, index) => (
          <li key={item.full}>
            <button
              type="button"
              onClick={() => setLightboxIndex(index)}
              className="group block w-full overflow-hidden"
            >
              <img
                src={item.thumbnail}
                alt={item.alt}
                loading="lazy"
                className="aspect-square w-full object-cover transition-transform duration-500 group-hover:scale-105"
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
