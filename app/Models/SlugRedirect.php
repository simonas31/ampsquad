<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SlugRedirectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A slug a live record used to have. Looked up when a request misses the
 * record's current slug, so the old URL can 301 to the current one.
 */
class SlugRedirect extends Model
{
    /** @use HasFactory<SlugRedirectFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'locale',
        'old_slug',
    ];

    public function redirectable(): MorphTo
    {
        return $this->morphTo();
    }
}
