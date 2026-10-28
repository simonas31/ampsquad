<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ShippingMethod;
use App\Models\CatalogImport;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Services\Catalog\CatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * An order is what was sold: nothing that happens to the product, the VAT
 * rate or the catalog afterwards changes it.
 */
class OrderSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_line_copies_the_product_as_it_is(): void
    {
        $product = Product::factory()->priced(333)->create([
            'name' => ['lt' => 'Maršrutizatorius', 'en' => 'Router'],
            'sku' => 'RT-100',
        ]);

        $lithuanian = OrderLine::snapshotOf($product, 3, 'lt');
        $english = OrderLine::snapshotOf($product, 3, 'en');

        $this->assertSame($product->id, $lithuanian['product_id']);
        $this->assertSame('Maršrutizatorius', $lithuanian['product_name']);
        $this->assertSame('Router', $english['product_name']);
        $this->assertSame('RT-100', $lithuanian['sku']);
        $this->assertSame(3, $lithuanian['quantity']);
        $this->assertSame(333, $lithuanian['unit_price_excl_vat_cents']);
        $this->assertSame(403, $lithuanian['unit_price_incl_vat_cents']);
        $this->assertSame(999, $lithuanian['line_total_excl_vat_cents']);
        $this->assertSame(1209, $lithuanian['line_total_incl_vat_cents']);
    }

    public function test_a_line_is_the_rounded_unit_price_times_the_quantity(): void
    {
        $product = Product::factory()->priced(333)->create();

        $line = OrderLine::snapshotOf($product, 7, 'lt');

        $this->assertSame(7 * $product->priceInclVatCents(), $line['line_total_incl_vat_cents']);
    }

    public function test_a_product_made_by_hand_has_no_sku_on_its_line(): void
    {
        $product = Product::factory()->create(['sku' => null]);

        $this->assertNull(OrderLine::snapshotOf($product, 1, 'lt')['sku']);
    }

    public function test_an_order_does_not_change_when_its_product_does(): void
    {
        $product = Product::factory()->priced(1000)->create(['name' => ['lt' => 'Senas', 'en' => 'Old'], 'sku' => 'OLD-1']);
        $order = Order::factory()->withLine($product, 2)->create();
        $before = $order->fresh()->only(['subtotal_excl_vat_cents', 'vat_cents', 'total_incl_vat_cents']);
        $lineBefore = $order->lines()->sole()->only(['product_name', 'sku', 'quantity', 'unit_price_excl_vat_cents', 'unit_price_incl_vat_cents', 'line_total_excl_vat_cents', 'line_total_incl_vat_cents']);

        $product->update(['price_excl_vat_cents' => 9999, 'name' => ['lt' => 'Naujas', 'en' => 'New'], 'sku' => 'NEW-1']);
        $product->delete();

        $this->assertSame($lineBefore, $order->lines()->sole()->only(array_keys($lineBefore)));
        $this->assertSame($before, $order->fresh()->only(array_keys($before)));
        $this->assertSame('Senas', $order->lines()->sole()->product_name);
        $this->assertSame(2000, $order->lines()->sole()->line_total_excl_vat_cents);
    }

    public function test_an_order_does_not_change_when_the_vat_rate_does(): void
    {
        $product = Product::factory()->priced(1000)->create();
        $order = Order::factory()->shippedBy(ShippingMethod::Dpd, 450)->withLine($product, 2)->create();
        $line = $order->lines()->sole();

        $this->assertSame(21, $order->vat_rate_percent);
        $this->assertSame(1210, $line->unit_price_incl_vat_cents);
        $totalBefore = $order->total_incl_vat_cents;

        config(['shop.vat_rate' => 9]);

        $this->assertSame(1090, $product->priceInclVatCents(), 'the shop shows the new price');
        $this->assertSame(21, $order->fresh()->vat_rate_percent);
        $this->assertSame(1210, $line->fresh()->unit_price_incl_vat_cents);
        $this->assertSame(2420, $line->fresh()->line_total_incl_vat_cents);
        $this->assertSame($totalBefore, $order->fresh()->total_incl_vat_cents);
    }

    public function test_a_catalog_import_changes_neither_the_order_nor_the_stock_it_took(): void
    {
        Storage::fake('local');

        $this->importCatalog([['sku' => 'A', 'name' => 'Old name', 'price' => '10.00', 'stock' => 10]]);
        $product = Product::query()->where('sku', 'A')->sole();
        $product->update(['is_active' => true]);

        $order = Order::factory()->withLine($product, 4)->create();
        $this->assertTrue(Product::takeStock($product->id, 4));
        $this->assertSame(6, $product->fresh()->stock_quantity);

        // The next catalog file reprices and renames it, and claims 500 in stock.
        $this->importCatalog([['sku' => 'A', 'name' => 'New name', 'price' => '12.50', 'stock' => 500]]);

        $this->assertSame(1250, $product->fresh()->price_excl_vat_cents);
        $this->assertSame(6, $product->fresh()->stock_quantity, 'the import never touches stock');

        $line = $order->lines()->sole();
        $this->assertSame('Old name', $line->product_name);
        $this->assertSame(1000, $line->unit_price_excl_vat_cents);
        $this->assertSame(4000, $line->line_total_excl_vat_cents);
        $this->assertSame(4 * 1210, $order->fresh()->total_incl_vat_cents);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importCatalog(array $rows): void
    {
        Storage::disk('local')->put($path = 'imports/'.uniqid().'.json', json_encode(['schema_version' => 1, 'products' => $rows], JSON_THROW_ON_ERROR));

        $import = CatalogImport::query()->create(['original_filename' => 'catalog.json', 'file_path' => $path]);

        app(CatalogImporter::class)->import($import);
    }
}
