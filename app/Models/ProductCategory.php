<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A node in the shop's category tree. Separate from Category, which
 * classifies portfolio projects. Names of siblings are unique, but the same
 * name can appear under different parents, so slugs are made unique across
 * the whole table (see HasSlug) to keep one shop filter URL per category.
 */
class ProductCategory extends Model
{
    /** @use HasFactory<ProductCategoryFactory> */
    use HasFactory;

    use HasSlug;
    use HasTranslations;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'name',
    ];

    /**
     * @var list<string>
     */
    public array $translatable = [
        'name',
        'slug',
    ];

    protected function slugSourceAttribute(): string
    {
        return 'name';
    }

    /**
     * Every category labelled with its whole path, "Top › Sub › Leaf", for
     * pickers where the bare name is ambiguous. The tree has a few hundred
     * nodes, so it is read in one query and walked in memory.
     *
     * @return array<int, string>
     */
    public static function pathLabels(): array
    {
        $categories = static::query()->get(['id', 'parent_id', 'name'])->keyBy('id');
        $labels = [];

        foreach ($categories as $category) {
            $names = [];

            for ($node = $category; $node !== null; $node = $categories->get($node->parent_id)) {
                array_unshift($names, $node->name);
            }

            $labels[$category->id] = implode(' › ', $names);
        }

        natcasesort($labels);

        return $labels;
    }

    /**
     * The category's id followed by the ids of everything below it, so a
     * filter on "Tinklo įranga" also finds products filed under its
     * sub-categories.
     *
     * @return list<int>
     */
    public static function idsWithDescendants(int $id): array
    {
        $childrenOf = static::query()->get(['id', 'parent_id'])->groupBy('parent_id');
        $ids = [$id];
        $pending = [$id];

        while ($pending !== []) {
            foreach ($childrenOf->get(array_pop($pending), []) as $child) {
                $ids[] = $child->id;
                $pending[] = $child->id;
            }
        }

        return $ids;
    }

    /**
     * Alphabetically by the current language's name, ignoring case and
     * accents.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrderedByName(Builder $query): Builder
    {
        return $query
            ->orderByRaw('json_unquote(json_extract(`name`, ?)) collate utf8mb4_unicode_ci', ['$."'.app()->getLocale().'"'])
            ->orderBy('id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
