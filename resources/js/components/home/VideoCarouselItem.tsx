import { InstagramEmbed } from "@/components/content/InstagramEmbed";
import type { Video } from "@/types";

export function VideoCarouselItem({ video }: { video: Video }) {
  return (
    <div>
      <div className="bg-concrete relative aspect-9/16 overflow-hidden rounded-2xl">
        {video.type === "instagram_embed" && video.embedUrl ? (
          <InstagramEmbed embedUrl={video.embedUrl} fill />
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
        <p className="meta text-ink-soft mt-4">{video.title}</p>
      )}
    </div>
  );
}
