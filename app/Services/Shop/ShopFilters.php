<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\ShopSort;

/**
 * What the visitor asked the shop listing to show, already cleaned: see
 * ShopIndexRequest::filters(). Prices are whole cents.
 */
final readonly class ShopFilters
{
    public function __construct(
        public string $search = '',
        public ?string $category = null,
        public ?int $manufacturerId = null,
        public bool $inStockOnly = false,
        public ?int $minPriceCents = null,
        public ?int $maxPriceCents = null,
        public ShopSort $sort = ShopSort::Featured,
    ) {}

    /**
     * Whether anything beyond the default listing was asked for. Such pages
     * are not worth indexing: they are the same products in another cut.
     */
    public function isActive(): bool
    {
        return $this->search !== ''
            || $this->category !== null
            || $this->manufacturerId !== null
            || $this->inStockOnly
            || $this->minPriceCents !== null
            || $this->maxPriceCents !== null
            || $this->sort !== ShopSort::Featured;
    }

    /**
     * The shape the page sends to the client to fill its filter form.
     * Prices go back as euros, the unit the visitor typed them in.
     *
     * @return array{search: string, category: string|null, manufacturer: int|null, inStock: bool, minPrice: string, maxPrice: string, sort: string}
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'category' => $this->category,
            'manufacturer' => $this->manufacturerId,
            'inStock' => $this->inStockOnly,
            'minPrice' => $this->euros($this->minPriceCents),
            'maxPrice' => $this->euros($this->maxPriceCents),
            'sort' => $this->sort->value,
        ];
    }

    private function euros(?int $cents): string
    {
        return $cents === null ? '' : number_format($cents / 100, 2, '.', '');
    }
}
