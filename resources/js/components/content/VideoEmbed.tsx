import { useT } from "@/hooks/use-t";

function toEmbedUrl(url: string): string {
  const youtube = url.match(
    /(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/))([\w-]+)/,
  );
  if (youtube) {
    return `https://www.youtube-nocookie.com/embed/${youtube[1]}`;
  }

  const vimeo = url.match(/vimeo\.com\/(\d+)/);
  if (vimeo) {
    return `https://player.vimeo.com/video/${vimeo[1]}`;
  }

  return url;
}

export function VideoEmbed({ url }: { url: string }) {
  const t = useT();

  return (
    <div className="aspect-video overflow-hidden bg-ink">
      <iframe
        src={toEmbedUrl(url)}
        title={t("content.embeddedVideo")}
        className="h-full w-full"
        loading="lazy"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
        allowFullScreen
      />
    </div>
  );
}
