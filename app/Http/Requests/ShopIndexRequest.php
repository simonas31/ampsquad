<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ShopSort;
use App\Models\Product;
use App\Services\Catalog\CatalogRowParser;
use App\Services\Shop\ShopFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * The shop listing's query string. Everything in it is optional and comes
 * from a link or a form anyone can edit by hand, so a value that makes no
 * sense is dropped rather than answered with an error page: a stale or
 * mistyped link still shows the shop.
 */
class ShopIndexRequest extends FormRequest
{
    public const int MAX_SEARCH_LENGTH = 100;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, never>
     */
    public function rules(): array
    {
        return [];
    }

    public function filters(): ShopFilters
    {
        return new ShopFilters(
            search: Str::limit(Str::squish($this->text('q')), self::MAX_SEARCH_LENGTH, ''),
            category: $this->text('category') !== '' ? $this->text('category') : null,
            manufacturerId: ctype_digit($this->text('manufacturer')) && $this->text('manufacturer') !== ''
                ? (int) $this->text('manufacturer')
                : null,
            inStockOnly: filter_var($this->text('in_stock'), FILTER_VALIDATE_BOOLEAN),
            minPriceCents: $this->priceCents('min_price'),
            maxPriceCents: $this->priceCents('max_price'),
            sort: ShopSort::tryFrom($this->text('sort')) ?? ShopSort::Featured,
        );
    }

    /**
     * A query value as text; empty when it is missing or not text (`q[]=x`).
     */
    private function text(string $key): string
    {
        $value = $this->query($key);

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Euros as typed ("10", "10.5", "10.50") to cents, or null when the
     * visitor typed something else or a sum no product can cost.
     */
    private function priceCents(string $key): ?int
    {
        $text = str_replace(',', '.', $this->text($key));

        if (! preg_match('/^\d{1,6}(\.\d{1,2})?$/', $text)) {
            return null;
        }

        $cents = CatalogRowParser::priceToCents($text);

        return $cents <= Product::MAX_PRICE_CENTS ? $cents : null;
    }
}
