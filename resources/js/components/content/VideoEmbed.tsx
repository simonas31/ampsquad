import { InstagramEmbed } from "@/components/content/InstagramEmbed";
import { useT } from "@/hooks/use-t";
import { cn } from "@/lib/utils";
import { toEmbedSource } from "@/lib/video-embed";

export function VideoEmbed({ url }: { url: string }) {
  const t = useT();
  const source = toEmbedSource(url);

  if (source.kind === "instagram") {
    // Instagram sizes its own post, so the slot is a centered column with
    // a floor height for the loading skeleton instead of a fixed ratio.
    return (
      <div className="relative mx-auto min-h-96 w-full max-w-lg">
        <InstagramEmbed embedUrl={source.permalink} />
      </div>
    );
  }

  return (
    <div
      className={cn(
        "bg-ink relative w-full overflow-hidden rounded-2xl",
        source.portrait ? "mx-auto aspect-[9/16] max-w-sm" : "aspect-video",
      )}
    >
      <iframe
        src={source.src}
        title={t("content.embeddedVideo")}
        className="absolute inset-0 h-full w-full border-0"
        loading="lazy"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
        allowFullScreen
      />
    </div>
  );
}
