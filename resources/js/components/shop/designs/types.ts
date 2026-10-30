import type {
  Breadcrumb,
  CategoryNode,
  ManufacturerOption,
  Paginated,
  Product,
  ShopFilters,
} from "@/types";

/** What the shop listing page receives; every layout of it takes the same. */
export interface ShopListingProps {
  products: Paginated<Product>;
  filters: ShopFilters;
  /** Opened out only along the chosen category, for the sidebar list. */
  categories: CategoryNode[];
  manufacturers: ManufacturerOption[];
  /** The listing's own URL without a query string; filter links are built from it. */
  shopUrl: string;
  breadcrumbs: Breadcrumb[];
  /** The popular shelf; null unless this is the plain first page. */
  popularProducts: Product[] | null;
  /** Every category opened out, for the menu; null unless this is the plain first page. */
  categoryMenu: CategoryNode[] | null;
}
