<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The oversell guard: stock moves for orders in one statement that decides
 * for itself, so it cannot go below zero and two buyers of the last unit
 * cannot both get it.
 */
class ProductStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_taking_stock_reduces_it_by_exactly_the_quantity(): void
    {
        $product = Product::factory()->inStock(10)->create();

        $this->assertTrue(Product::takeStock($product->id, 3));

        $this->assertSame(7, $product->fresh()->stock_quantity);
    }

    public function test_taking_all_that_is_left_leaves_none(): void
    {
        $product = Product::factory()->inStock(4)->create();

        $this->assertTrue(Product::takeStock($product->id, 4));

        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    public function test_taking_more_than_is_left_takes_nothing(): void
    {
        $product = Product::factory()->inStock(2)->create();

        $this->assertFalse(Product::takeStock($product->id, 3));

        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    public function test_the_last_unit_goes_to_one_buyer_only(): void
    {
        $product = Product::factory()->inStock(1)->create();

        // Both buyers looked a moment ago and saw one unit.
        $firstBuyerSaw = Product::query()->findOrFail($product->id);
        $secondBuyerSaw = Product::query()->findOrFail($product->id);
        $this->assertSame(1, $firstBuyerSaw->stock_quantity);
        $this->assertSame(1, $secondBuyerSaw->stock_quantity);

        $this->assertTrue(Product::takeStock($firstBuyerSaw->id, 1));
        $this->assertFalse(Product::takeStock($secondBuyerSaw->id, 1));

        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    public function test_stock_never_goes_below_zero_however_many_try(): void
    {
        $product = Product::factory()->inStock(3)->create();

        $taken = collect(range(1, 8))
            ->filter(fn (): bool => Product::takeStock($product->id, 1))
            ->count();

        $this->assertSame(3, $taken);
        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    public function test_a_product_that_is_off_sale_cannot_be_taken(): void
    {
        $product = Product::factory()->inactive()->inStock(5)->create();

        $this->assertFalse(Product::takeStock($product->id, 1));

        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_a_deleted_product_cannot_be_taken(): void
    {
        $product = Product::factory()->inStock(5)->create();
        $product->delete();

        $this->assertFalse(Product::takeStock($product->id, 1));

        $this->assertSame(5, Product::withTrashed()->findOrFail($product->id)->stock_quantity);
    }

    public function test_an_unknown_product_cannot_be_taken(): void
    {
        $this->assertFalse(Product::takeStock(999_999, 1));
    }

    public function test_taking_stock_leaves_the_products_updated_at_alone(): void
    {
        $product = Product::factory()->inStock(5)->create();
        Product::query()->whereKey($product->id)->toBase()->update(['updated_at' => '2026-01-02 03:04:05']);

        Product::takeStock($product->id, 1);
        Product::returnStock($product->id, 1);

        $this->assertSame('2026-01-02 03:04:05', $product->fresh()->updated_at->format('Y-m-d H:i:s'));
    }

    public function test_returned_stock_is_added_back(): void
    {
        $product = Product::factory()->inStock(10)->create();
        Product::takeStock($product->id, 4);

        $this->assertTrue(Product::returnStock($product->id, 4));

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_stock_goes_back_to_a_product_that_was_switched_off_or_deleted_meanwhile(): void
    {
        $switchedOff = Product::factory()->inStock(5)->create();
        $deleted = Product::factory()->inStock(5)->create();
        Product::takeStock($switchedOff->id, 2);
        Product::takeStock($deleted->id, 2);

        $switchedOff->update(['is_active' => false]);
        $deleted->delete();

        $this->assertTrue(Product::returnStock($switchedOff->id, 2));
        $this->assertTrue(Product::returnStock($deleted->id, 2));

        $this->assertSame(5, $switchedOff->fresh()->stock_quantity);
        $this->assertSame(5, Product::withTrashed()->findOrFail($deleted->id)->stock_quantity);
    }

    public function test_returning_stock_for_an_unknown_product_says_so(): void
    {
        $this->assertFalse(Product::returnStock(999_999, 1));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function quantitiesThatAreNotWholeUnits(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    #[DataProvider('quantitiesThatAreNotWholeUnits')]
    public function test_taking_stock_only_moves_whole_units(int $quantity): void
    {
        $product = Product::factory()->inStock(5)->create();

        $this->expectException(InvalidArgumentException::class);

        Product::takeStock($product->id, $quantity);
    }

    #[DataProvider('quantitiesThatAreNotWholeUnits')]
    public function test_returning_stock_only_moves_whole_units(int $quantity): void
    {
        $product = Product::factory()->inStock(5)->create();

        $this->expectException(InvalidArgumentException::class);

        Product::returnStock($product->id, $quantity);
    }
}
