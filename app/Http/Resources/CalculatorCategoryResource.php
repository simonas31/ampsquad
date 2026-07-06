<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CalculatorCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CalculatorCategory
 */
class CalculatorCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'options' => $this->whenLoaded(
                'options',
                fn () => $this->options->pluck('name'),
            ),
        ];
    }
}
