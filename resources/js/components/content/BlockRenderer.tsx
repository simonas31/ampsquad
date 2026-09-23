import { isExternalUrl } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import type { ContentBlock } from "@/types";
import { Gallery } from "./Gallery";
import { VideoEmbed } from "./VideoEmbed";

function Block({ block }: { block: ContentBlock }) {
  switch (block.type) {
    case "heading": {
      const Heading = block.data.level;
      const sizes = {
        h2: "text-2xl sm:text-3xl",
        h3: "text-xl sm:text-2xl",
        h4: "text-lg sm:text-xl",
      };

      return (
        <Heading className={`display text-ink ${sizes[Heading]}`}>
          {block.data.text}
        </Heading>
      );
    }

    case "rich_text":
      // Authored by admins in the Filament rich editor — trusted HTML.
      return (
        <div
          className="prose prose-brand prose-lg max-w-none"
          dangerouslySetInnerHTML={{ __html: block.data.content }}
        />
      );

    case "image":
      return (
        <figure>
          <img
            src={`/storage/${block.data.image}`}
            alt={block.data.caption ?? ""}
            loading="lazy"
            className="w-full"
          />
          {block.data.caption && (
            <figcaption className="meta text-ink-soft mt-3">
              {block.data.caption}
            </figcaption>
          )}
        </figure>
      );

    case "gallery":
      return (
        <Gallery
          items={block.data.images.map((image) => ({
            thumbnail: `/storage/${image}`,
            full: `/storage/${image}`,
            alt: "",
          }))}
        />
      );

    case "video_embed":
      return <VideoEmbed url={block.data.url} />;

    case "quote":
      return (
        <blockquote className="border-signal space-y-3 border-l-2 py-1 pl-6">
          <p className="text-ink text-xl sm:text-2xl">
            &ldquo;{block.data.text}&rdquo;
          </p>
          {block.data.author && (
            <footer className="meta text-ink-soft">{block.data.author}</footer>
          )}
        </blockquote>
      );

    case "cta":
      return (
        <div className="on-ink bg-ink px-6 py-12 sm:px-10">
          <Button asChild variant="accent" size="lg">
            {isExternalUrl(block.data.url) ? (
              <a href={block.data.url} rel="noopener noreferrer">
                {block.data.label}
              </a>
            ) : (
              <Link href={block.data.url}>{block.data.label}</Link>
            )}
          </Button>
        </div>
      );

    default:
      return null;
  }
}

export function BlockRenderer({ blocks }: { blocks: ContentBlock[] }) {
  return (
    <div className="space-y-8">
      {blocks.map((block, index) => (
        <Block key={index} block={block} />
      ))}
    </div>
  );
}
