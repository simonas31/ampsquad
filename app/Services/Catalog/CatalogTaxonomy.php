<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Manufacturer;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;

/**
 * Finds, or creates, the categories and manufacturers a catalog refers to by
 * name. Both lists are small (hundreds of rows), so they are held in memory
 * and refreshed at the start of every chunk; a chunk that rolls back then
 * cannot leave ids of rows that no longer exist behind.
 *
 * Names are matched case-insensitively, like the database does, and a
 * category is identified by its whole path, so "IP kameros" under two
 * different parents stays two categories. Categories are matched on their
 * Lithuanian name, the language of the catalog.
 */
final class CatalogTaxonomy
{
    private const string SEPARATOR = "\0";

    /**
     * Path key -> category id.
     *
     * @var array<string, int>
     */
    private array $categoryIds = [];

    /**
     * Lower-cased name -> manufacturer id.
     *
     * @var array<string, int>
     */
    private array $manufacturerIds = [];

    public function refresh(): void
    {
        $categories = ProductCategory::query()->get(['id', 'parent_id', 'name'])->keyBy('id');
        $pathKeys = [];

        $this->categoryIds = [];

        foreach ($categories as $category) {
            $this->categoryIds[$this->pathKey($category, $categories, $pathKeys)] = $category->id;
        }

        $this->manufacturerIds = Manufacturer::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Manufacturer $manufacturer): array => [mb_strtolower($manufacturer->name) => $manufacturer->id])
            ->all();
    }

    /**
     * The id of the category at the end of the path, creating whatever part
     * of the path is missing. Null for an empty path.
     *
     * @param  list<string>  $path
     */
    public function categoryId(array $path): ?int
    {
        $parentId = null;
        $key = '';

        foreach ($path as $name) {
            $key .= self::SEPARATOR.mb_strtolower($name);

            $parentId = $this->categoryIds[$key] ??= ProductCategory::query()->create([
                'parent_id' => $parentId,
                'name' => ['lt' => $name, 'en' => $name],
            ])->id;
        }

        return $parentId;
    }

    public function manufacturerId(?string $name): ?int
    {
        if ($name === null) {
            return null;
        }

        return $this->manufacturerIds[mb_strtolower($name)] ??= Manufacturer::query()->create(['name' => $name])->id;
    }

    /**
     * @param  Collection<int, ProductCategory>  $categories
     * @param  array<int, string>  $pathKeys
     */
    private function pathKey(ProductCategory $category, Collection $categories, array &$pathKeys): string
    {
        if (isset($pathKeys[$category->id])) {
            return $pathKeys[$category->id];
        }

        $parent = $category->parent_id === null ? null : $categories->get($category->parent_id);
        $prefix = $parent === null ? '' : $this->pathKey($parent, $categories, $pathKeys);
        $name = $category->getTranslation('name', 'lt', false) ?: (string) $category->name;

        return $pathKeys[$category->id] = $prefix.self::SEPARATOR.mb_strtolower($name);
    }
}
