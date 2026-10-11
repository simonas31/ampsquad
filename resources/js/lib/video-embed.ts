export type EmbedSource =
  | { kind: "iframe"; src: string; portrait: boolean }
  | { kind: "instagram"; permalink: string };

export function toEmbedSource(url: string): EmbedSource {
  // Instagram posts can't be framed directly; they have to go through
  // Instagram's embed.js, which sizes the post itself.
  const instagram = url.match(
    /instagram\.com\/(?:[\w.]+\/)?(p|reel|reels|tv)\/([\w-]+)/,
  );
  if (instagram) {
    const kind = instagram[1] === "reels" ? "reel" : instagram[1];

    return {
      kind: "instagram",
      permalink: `https://www.instagram.com/${kind}/${instagram[2]}/`,
    };
  }

  const youtube = url.match(
    /(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:embed\/|shorts\/|live\/|v\/|watch\?(?:[^#]*&)?v=))([\w-]+)/,
  );
  if (youtube) {
    return {
      kind: "iframe",
      src: `https://www.youtube-nocookie.com/embed/${youtube[1]}`,
      portrait: /youtube\.com\/shorts\//.test(url),
    };
  }

  const vimeo = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
  if (vimeo) {
    return {
      kind: "iframe",
      src: `https://player.vimeo.com/video/${vimeo[1]}`,
      portrait: false,
    };
  }

  return { kind: "iframe", src: url, portrait: false };
}
