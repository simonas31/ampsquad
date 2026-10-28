<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Tests\Concerns\CheckoutScenario;
use Tests\TestCase;

/**
 * However many lines a cart or an order has, its pages cost the same few
 * queries: nothing is loaded line by line.
 */
class PageQueryBudgetTest extends TestCase
{
    use CheckoutScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->switchCheckoutOn();
        ShippingMethod::factory()->pickup()->create();
        ShippingMethod::factory()->priced(248)->create();
    }

    /**
     * The queries a request makes, after one run that is not counted: the
     * first request of a process loads settings and the like once, which is
     * not what scales with a cart's lines.
     */
    private function queriesOf(callable $request): int
    {
        $request();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * @return array{Cart, Cart}
     */
    private function smallAndBigCart(): array
    {
        $products = Product::factory()->count(20)->inStock(5)->create();

        return [
            $this->cartWith([[$products[0], 1]]),
            $this->cartWith($products->map(fn (Product $product): array => [$product, 2])->all()),
        ];
    }

    public function test_the_cart_page_costs_the_same_for_one_line_or_twenty(): void
    {
        [$small, $big] = $this->smallAndBigCart();

        $one = $this->queriesOf(fn () => $this->visitorWith($small)->get('/cart')->assertOk());
        $twenty = $this->queriesOf(fn () => $this->visitorWith($big)->get('/cart')->assertOk());

        $this->assertSame($one, $twenty, "1 line: {$one} queries, 20 lines: {$twenty}");
    }

    public function test_the_checkout_page_costs_the_same_for_one_line_or_twenty(): void
    {
        [$small, $big] = $this->smallAndBigCart();

        $one = $this->queriesOf(fn () => $this->visitorWith($small)->get('/checkout')->assertOk());
        $twenty = $this->queriesOf(fn () => $this->visitorWith($big)->get('/checkout')->assertOk());

        $this->assertSame($one, $twenty, "1 line: {$one} queries, 20 lines: {$twenty}");
    }

    public function test_the_order_page_costs_the_same_for_one_line_or_twenty_and_a_poll_costs_less(): void
    {
        $products = Product::factory()->count(20)->create();
        $small = Order::factory()->withLine($products[0])->create();
        $big = $products->reduce(fn ($factory, Product $product) => $factory->withLine($product, 2), Order::factory())->create();

        $one = $this->queriesOf(fn () => $this->get(route('orders.show', ['order' => $small->uuid]))->assertOk());
        $twenty = $this->queriesOf(fn () => $this->get(route('orders.show', ['order' => $big->uuid]))->assertOk());
        $this->assertSame($one, $twenty, "1 line: {$one} queries, 20 lines: {$twenty}");

        // The page asks for the order alone while it waits for the payment.
        $poll = $this->queriesOf(function () use ($big): void {
            $response = $this->get(route('orders.show', ['order' => $big->uuid]), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => Inertia::getVersion(),
                'X-Inertia-Partial-Component' => 'Orders/Show',
                'X-Inertia-Partial-Data' => 'order',
            ])->assertOk();

            $this->assertSame(['order'], array_values(array_intersect(['order', 'breadcrumbs', 'seo', 'navigation', 'cartSummary'], array_keys($response->json('props')))));
        });
        $this->assertLessThan($twenty, $poll, "full page: {$twenty} queries, poll: {$poll}");
        $this->assertLessThanOrEqual(6, $poll);
    }
}
