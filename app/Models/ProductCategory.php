<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\ProductCategoryFactory;
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
     * @var array<string, mixed>
     */
    protected $attributes = [
        'order' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'name',
        'order',
    ];

    /**
     * @var list<string>
     */
    public array $translatable = [
        'name',
        'slug',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    protected function slugSourceAttribute(): string
    {
        return 'name';
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
