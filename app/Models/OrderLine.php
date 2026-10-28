<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrderLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One product on an order, as it was sold: the name, SKU and prices are
 * copies made by snapshotOf(), never read back from the product. The product
 * is only a pointer for someone in the admin panel to follow.
 */
class OrderLine extends Model
{
    /** @use HasFactory<OrderLineFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'product_name',
        'sku',
        'quantity',
        'unit_price_excl_vat_cents',
        'unit_price_incl_vat_cents',
        'line_total_excl_vat_cents',
        'line_total_incl_vat_cents',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_excl_vat_cents' => 'integer',
            'unit_price_incl_vat_cents' => 'integer',
            'line_total_excl_vat_cents' => 'integer',
            'line_total_incl_vat_cents' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Includes soft deleted products: a line outlives its product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * What a line of $quantity units of the product is worth right now, as
     * the columns of a line. The unit price with VAT is the same rounded
     * figure the shop shows, and the line is that times the quantity, so
     * what the customer saw per unit times the quantity is what they pay.
     *
     * The name is the one in $locale (the language the customer ordered in).
     *
     * @return array{product_id: int, product_name: string, sku: ?string, quantity: int, unit_price_excl_vat_cents: int, unit_price_incl_vat_cents: int, line_total_excl_vat_cents: int, line_total_incl_vat_cents: int}
     */
    public static function snapshotOf(Product $product, int $quantity, string $locale): array
    {
        $unitExclVat = $product->price_excl_vat_cents;
        $unitInclVat = $product->priceInclVatCents();

        return [
            'product_id' => $product->getKey(),
            'product_name' => $product->getTranslation('name', $locale),
            'sku' => $product->sku,
            'quantity' => $quantity,
            'unit_price_excl_vat_cents' => $unitExclVat,
            'unit_price_incl_vat_cents' => $unitInclVat,
            'line_total_excl_vat_cents' => $quantity * $unitExclVat,
            'line_total_incl_vat_cents' => $quantity * $unitInclVat,
        ];
    }
}
