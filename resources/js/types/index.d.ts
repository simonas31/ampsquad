export type Locale = "lt" | "en";

export interface LocaleOption {
  code: Locale;
  name: string;
  url: string;
}

export interface SeoData {
  title: string;
  description: string | null;
  canonical: string;
  ogImage: string | null;
  ogType: string;
  noindex: boolean;
  alternates: Array<{ locale: string; url: string }>;
  jsonLd: Record<string, unknown>[];
}

export interface Breadcrumb {
  label: string;
  url: string | null;
}

export interface NavigationLink {
  labelKey: "nav.home" | "nav.projects" | "nav.about" | "nav.contact";
  url: string;
}

export interface SiteData {
  contact: {
    email: string;
    phone: string;
    address: string;
  };
  social: {
    facebook: string | null;
    instagram: string | null;
    linkedin: string | null;
  };
}

export interface SharedData {
  locale: {
    current: Locale;
    available: LocaleOption[];
  };
  navigation: NavigationLink[];
  site: SiteData;
}

export interface Category {
  id: number;
  name: string;
  slug: string;
}

export interface Tag {
  id: number;
  name: string;
  slug: string;
}

export interface GalleryImage {
  large: string;
  thumbnail: string;
}

export interface HeadingBlock {
  type: "heading";
  data: { text: string; level: "h2" | "h3" | "h4" };
}

export interface RichTextBlock {
  type: "rich_text";
  data: { content: string };
}

export interface ImageBlock {
  type: "image";
  data: { image: string; caption: string | null };
}

export interface GalleryBlock {
  type: "gallery";
  data: { images: string[] };
}

export interface VideoEmbedBlock {
  type: "video_embed";
  data: { url: string };
}

export interface QuoteBlock {
  type: "quote";
  data: { text: string; author: string | null };
}

export interface CtaBlock {
  type: "cta";
  data: { label: string; url: string };
}

export type ContentBlock =
  | HeadingBlock
  | RichTextBlock
  | ImageBlock
  | GalleryBlock
  | VideoEmbedBlock
  | QuoteBlock
  | CtaBlock;

export interface Project {
  id: number;
  title: string;
  excerpt: string | null;
  status: "draft" | "published";
  publishedAt: string | null;
  isFeatured: boolean;
  location: string | null;
  clientName: string | null;
  completedAt: string | null;
  url: string;
  category: Category;
  tags: Tag[];
  author: string | null;
  featuredImageUrl: string | null;
  featuredImageThumbUrl: string | null;
  gallery: GalleryImage[];
  blocks?: ContentBlock[];
}

export interface Video {
  id: number;
  title: string | null;
  type: "instagram_embed" | "upload";
  embedUrl: string | null;
  videoUrl: string | null;
  posterUrl: string | null;
}

export interface CalculatorCategoryTeaser {
  id: number;
  name: string;
  icon: string | null;
  options: string[];
}

export interface Page {
  id: number;
  key: string;
  title: string;
  blocks: ContentBlock[];
  featuredImageUrl: string | null;
}

export interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

/**
 * Shape of a paginated Eloquent API resource collection: `links` holds the
 * first/last/prev/next URLs, while the numbered page links (with `active`)
 * live under `meta.links`.
 */
export interface Paginated<T> {
  data: T[];
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
  meta: {
    current_page: number;
    last_page: number;
    total: number;
    links: PaginationLink[];
  };
}

declare module "@inertiajs/core" {
  export interface InertiaConfig {
    sharedPageProps: SharedData;
    flashDataType: {
      contactSubmitted?: boolean;
    };
  }
}
