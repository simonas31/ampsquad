import { useEffect, useRef, useState } from "react";
import { Skeleton } from "@/components/ui/skeleton";
import { processInstagramEmbeds } from "@/lib/instagram";
import { cn } from "@/lib/utils";

interface InstagramEmbedProps {
  embedUrl: string;
  /**
   * Stretch the post to the parent's height as well as its width. Needs a
   * parent with a definite height; otherwise the post keeps the height
   * Instagram measures for it.
   */
  fill?: boolean;
}

/**
 * Renders inside a `relative` parent: the skeleton covers it until
 * Instagram's embed.js has swapped in the real post.
 */
export function InstagramEmbed({
  embedUrl,
  fill = false,
}: InstagramEmbedProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  // Instagram's embed.js swaps the blockquote for its own iframe
  // asynchronously, so the slot is blank until that happens — show a
  // skeleton until it does.
  const [isLoading, setIsLoading] = useState(true);

  // The blockquote is created imperatively rather than rendered by React:
  // embed.js replaces it with an iframe, and React must not own a node
  // that gets swapped out from under it.
  useEffect(() => {
    const container = containerRef.current;

    if (!container) {
      return;
    }

    const quote = document.createElement("blockquote");
    quote.className = "instagram-media";
    quote.dataset.instgrmPermalink = embedUrl;
    quote.dataset.instgrmVersion = "14";
    container.replaceChildren(quote);

    let active = true;
    void processInstagramEmbeds().then(() => {
      if (active) {
        setIsLoading(false);
      }
    });

    return () => {
      active = false;
      container.replaceChildren();
    };
  }, [embedUrl]);

  return (
    <>
      {isLoading && <Skeleton className="absolute inset-0" />}
      <div
        ref={containerRef}
        className={cn(
          "instagram-slot w-full",
          fill && "instagram-slot--fill h-full",
        )}
      />
    </>
  );
}
