<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CalculatorCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class CalculatorCategory extends Model
{
    /** @use HasFactory<CalculatorCategoryFactory> */
    use HasFactory;

    use HasTranslations;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'icon',
        'order',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    public array $translatable = [
        'name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(CalculatorOption::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
