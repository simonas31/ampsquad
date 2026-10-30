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

export interface HeroStat {
  value: number;
  unit: string;
  label: string;
}

export interface Breadcrumb {
  label: string;
  url: string | null;
}

export interface NavigationLink {
  labelKey:
    | "nav.home"
    | "nav.about"
    | "nav.projects"
    | "nav.priceCalculator"
    | "nav.shop"
    | "nav.contact";
  url: string;
}

/** A page the admin flagged to appear in the header or footer menu. */
export interface PageLink {
  title: string;
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
  /** Uploaded logo URLs; null means use the bundled artwork. */
  logo: {
    light: string | null;
    dark: string | null;
  };
}

/**
 * What the header's cart link needs; null while the shop is switched off.
 * Shared as `cartSummary`: the cart page's own `cart` prop (CartData) is a
 * different thing and would replace a shared prop of that name.
 */
export interface CartSummary {
  /** The cart page. */
  url: string;
  /** POST here to add a product. */
  itemsUrl: string;
  /** Units that can still be bought. */
  count: number;
}

/**
 * Who is signed in as a customer (nobody, usually) and where the account
 * pages are. The URLs are relative, like the cart's.
 */
export interface AccountSummary {
  customer: {
    fullName: string;
    email: string;
    /** An unverified customer is kept out of the account area. */
    isVerified: boolean;
  } | null;
  loginUrl: string;
  registerUrl: string;
  dashboardUrl: string;
  /** POST here to sign out. */
  logoutUrl: string;
}

export interface SharedData {
  locale: {
    current: Locale;
    available: LocaleOption[];
  };
  navigation: NavigationLink[];
  pageLinks: {
    header: PageLink[];
    footer: PageLink[];
  };
  site: SiteData;
  cartSummary: CartSummary | null;
  account: AccountSummary;
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

/**
 * Prices are whole cents; format them with formatCents(). Each comes without
 * and with VAT; show the one with VAT first.
 */
export interface Product {
  id: number;
  name: string;
  excerpt: string | null;
  /** Only sent to the product page. */
  description?: string | null;
  priceExclVatCents: number;
  priceInclVatCents: number;
  url: string;
  /** An uploaded photograph, or the supplier's image link; null when neither exists. */
  imageUrl: string | null;
  imageThumbUrl: string | null;
  sku: string | null;
  /** Only sent where the manufacturer was loaded: the listing and the product page. */
  manufacturer?: string | null;
  /** Only sent where the category was loaded: the listing and the product page. */
  category?: { name: string; url: string } | null;
  /** False once the stock is used up; the product stays visible but cannot be bought. */
  inStock: boolean;
  /** How many are left when only a few are; null for a well-stocked product. */
  lowStockCount: number | null;
  /** The most one cart line may hold: the cart cap, or the stock when lower. */
  maxQuantity: number;
}

export type ShopSort =
  | "availability"
  | "price_asc"
  | "price_desc"
  | "name"
  | "newest";

/** What the shop listing is currently narrowed to, as the server understood it. */
export interface ShopFilters {
  search: string;
  /** Slug of the chosen category; null for all. */
  category: string | null;
  manufacturer: number | null;
  inStock: boolean;
  /** Euros with VAT as typed; empty for no limit. */
  minPrice: string;
  maxPrice: string;
  sort: ShopSort;
}

export interface CategoryNode {
  id: number;
  name: string;
  slug: string;
  /** Products in it and everything below it, under the current filters. */
  count: number;
  isSelected: boolean;
  /** Filled only for the chosen category and its ancestors. */
  children: CategoryNode[];
}

export interface ManufacturerOption {
  id: number;
  name: string;
  count: number;
}

/**
 * `reduced`: less is in stock than the line asked for, so only what is left
 * counts. `unavailable`: the product was deactivated, deleted or sold out,
 * and nothing counts.
 */
export type CartLineStatus = "available" | "reduced" | "unavailable";

export interface CartLine {
  id: number;
  status: CartLineStatus;
  /** The units that count; 0 while unavailable. */
  quantity: number;
  /** What the visitor asked for; more than `quantity` on a reduced line. */
  requestedQuantity: number;
  maxQuantity: number;
  unitPriceExclVatCents: number;
  unitPriceInclVatCents: number;
  lineTotalExclVatCents: number;
  lineTotalInclVatCents: number;
  /** PATCH to set the quantity, DELETE to remove the line. */
  url: string;
  product: {
    id: number;
    name: string;
    /** Null when the product can no longer be viewed. */
    url: string | null;
    imageThumbUrl: string | null;
  };
}

export interface CartData {
  items: CartLine[];
  /** Counts what can be bought (see CartLineStatus). */
  subtotalExclVatCents: number;
  /** The VAT rate in whole percent. */
  vatRate: number;
  /** The VAT in the total: the total incl. VAT minus the subtotal excl. VAT. */
  vatCents: number;
  totalInclVatCents: number;
  totalQuantity: number;
  hasReducedItems: boolean;
  hasUnavailableItems: boolean;
  /** DELETE here empties the cart. */
  clearUrl: string;
  /** The way into checkout; null while online checkout is switched off. */
  checkoutUrl: string | null;
}

/**
 * A way to get the order, with what the whole order would cost if chosen.
 * The server worked all of it out, including the fingerprint that signs what
 * the visitor was shown; the form only sends the fingerprint back.
 */
export interface ShippingMethodOption {
  id: number;
  name: string;
  description: string | null;
  /** Collected at the shop: no address is asked for. */
  isPickup: boolean;
  priceExclVatCents: number;
  priceInclVatCents: number;
  /** The VAT in the whole order with this delivery. */
  vatCents: number;
  totalInclVatCents: number;
  fingerprint: string;
}

export type OrderStatus =
  | "pending_payment"
  | "paid"
  | "shipped"
  | "cancelled"
  | "expired"
  | "refunded";

export interface OrderLineData {
  name: string;
  sku: string | null;
  /** The product's photograph as it is now; null when it has none. */
  imageThumbUrl: string | null;
  quantity: number;
  unitPriceInclVatCents: number;
  lineTotalInclVatCents: number;
}

/** An order as its own page shows it: no contact details, no address. */
export interface OrderData {
  number: string;
  status: OrderStatus;
  placedAt: string;
  /** When the time to pay runs out (ISO 8601). */
  paymentExpiresAt: string;
  /** Where the customer can still pay; null once they cannot. */
  payUrl: string | null;
  lines: OrderLineData[];
  shipping: {
    label: string;
    details: string | null;
    isPickup: boolean;
    priceInclVatCents: number;
  };
  subtotalExclVatCents: number;
  shippingExclVatCents: number;
  vatRate: number;
  vatCents: number;
  totalInclVatCents: number;
}

export interface Video {
  id: number;
  title: string | null;
  type: "instagram_embed" | "upload";
  embedUrl: string | null;
  videoUrl: string | null;
  posterUrl: string | null;
}

export interface CapabilityTeaser {
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
      accountStatus?:
        | "verification-link-sent"
        | "email-verified"
        | "reset-link-sent"
        | "password-reset"
        | "password-changed"
        | "email-change-requested"
        | "email-changed"
        | "email-change-failed";
    };
  }
}
