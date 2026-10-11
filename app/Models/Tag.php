<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    use HasSlug;
    use HasTranslations;

    /**
     * @var list<string>
     */
    protected $fillable = [
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

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }
}
