<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function cartWith(Product $product, int $quantity = 1): Cart
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)->for($product)->quantity($quantity)->create();

        return $cart->fresh();
    }

    public function test_a_cart_gets_a_unique_uuid_token_when_created(): void
    {
        $first = Cart::query()->create();
        $second = Cart::query()->create();

        $this->assertTrue(Str::isUuid($first->token));
        $this->assertNotSame($first->token, $second->token);
    }

    public function test_token_cannot_be_mass_assigned(): void
    {
        $this->expectException(MassAssignmentException::class);

        Cart::query()->create(['token' => 'chosen-by-the-client']);
    }

    public function test_a_product_can_only_appear_once_per_cart(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create();
        CartItem::factory()->for($cart)->for($product)->create();

        $this->expectException(QueryException::class);

        CartItem::factory()->for($cart)->for($product)->create();
    }

    public function test_the_same_product_can_be_in_different_carts(): void
    {
        $product = Product::factory()->create();

        $this->cartWith($product);
        $this->cartWith($product);

        $this->assertSame(2, CartItem::query()->where('product_id', $product->id)->count());
    }

    public function test_database_rejects_zero_and_negative_quantities(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create();

        foreach ([0, -1] as $quantity) {
            try {
                DB::table('cart_items')->insert([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                ]);

                $this->fail("A quantity of {$quantity} was stored.");
            } catch (QueryException) {
                $this->assertSame(0, CartItem::query()->count());
            }
        }
    }

    public function test_deleting_a_cart_deletes_its_items(): void
    {
        $cart = $this->cartWith(Product::factory()->create());

        $cart->delete();

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_a_product_in_a_cart_cannot_be_force_deleted(): void
    {
        $product = Product::factory()->create();
        $this->cartWith($product);

        $this->expectException(QueryException::class);

        $product->forceDelete();
    }

    public function test_a_soft_deleted_product_stays_on_its_cart_line_but_is_unavailable(): void
    {
        $product = Product::factory()->create();
        $cart = $this->cartWith($product);
        $product->delete();

        $item = $cart->items()->with('product')->firstOrFail();

        $this->assertTrue($item->product->is($product));
        $this->assertFalse($item->isAvailable());
    }

    public function test_changing_an_item_touches_the_cart(): void
    {
        $cart = $this->cartWith(Product::factory()->create());
        $before = $cart->updated_at;

        $this->travel(10)->minutes();
        $cart->items()->firstOrFail()->update(['quantity' => 3]);

        $this->assertTrue($cart->fresh()->updated_at->gt($before));
    }

    public function test_carts_untouched_for_the_cart_lifetime_are_pruned_with_their_lines(): void
    {
        $stale = $this->cartWith(Product::factory()->create());
        $recent = $this->cartWith(Product::factory()->create());
        Cart::query()->whereKey($stale->id)->update(['updated_at' => now()->subDays(Cart::LIFETIME_DAYS)->subMinute()]);
        Cart::query()->whereKey($recent->id)->update(['updated_at' => now()->subDays(Cart::LIFETIME_DAYS)->addMinute()]);

        $this->artisan('model:prune', ['--model' => [Cart::class]])->assertSuccessful();

        $this->assertModelMissing($stale);
        $this->assertModelExists($recent);
        $this->assertSame(1, CartItem::query()->count());
    }

    public function test_the_scheduler_prunes_carts_daily(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'model:prune'));

        $this->assertNotNull($event);
        $this->assertStringContainsString(Cart::class, $event->command);
        $this->assertSame('0 0 * * *', $event->expression);
    }

    public function test_an_empty_cart_has_zero_totals(): void
    {
        $cart = Cart::factory()->create();

        $this->assertSame(0, $cart->subtotalCents());
        $this->assertSame(0, $cart->totalQuantity());
    }

    public function test_line_total_is_quantity_times_the_product_price(): void
    {
        $cart = $this->cartWith(Product::factory()->priced(1999)->create(), 3);

        $this->assertSame(5997, $cart->items->first()->lineTotalCents());
    }

    public function test_subtotal_and_quantity_sum_all_lines(): void
    {
        $cart = $this->cartWith(Product::factory()->priced(1000)->create(), 2);
        CartItem::factory()->for($cart)->for(Product::factory()->priced(250)->create())->quantity(4)->create();

        $cart = $cart->fresh();

        $this->assertSame(3000, $cart->subtotalCents());
        $this->assertSame(6, $cart->totalQuantity());
    }

    public function test_totals_leave_out_lines_whose_product_cannot_be_bought(): void
    {
        $cart = $this->cartWith(Product::factory()->priced(1000)->create(), 1);
        CartItem::factory()->for($cart)->for(Product::factory()->inactive()->priced(500)->create())->quantity(2)->create();

        $deleted = Product::factory()->priced(700)->create();
        CartItem::factory()->for($cart)->for($deleted)->create();
        $deleted->delete();

        $cart = $cart->fresh();

        $this->assertSame(1000, $cart->subtotalCents());
        $this->assertSame(1, $cart->totalQuantity());
    }

    public function test_totals_follow_the_current_product_price(): void
    {
        $product = Product::factory()->priced(1000)->create();
        $cart = $this->cartWith($product, 2);

        $product->update(['price_cents' => 1500]);

        $this->assertSame(3000, $cart->fresh()->subtotalCents());
    }

    public function test_totals_load_items_and_products_only_once(): void
    {
        $cart = $this->cartWith(Product::factory()->create(), 2);
        CartItem::factory()->for($cart)->for(Product::factory()->create())->create();
        $cart = $cart->fresh();

        DB::enableQueryLog();
        $cart->subtotalCents();
        $cart->totalQuantity();
        $cart->subtotalCents();

        $this->assertCount(2, DB::getQueryLog());
    }
}
