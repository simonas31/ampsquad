<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderLine;
use App\Services\Checkout\OrderTotals;
use Tests\TestCase;

/**
 * Money is whole cents and nothing is rounded twice: each line is its unit
 * price with VAT times the quantity, the order is the sum of the lines, and
 * the VAT is what is left, so the parts always add up to the total.
 */
class OrderTotalsTest extends TestCase
{
    /**
     * @return array{line_total_excl_vat_cents: int, line_total_incl_vat_cents: int}
     */
    private function line(int $exclVat, int $inclVat): array
    {
        return ['line_total_excl_vat_cents' => $exclVat, 'line_total_incl_vat_cents' => $inclVat];
    }

    public function test_one_line_without_shipping(): void
    {
        // 3 units at 10.00 without VAT, 12.10 with: 36.30 in all, 6.30 of it VAT.
        $totals = OrderTotals::forLines([$this->line(3000, 3630)]);

        $this->assertSame(3000, $totals->subtotalExclVatCents);
        $this->assertSame(0, $totals->shippingExclVatCents);
        $this->assertSame(0, $totals->shippingInclVatCents);
        $this->assertSame(630, $totals->vatCents);
        $this->assertSame(3630, $totals->totalInclVatCents);
    }

    public function test_the_lines_are_summed_not_rounded_again(): void
    {
        // 3.33 + 21% is 4.0293, so a unit is 4.03; three units are 12.09,
        // not the 11.99 plus 21% of the sum (12.0879) would give.
        $totals = OrderTotals::forLines([$this->line(3 * 333, 3 * 403), $this->line(1000, 1210)]);

        $this->assertSame(999 + 1000, $totals->subtotalExclVatCents);
        $this->assertSame(1209 + 1210, $totals->totalInclVatCents);
        $this->assertSame(1209 + 1210 - 999 - 1000, $totals->vatCents);
    }

    public function test_shipping_gets_vat_like_a_price_and_is_part_of_the_total(): void
    {
        // 3.99 + 21% is 4.8279, so 4.83.
        $totals = OrderTotals::forLines([$this->line(1000, 1210)], 399);

        $this->assertSame(1000, $totals->subtotalExclVatCents);
        $this->assertSame(399, $totals->shippingExclVatCents);
        $this->assertSame(483, $totals->shippingInclVatCents);
        $this->assertSame(1210 + 483, $totals->totalInclVatCents);
        $this->assertSame(210 + 84, $totals->vatCents);
    }

    public function test_the_parts_always_add_up_to_the_total(): void
    {
        foreach ([1, 7, 333, 999, 1234, 99_999] as $unit) {
            foreach ([0, 1, 299, 349, 450] as $shipping) {
                foreach ([1, 3, 99] as $quantity) {
                    $inclVat = intdiv($unit * 121 + 50, 100);
                    $totals = OrderTotals::forLines([$this->line($quantity * $unit, $quantity * $inclVat)], $shipping);

                    $this->assertSame(
                        $totals->totalInclVatCents,
                        $totals->subtotalExclVatCents + $totals->shippingExclVatCents + $totals->vatCents,
                        "{$quantity} x {$unit} + {$shipping}",
                    );
                    $this->assertGreaterThanOrEqual(0, $totals->vatCents);
                }
            }
        }
    }

    public function test_no_lines_and_no_shipping_is_nothing(): void
    {
        $totals = OrderTotals::forLines([]);

        $this->assertSame(0, $totals->totalInclVatCents);
        $this->assertSame(0, $totals->vatCents);
    }

    public function test_the_configured_vat_rate_is_used_for_shipping(): void
    {
        config(['shop.vat_rate' => 9]);

        $this->assertSame(1090, OrderTotals::forLines([], 1000)->shippingInclVatCents);
    }

    public function test_it_reads_lines_that_are_models_as_well_as_arrays(): void
    {
        $line = new OrderLine($this->line(1000, 1210));

        $this->assertSame(1210, OrderTotals::forLines([$line])->totalInclVatCents);
    }

    public function test_it_gives_exactly_the_columns_an_order_takes(): void
    {
        $attributes = OrderTotals::forLines([$this->line(1000, 1210)], 399)->toAttributes();

        $this->assertSame([], array_diff(array_keys($attributes), (new Order)->getFillable()));
        $this->assertSame(
            ['subtotal_excl_vat_cents', 'shipping_excl_vat_cents', 'shipping_incl_vat_cents', 'vat_cents', 'total_incl_vat_cents'],
            array_keys($attributes),
        );
    }
}
