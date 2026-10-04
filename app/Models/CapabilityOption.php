<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CapabilityOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class CapabilityOption extends Model
{
    /** @use HasFactory<CapabilityOptionFactory> */
    use HasFactory;

    use HasTranslations;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'capability_id',
        'name',
        'order',
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
        ];
    }

    public function capability(): BelongsTo
    {
        return $this->belongsTo(Capability::class, 'capability_id');
    }
}
