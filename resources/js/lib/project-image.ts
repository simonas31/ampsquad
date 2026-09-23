import type { Project } from "@/types";

/**
 * PLACEHOLDER PHOTOGRAPHY - REMOVE WHEN REAL IMAGES ARE UPLOADED.
 *
 * The layouts on this site are photography-led, but no project in the
 * database carries a featured image yet. Until they do, every empty slot
 * falls back to a seeded picsum.photos frame so the composition can be
 * judged at full strength.
 *
 * The fallback is deliberately confined to this one module. Uploading a
 * featured image in the admin panel makes that project use the real file
 * immediately, with no code change, so projects can be migrated one at a
 * time. Flipping USE_PLACEHOLDER_PHOTOGRAPHY to false makes every frame
 * without real media collapse instead of rendering, which is the honest
 * end state once the library is filled in.
 *
 * Note the asymmetry: project frames have a real source behind them (the
 * `featured_image` media collection), but `subjectFrame` does not. The
 * hero photograph and the capability photographs have no admin field at
 * all, so they stay placeholders until either a media collection is added
 * for them or those slots are removed from the design.
 */
export const USE_PLACEHOLDER_PHOTOGRAPHY = true;

/** Frame sizes, named after the composition each one serves. */
const FRAMES = {
  hero: [1800, 900],
  lead: [1440, 960],
  portrait: [960, 1200],
  square: [1100, 1100],
  row: [880, 495],
  strip: [1280, 720],
} as const;

export type FrameName = keyof typeof FRAMES;

export interface Frame {
  src: string;
  width: number;
  height: number;
  isPlaceholder: boolean;
}

/**
 * A stable seed per subject, so the same project keeps the same placeholder
 * frame across reloads and across page transitions instead of flickering to
 * a different photograph every render.
 */
function placeholder(seed: string, frame: FrameName): Frame {
  const [width, height] = FRAMES[frame];

  return {
    src: `https://picsum.photos/seed/${encodeURIComponent(seed)}/${width}/${height}`,
    width,
    height,
    isPlaceholder: true,
  };
}

function slugOf(project: Pick<Project, "id" | "url">): string {
  const segments = project.url.split("?")[0].split("/").filter(Boolean);

  return segments[segments.length - 1] ?? String(project.id);
}

/**
 * The frame for a project: its uploaded featured image when one exists,
 * otherwise a seeded placeholder, otherwise null so the caller can render
 * its typographic fallback.
 */
export function projectFrame(
  project: Pick<Project, "id" | "url" | "featuredImageUrl" | "featuredImageThumbUrl">,
  frame: FrameName,
  { thumbnail = false }: { thumbnail?: boolean } = {},
): Frame | null {
  const real = thumbnail
    ? (project.featuredImageThumbUrl ?? project.featuredImageUrl)
    : (project.featuredImageUrl ?? project.featuredImageThumbUrl);
  const [width, height] = FRAMES[frame];

  if (real) {
    return { src: real, width, height, isPlaceholder: false };
  }

  return USE_PLACEHOLDER_PHOTOGRAPHY
    ? placeholder(`ampsquad-project-${slugOf(project)}`, frame)
    : null;
}

/** The frame for a non-project subject (a capability, a page banner). */
export function subjectFrame(subject: string, frame: FrameName): Frame | null {
  return USE_PLACEHOLDER_PHOTOGRAPHY
    ? placeholder(`ampsquad-${subject}`, frame)
    : null;
}
