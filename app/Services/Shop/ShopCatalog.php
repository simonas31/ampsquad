<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\ShopSort;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Everything the shop listing shows: the page of products and the two
 * facets beside it (categories and manufacturers) with how many products
 * each would give.
 *
 * A facet counts the products that match every filter except its own, so
 * "Cudy (12)" really means twelve results after choosing it, and the other
 * options in the same facet stay visible instead of collapsing to the one
 * already picked.
 */
final class ShopCatalog
{
    public const int PER_PAGE = 12;

    /**
     * Products on the "Popular products" shelf.
     */
    public const int POPULAR_LIMIT = 12;

    /**
     * What a listing card shows. The long description stays out: every row
     * passes through the sort, and carrying five thousand characters per
     * product through it is what made a large catalog slow.
     *
     * @var list<string>
     */
    private const array LISTING_COLUMNS = [
        'id', 'name', 'slug', 'excerpt', 'sku', 'price_excl_vat_cents', 'stock_quantity',
        'image_url', 'product_category_id', 'manufacturer_id', 'is_active', 'deleted_at',
    ];

    /**
     * Resolved categories by slug, so one request looks each up once.
     *
     * @var array<string, array{category: ProductCategory, ids: list<int>}|null>
     */
    private array $categories = [];

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function products(ShopFilters $filters): LengthAwarePaginator
    {
        return $this->matching($filters)
            ->select(self::LISTING_COLUMNS)
            ->with(['media', 'category', 'manufacturer'])
            ->sortedBy($filters->sort)
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * The shelf at the top of the shop: the products the owner marked as
     * popular (those that can be bought first), or, while none is marked,
     * the newest products in stock so the shelf is never empty on a shop
     * that has not been curated yet.
     *
     * @return Collection<int, Product>
     */
    public function popularProducts(): Collection
    {
        $listing = fn (): Builder => Product::query()
            ->active()
            ->select(self::LISTING_COLUMNS)
            ->with(['media', 'category', 'manufacturer'])
            ->limit(self::POPULAR_LIMIT);

        $featured = $listing()->featured()->sortedBy(ShopSort::Availability)->get();

        return $featured->isNotEmpty()
            ? $featured
            : $listing()->inStock()->sortedBy(ShopSort::Newest)->get();
    }

    /**
     * The category the filter names, or null when it names none that exists
     * (an old link to a renamed category).
     */
    public function selectedCategory(ShopFilters $filters): ?ProductCategory
    {
        return $this->resolveCategory($filters)['category'] ?? null;
    }

    /**
     * Categories with products under the current filters, as a tree. Only
     * the top level and the branch leading to the chosen category are opened
     * out; the rest is one click away, unless $openAll opens every branch.
     *
     * @return list<array{id: int, name: string, slug: string, count: int, isSelected: bool, children: list<mixed>}>
     */
    public function categoryTree(ShopFilters $filters, bool $openAll = false): array
    {
        $counts = $this->countsBy($this->matching($filters, byCategory: false), 'product_category_id');

        $categories = ProductCategory::query()->orderedByName()->get(['id', 'parent_id', 'name', 'slug']);
        $childrenOf = $categories->groupBy(fn (ProductCategory $category): string => (string) $category->parent_id);
        $selectedId = $this->selectedCategory($filters)?->id;

        $totals = [];
        $this->addTotals($childrenOf, '', $counts, $totals);

        return $this->branch($childrenOf, '', $totals, $this->pathTo($categories, $selectedId), $selectedId, $openAll);
    }

    /**
     * Every category with products, all levels opened out, for the menu on
     * the plain shop page. Counts are the whole catalog's: the menu only
     * shows when nothing is filtered.
     *
     * @return list<array{id: int, name: string, slug: string, count: int, isSelected: bool, children: list<mixed>}>
     */
    public function categoryMenu(): array
    {
        return $this->categoryTree(new ShopFilters, openAll: true);
    }

    /**
     * Manufacturers with products under the current filters, by name.
     *
     * @return list<array{id: int, name: string, count: int}>
     */
    public function manufacturers(ShopFilters $filters): array
    {
        $counts = $this->countsBy($this->matching($filters, byManufacturer: false), 'manufacturer_id');

        // The chosen one stays in the list even if the other filters leave it
        // no products, so the select never shows a value it cannot name.
        $ids = $counts->keys()->when($filters->manufacturerId !== null, fn (Collection $ids) => $ids->push($filters->manufacturerId));

        return Manufacturer::query()
            ->whereIn('id', $ids->unique()->all())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Manufacturer $manufacturer): array => [
                'id' => $manufacturer->id,
                'name' => $manufacturer->name,
                'count' => (int) ($counts[$manufacturer->id] ?? 0),
            ])
            ->all();
    }

    /**
     * @return Builder<Product>
     */
    private function matching(ShopFilters $filters, bool $byCategory = true, bool $byManufacturer = true): Builder
    {
        $query = Product::query()->active();

        if ($filters->search !== '') {
            $query->search($filters->search);
        }

        if ($byCategory && ($resolved = $this->resolveCategory($filters)) !== null) {
            $query->whereIn('product_category_id', $resolved['ids']);
        }

        if ($byManufacturer && $filters->manufacturerId !== null) {
            $query->where('manufacturer_id', $filters->manufacturerId);
        }

        if ($filters->inStockOnly) {
            $query->inStock();
        }

        return $query->pricedBetween($filters->minPriceInclVatCents, $filters->maxPriceInclVatCents);
    }

    /**
     * @return array{category: ProductCategory, ids: list<int>}|null
     */
    private function resolveCategory(ShopFilters $filters): ?array
    {
        if ($filters->category === null) {
            return null;
        }

        return $this->categories[$filters->category] ??= $this->lookUpCategory($filters->category);
    }

    /**
     * @return array{category: ProductCategory, ids: list<int>}|null
     */
    private function lookUpCategory(string $slug): ?array
    {
        $category = ProductCategory::query()->where('slug_'.app()->getLocale(), $slug)->first();

        return $category === null
            ? null
            : ['category' => $category, 'ids' => ProductCategory::idsWithDescendants($category->id)];
    }

    /**
     * Products per value of a foreign key column.
     *
     * @param  Builder<Product>  $query
     * @return Collection<int, int>
     */
    private function countsBy(Builder $query, string $column): Collection
    {
        return $query->toBase()
            ->whereNotNull($column)
            ->groupBy($column)
            ->selectRaw("{$column} as group_id, count(*) as aggregate")
            ->pluck('aggregate', 'group_id')
            ->map(fn (mixed $count): int => (int) $count);
    }

    /**
     * Fills $totals with each category's products plus everything below it.
     *
     * @param  Collection<string, Collection<int, ProductCategory>>  $childrenOf
     * @param  Collection<int, int>  $counts
     * @param  array<int, int>  $totals
     */
    private function addTotals(Collection $childrenOf, string $parentKey, Collection $counts, array &$totals): int
    {
        $sum = 0;

        foreach ($childrenOf->get($parentKey, []) as $category) {
            $total = ($counts[$category->id] ?? 0) + $this->addTotals($childrenOf, (string) $category->id, $counts, $totals);

            $totals[$category->id] = $total;
            $sum += $total;
        }

        return $sum;
    }

    /**
     * @param  Collection<string, Collection<int, ProductCategory>>  $childrenOf
     * @param  array<int, int>  $totals
     * @param  list<int>  $openPath  The chosen category and its ancestors.
     * @return list<array{id: int, name: string, slug: string, count: int, isSelected: bool, children: list<mixed>}>
     */
    private function branch(Collection $childrenOf, string $parentKey, array $totals, array $openPath, ?int $selectedId, bool $openAll): array
    {
        $nodes = [];

        foreach ($childrenOf->get($parentKey, []) as $category) {
            $isOnPath = in_array($category->id, $openPath, true);

            if ($totals[$category->id] === 0 && ! $isOnPath) {
                continue;
            }

            $isOpen = $isOnPath || $openAll;

            $nodes[] = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'count' => $totals[$category->id],
                'isSelected' => $category->id === $selectedId,
                'children' => $isOpen ? $this->branch($childrenOf, (string) $category->id, $totals, $openPath, $selectedId, $openAll) : [],
            ];
        }

        return $nodes;
    }

    /**
     * The category and each of its ancestors, or nothing for no category.
     *
     * @param  Collection<int, ProductCategory>  $categories
     * @return list<int>
     */
    private function pathTo(Collection $categories, ?int $categoryId): array
    {
        $byId = $categories->keyBy('id');
        $path = [];

        for ($node = $byId->get($categoryId); $node !== null; $node = $byId->get($node->parent_id)) {
            $path[] = $node->id;
        }

        return $path;
    }
}
