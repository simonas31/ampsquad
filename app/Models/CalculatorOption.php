<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CalculatorOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class CalculatorOption extends Model
{
    /** @use HasFactory<CalculatorOptionFactory> */
    use HasFactory;

    use HasTranslations;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'calculator_category_id',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(CalculatorCategory::class, 'calculator_category_id');
    }
}
