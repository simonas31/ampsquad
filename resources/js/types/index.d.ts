export interface LocaleOption {
    code: string;
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
    labelKey: string;
    url: string;
}

export interface SharedData {
    locale: {
        current: string;
        available: LocaleOption[];
    };
    navigation: NavigationLink[];
    [key: string]: unknown;
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
    type: 'heading';
    data: { text: string; level: 'h2' | 'h3' | 'h4' };
}

export interface RichTextBlock {
    type: 'rich_text';
    data: { content: string };
}

export interface ImageBlock {
    type: 'image';
    data: { image: string; caption: string | null };
}

export interface GalleryBlock {
    type: 'gallery';
    data: { images: string[] };
}

export interface VideoEmbedBlock {
    type: 'video_embed';
    data: { url: string };
}

export interface QuoteBlock {
    type: 'quote';
    data: { text: string; author: string | null };
}

export interface CtaBlock {
    type: 'cta';
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
    status: 'draft' | 'published';
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

export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
    };
}

declare module "@inertiajs/core" {
    export interface InertiaConfig {
        flashDataType: {
            contactSubmitted?: boolean;
        };
    }
}
