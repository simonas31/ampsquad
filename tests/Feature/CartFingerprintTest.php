<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ShippingMethod;
use App\Services\Checkout\CartFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CheckoutScenario;
use Tests\TestCase;

class CartFingerprintTest extends TestCase
{
    use CheckoutScenario;
    use RefreshDatabase;

    public function test_it_is_the_same_for_the_same_cart_however_the_lines_are_ordered(): void
    {
        $a = Product::factory()->inStock()->create();
        $b = Product::factory()->inStock()->create();
        $method = ShippingMethod::factory()->create();
        $cart = $this->cartWith([[$a, 1], [$b, 2]]);

        $forward = $cart->items()->with('product')->orderBy('id')->get();
        $backward = $cart->items()->with('product')->orderByDesc('id')->get();

        $this->assertSame(
            CartFingerprint::for($forward, $method),
            CartFingerprint::for($backward, $method),
        );
        $this->assertSame(64, strlen($this->fingerprintFor($cart, $method)));
    }

    public function test_anything_that_changes_what_the_visitor_pays_changes_it(): void
    {
        $product = Product::factory()->priced(1000)->inStock()->create();
        $method = ShippingMethod::factory()->priced(450)->create();
        $cart = $this->cartWith([[$product, 1]]);
        $before = $this->fingerprintFor($cart, $method);

        $changes = [
            'quantity' => fn () => $cart->items()->update(['quantity' => 2]),
            'price' => fn () => $product->update(['price_excl_vat_cents' => 1001]),
            'delivery price' => fn () => $method->update(['price_excl_vat_cents' => 451]),
            'vat rate' => fn () => config(['shop.vat_rate' => 9]),
        ];

        foreach ($changes as $what => $change) {
            $change();
            $this->assertNotSame($before, $this->fingerprintFor($cart, $method), "after the {$what} changed");

            // Put it back.
            $cart->items()->update(['quantity' => 1]);
            $product->update(['price_excl_vat_cents' => 1000]);
            $method->update(['price_excl_vat_cents' => 450]);
            config(['shop.vat_rate' => 21]);
            $this->assertSame($before, $this->fingerprintFor($cart, $method), "after the {$what} was put back");
        }
    }

    public function test_it_depends_on_the_delivery_method_chosen(): void
    {
        $cart = $this->cartWith([[Product::factory()->inStock()->create(), 1]]);

        $this->assertNotSame(
            $this->fingerprintFor($cart, ShippingMethod::factory()->create()),
            $this->fingerprintFor($cart, ShippingMethod::factory()->create()),
        );
    }

    public function test_it_cannot_be_worked_out_without_the_app_key(): void
    {
        $product = Product::factory()->inStock()->create();
        $method = ShippingMethod::factory()->create();
        $cart = $this->cartWith([[$product, 1]]);
        $withKey = $this->fingerprintFor($cart, $method);

        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);

        $this->assertNotSame($withKey, $this->fingerprintFor($cart, $method));
    }
}
