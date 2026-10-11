import { describe, expect, it } from "vitest";
import { toEmbedSource } from "./video-embed";

const YOUTUBE = "https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ";

describe("toEmbedSource", () => {
  it.each([
    "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
    "https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=42s",
    "https://m.youtube.com/watch?v=dQw4w9WgXcQ",
    "https://youtu.be/dQw4w9WgXcQ?si=abc",
    "https://www.youtube.com/embed/dQw4w9WgXcQ",
    "https://www.youtube.com/live/dQw4w9WgXcQ",
  ])("embeds the landscape YouTube url %s", (url) => {
    expect(toEmbedSource(url)).toEqual({ kind: "iframe", src: YOUTUBE, portrait: false });
  });

  it("embeds YouTube Shorts in a portrait frame", () => {
    expect(
      toEmbedSource("https://www.youtube.com/shorts/dQw4w9WgXcQ"),
    ).toEqual({ kind: "iframe", src: YOUTUBE, portrait: true });
  });

  it("embeds Vimeo urls, with or without the player path", () => {
    const expected = {
      kind: "iframe",
      src: "https://player.vimeo.com/video/123456789",
      portrait: false,
    };

    expect(toEmbedSource("https://vimeo.com/123456789")).toEqual(expected);
    expect(toEmbedSource("https://player.vimeo.com/video/123456789")).toEqual(
      expected,
    );
  });

  it("passes other urls through unchanged", () => {
    expect(toEmbedSource("https://example.com/player/1")).toEqual({
      kind: "iframe",
      src: "https://example.com/player/1",
      portrait: false,
    });
  });

  it.each([
    ["https://www.instagram.com/reel/C8abc_DEF-1/?igsh=xyz", "reel"],
    ["https://instagram.com/reels/C8abc_DEF-1/", "reel"],
    ["https://www.instagram.com/p/C8abc_DEF-1/", "p"],
    ["https://www.instagram.com/someuser/reel/C8abc_DEF-1/", "reel"],
    ["https://www.instagram.com/reel/C8abc_DEF-1/embed", "reel"],
  ])("routes the Instagram url %s through embed.js", (url, kind) => {
    expect(toEmbedSource(url)).toEqual({
      kind: "instagram",
      permalink: `https://www.instagram.com/${kind}/C8abc_DEF-1/`,
    });
  });
});
