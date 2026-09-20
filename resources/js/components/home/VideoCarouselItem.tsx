import { useEffect, useRef, useState } from "react";
import { Skeleton } from "@/components/ui/skeleton";
import { processInstagramEmbeds } from "@/lib/instagram";
import type { Video } from "@/types";

function InstagramEmbed({ embedUrl }: { embedUrl: string }) {
  const containerRef = useRef<HTMLDivElement>(null);
  // Instagram's embed.js swaps the blockquote for its own iframe
  // asynchronously, so the slide is blank until that happens — show a
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
      {isLoading && <Skeleton className="absolute inset-0 rounded-none" />}
      <div ref={containerRef} className="h-full w-full" />
    </>
  );
}

export function VideoCarouselItem({ video }: { video: Video }) {
  return (
    <div>
      <div className="bg-secondary relative aspect-9/16 overflow-hidden rounded-lg">
        {video.type === "instagram_embed" && video.embedUrl ? (
          <InstagramEmbed embedUrl={video.embedUrl} />
        ) : (
          video.videoUrl && (
            <video
              src={video.videoUrl}
              poster={video.posterUrl ?? undefined}
              controls
              preload="none"
              className="h-full w-full object-cover"
            />
          )
        )}
      </div>
      {video.title && (
        <p className="text-muted-foreground mt-3 text-sm">{video.title}</p>
      )}
    </div>
  );
}
