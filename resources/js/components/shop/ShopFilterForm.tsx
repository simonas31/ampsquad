import { Link, router } from "@inertiajs/react";
import { Search } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useT } from "@/hooks/use-t";
import { hasActiveFilters, shopUrl } from "@/lib/shop";
import type { ManufacturerOption, ShopFilters, ShopSort } from "@/types";

interface ShopFilterFormProps {
  filters: ShopFilters;
  manufacturers: ManufacturerOption[];
  /** The shop listing's own URL, without a query string. */
  baseUrl: string;
}

const sorts: ShopSort[] = [
  "availability",
  "price_asc",
  "price_desc",
  "name",
  "newest",
];

/**
 * Search, manufacturer, stock, price range and sort as one GET form. Choices
 * that are a single click (the selects and the stock box) apply at once;
 * the typed ones wait for Enter or the button. The form is built from the
 * filters the server applied, and remounted whenever they change, so it
 * always shows what the page is actually showing. The chosen category lives
 * in the category list, not here, and is carried over untouched.
 */
export function ShopFilterForm({
  filters,
  manufacturers,
  baseUrl,
}: ShopFilterFormProps) {
  const t = useT();

  function submit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault();

    const data = new FormData(event.currentTarget);
    const manufacturer = Number(data.get("manufacturer"));

    router.get(
      shopUrl(baseUrl, {
        search: String(data.get("q") ?? ""),
        category: filters.category,
        manufacturer: manufacturer > 0 ? manufacturer : null,
        inStock: data.get("in_stock") === "1",
        minPrice: String(data.get("min_price") ?? ""),
        maxPrice: String(data.get("max_price") ?? ""),
        sort: String(data.get("sort")) as ShopSort,
      }),
    );
  }

  const applyNow = (
    event: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>,
  ) => event.currentTarget.form?.requestSubmit();

  return (
    <form
      key={shopUrl(baseUrl, filters)}
      onSubmit={submit}
      role="search"
      aria-label={t("shop.filters.title")}
      className="space-y-6"
    >
      <Field id="shop-search" label={t("shop.filters.search")}>
        {(control) => (
          <Input
            {...control}
            type="search"
            name="q"
            defaultValue={filters.search}
            placeholder={t("shop.filters.searchPlaceholder")}
            maxLength={100}
          />
        )}
      </Field>

      <Field id="shop-manufacturer" label={t("shop.filters.manufacturer")}>
        {(control) => (
          <Select
            {...control}
            name="manufacturer"
            defaultValue={filters.manufacturer ?? ""}
            onChange={applyNow}
          >
            <option value="">{t("shop.filters.allManufacturers")}</option>
            {manufacturers.map((manufacturer) => (
              <option key={manufacturer.id} value={manufacturer.id}>
                {manufacturer.name} ({manufacturer.count})
              </option>
            ))}
          </Select>
        )}
      </Field>

      <fieldset>
        <legend className="meta text-ink-soft mb-2">
          {t("shop.filters.price")}
        </legend>
        <div className="grid grid-cols-2 gap-3">
          <Input
            type="number"
            name="min_price"
            defaultValue={filters.minPrice}
            min={0}
            step="0.01"
            inputMode="decimal"
            aria-label={t("shop.filters.minPrice")}
            placeholder={t("shop.filters.minPrice")}
          />
          <Input
            type="number"
            name="max_price"
            defaultValue={filters.maxPrice}
            min={0}
            step="0.01"
            inputMode="decimal"
            aria-label={t("shop.filters.maxPrice")}
            placeholder={t("shop.filters.maxPrice")}
          />
        </div>
      </fieldset>

      <label className="text-ink flex min-h-11 cursor-pointer items-center gap-3">
        <input
          type="checkbox"
          name="in_stock"
          value="1"
          defaultChecked={filters.inStock}
          onChange={applyNow}
          className="accent-ink size-5 shrink-0"
        />
        {t("shop.filters.inStockOnly")}
      </label>

      <Field id="shop-sort" label={t("shop.filters.sort")}>
        {(control) => (
          <Select
            {...control}
            name="sort"
            defaultValue={filters.sort}
            onChange={applyNow}
          >
            {sorts.map((sort) => (
              <option key={sort} value={sort}>
                {t(`shop.sort.${sort}`)}
              </option>
            ))}
          </Select>
        )}
      </Field>

      <div className="flex flex-wrap items-center gap-3">
        <Button type="submit">
          <Search aria-hidden="true" />
          {t("shop.filters.apply")}
        </Button>

        {hasActiveFilters(filters) && (
          <Button asChild variant="link">
            <Link href={baseUrl}>{t("shop.clearFilters")}</Link>
          </Button>
        )}
      </div>
    </form>
  );
}
