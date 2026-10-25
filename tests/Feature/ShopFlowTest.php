<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CatalogImport;
use App\Models\Product;
use App\Services\CartService;
use App\Services\Catalog\CatalogImporter;
use App\Services\Shop\ShopCatalog;
use App\Services\Shop\ShopFilters;
use App\Settings\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The whole path in one story: imported product data becomes a product, the
 * shop owns its quantity, a visitor fills a cart from it, and the cart's
 * lines and totals keep telling the truth as the shop and the catalog change
 * underneath it.
 */
class ShopFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $settings = app(ShopSettings::class);
        $settings->enabled = true;
        $settings->save();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function row(string $sku, array $overrides = []): array
    {
        return [
            'sku' => $sku,
            'name' => "Product {$sku}",
            'price' => '10.00',
            'stock' => 10,
            ...$overrides,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importCatalog(array $rows): CatalogImport
    {
        Storage::disk('local')->put($path = 'imports/'.uniqid().'.json', json_encode(['schema_version' => 1, 'products' => $rows], JSON_THROW_ON_ERROR));

        $import = CatalogImport::query()->create(['original_filename' => 'catalog.json', 'file_path' => $path]);

        app(CatalogImporter::class)->import($import);

        return $import->fresh();
    }

    private function product(string $sku): Product
    {
        return Product::query()->where('sku', $sku)->sole();
    }

    private function visitorWith(Cart $cart): static
    {
        return $this->withCookie(CartService::COOKIE, $cart->token);
    }

    public function test_the_cart_keeps_telling_the_truth_as_the_shop_and_the_catalog_change(): void
    {
        $this->importCatalog([
            $this->row('A', ['price' => '10.00', 'stock' => 10]),
            $this->row('B', ['price' => '5.00', 'stock' => 3]),
        ]);
        $a = $this->product('A');
        $b = $this->product('B');

        // A visitor fills a cart from the imported products.
        $this->post(route('cart.items.store'), ['product_id' => $a->id, 'quantity' => 4])->assertSessionHasNoErrors();
        $cart = Cart::query()->sole();
        $this->visitorWith($cart)->post(route('cart.items.store'), ['product_id' => $b->id, 'quantity' => 3])->assertSessionHasNoErrors();

        $this->visitorWith($cart)->get('/cart')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.subtotalCents', 4 * 1000 + 3 * 500)
            ->where('cart.totalQuantity', 7)
            ->where('cartSummary.count', 7)
            ->where('cart.hasReducedItems', false)
        );

        // The shop sells down A's stock: the line is held to what is left.
        $a->update(['stock_quantity' => 2]);

        $this->visitorWith($cart)->get('/cart')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.items.0.status', 'reduced')
            ->where('cart.items.0.quantity', 2)
            ->where('cart.items.0.requestedQuantity', 4)
            ->where('cart.items.1.status', 'available')
            ->where('cart.subtotalCents', 2 * 1000 + 3 * 500)
            ->where('cart.totalQuantity', 5)
            ->where('cartSummary.count', 5)
            ->where('cart.hasReducedItems', true)
        );

        // A new catalog file reprices A and claims a lot of stock, and drops B.
        // The price follows the file; the quantity and B's place in the shop do not.
        $this->importCatalog([$this->row('A', ['price' => '12.50', 'stock' => 500])]);

        $this->assertSame(1250, $a->fresh()->price_cents);
        $this->assertSame(2, $a->fresh()->stock_quantity);
        $this->assertSame(3, $b->fresh()->stock_quantity);
        $this->assertTrue($b->fresh()->is_active);

        $this->visitorWith($cart)->get('/cart')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.items.0.unitPriceCents', 1250)
            ->where('cart.items.0.status', 'reduced')
            ->where('cart.subtotalCents', 2 * 1250 + 3 * 500)
        );

        // The shop takes B off sale: it stays visible in the cart and counts for nothing.
        $b->update(['is_active' => false]);

        $this->visitorWith($cart)->get('/cart')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.items.1.status', 'unavailable')
            ->where('cart.items.1.quantity', 0)
            ->where('cart.subtotalCents', 2 * 1250)
            ->where('cart.totalQuantity', 2)
            ->where('cartSummary.count', 2)
            ->where('cart.hasUnavailableItems', true)
        );

        // The visitor cannot ask for more of A than the shop has, and settles on what is left.
        $line = $cart->items()->where('product_id', $a->id)->sole();

        $this->visitorWith($cart)->patch(route('cart.items.update', $line), ['quantity' => 3])
            ->assertSessionHasErrors(['quantity' => __('shop.not_enough_stock', ['count' => 2])]);
        $this->visitorWith($cart)->patch(route('cart.items.update', $line), ['quantity' => 2])
            ->assertSessionHasNoErrors();

        $this->visitorWith($cart)->get('/cart')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.items.0.status', 'available')
            ->where('cart.hasReducedItems', false)
        );

        // The shop restocks A by hand; a later import still leaves that alone.
        $a->update(['stock_quantity' => 8]);
        $this->importCatalog([$this->row('A', ['price' => '12.50', 'stock' => 1])]);

        $this->assertSame(8, $a->fresh()->stock_quantity);

        $this->visitorWith($cart)->patch(route('cart.items.update', $line), ['quantity' => 8])
            ->assertSessionHasNoErrors();
        $this->assertSame(8, $line->fresh()->quantity);
    }

    public function test_a_product_that_stays_deleted_in_the_shop_still_sits_in_the_cart_as_unavailable(): void
    {
        $this->importCatalog([$this->row('A')]);
        $a = $this->product('A');

        $this->post(route('cart.items.store'), ['product_id' => $a->id, 'quantity' => 1]);
        $cart = Cart::query()->sole();
        $a->delete();

        // Importing the product again updates it but does not bring it back.
        $this->importCatalog([$this->row('A', ['price' => '99.00'])]);

        $this->assertTrue($a->fresh()->trashed());
        $this->visitorWith($cart)->get('/cart')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.items.0.status', 'unavailable')
            ->where('cart.subtotalCents', 0)
        );

        // Restored in the admin panel, it is just as it was.
        $a->restore();

        $this->visitorWith($cart)->get('/cart')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cart.items.0.status', 'available')
            ->where('cart.subtotalCents', 9900)
        );
    }

    public function test_the_listing_does_not_carry_descriptions_through_the_query(): void
    {
        $this->importCatalog([$this->row('A', ['description' => str_repeat('long text ', 100)])]);

        $products = app(ShopCatalog::class)->products(new ShopFilters);

        $this->assertArrayNotHasKey('description', $products->first()->getAttributes());
        $this->get('/shop')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->missing('products.data.0.description')
        );
        $this->get(route('shop.show', $this->product('A')->slug))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('product.description', fn ($description) => str_starts_with($description, 'long text'))
        );
    }
}
