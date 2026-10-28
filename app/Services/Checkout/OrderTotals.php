<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\OrderLine;
use App\Models\Product;

/**
 * The amounts of an order, worked out from its lines and shipping in whole
 * cents. The lines already carry their own VAT-inclusive figures (see
 * OrderLine::snapshotOf()), so the order's total is their sum and no rounding
 * happens on the sum. Shipping is entered without VAT and gets the VAT added
 * the way a product's price does. The VAT is what is left between the total
 * and the amounts without VAT, so the parts always add up to the total.
 */
final readonly class OrderTotals
{
    public function __construct(
        public int $subtotalExclVatCents,
        public int $shippingExclVatCents,
        public int $shippingInclVatCents,
        public int $vatCents,
        public int $totalInclVatCents,
    ) {}

    /**
     * @param  iterable<OrderLine|array{line_total_excl_vat_cents: int, line_total_incl_vat_cents: int}>  $lines
     */
    public static function forLines(iterable $lines, int $shippingExclVatCents = 0): self
    {
        $linesExclVat = 0;
        $linesInclVat = 0;

        foreach ($lines as $line) {
            $linesExclVat += $line['line_total_excl_vat_cents'];
            $linesInclVat += $line['line_total_incl_vat_cents'];
        }

        $shippingInclVat = Product::addVat($shippingExclVatCents);
        $total = $linesInclVat + $shippingInclVat;

        return new self(
            subtotalExclVatCents: $linesExclVat,
            shippingExclVatCents: $shippingExclVatCents,
            shippingInclVatCents: $shippingInclVat,
            vatCents: $total - $linesExclVat - $shippingExclVatCents,
            totalInclVatCents: $total,
        );
    }

    /**
     * The order's columns.
     *
     * @return array{subtotal_excl_vat_cents: int, shipping_excl_vat_cents: int, shipping_incl_vat_cents: int, vat_cents: int, total_incl_vat_cents: int}
     */
    public function toAttributes(): array
    {
        return [
            'subtotal_excl_vat_cents' => $this->subtotalExclVatCents,
            'shipping_excl_vat_cents' => $this->shippingExclVatCents,
            'shipping_incl_vat_cents' => $this->shippingInclVatCents,
            'vat_cents' => $this->vatCents,
            'total_incl_vat_cents' => $this->totalInclVatCents,
        ];
    }
}
