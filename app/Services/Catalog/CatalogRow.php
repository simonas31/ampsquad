<?php

declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * One product from a catalog file after validation: trimmed, typed, and with
 * the price already in whole cents. The price is the net one, without VAT. `stock` is only the starting quantity of
 * a product the import creates; null when the file leaves it out.
 */
final readonly class CatalogRow
{
    /**
     * @param  list<string>  $categoryPath  Top level first; empty when uncategorised.
     */
    public function __construct(
        public string $sku,
        public string $name,
        public array $categoryPath,
        public ?string $excerpt,
        public ?string $description,
        public int $priceNetCents,
        public ?int $stock,
        public ?string $imageUrl,
        public ?string $manufacturer,
        public ?string $ean,
    ) {}
}
