<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CheckoutRefusal;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Page;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Services\Checkout\InsufficientStock;
use App\Services\Checkout\OrderStock;
use App\Settings\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CheckoutScenario;
use Tests\TestCase;

/**
 * Checkout turns a cart into an order and starts its payment, or refuses
 * and leaves everything exactly as it was.
 */
class CheckoutTest extends TestCase
{
    use CheckoutScenario;
    use RefreshDatabase;

    private Product $router;

    private Product $cable;

    private ShippingMethod $pickup;

    private ShippingMethod $courier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->switchCheckoutOn();

        $this->router = Product::factory()->priced(1000)->inStock(10)->create(['name' => ['lt' => 'Maršrutizatorius', 'en' => 'Router']]);
        $this->cable = Product::factory()->priced(500)->inStock(5)->create(['name' => ['lt' => 'Kabelis', 'en' => 'Cable']]);
        $this->pickup = ShippingMethod::factory()->pickup()->create();
        $this->courier = ShippingMethod::factory()->named('DPD')->priced(450)->create();
    }

    private function filledCart(): Cart
    {
        return $this->cartWith([[$this->router, 2], [$this->cable, 1]]);
    }

    /**
     * Nothing was ordered: no order or payment, the stock and the cart as they were.
     */
    private function assertNothingChanged(Cart $cart, int $cartLines = 2): void
    {
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(10, $this->router->fresh()->stock_quantity);
        $this->assertSame(5, $this->cable->fresh()->stock_quantity);
        $this->assertSame($cartLines, $cart->items()->count());
    }

    private function assertRefused(TestResponse $response, CheckoutRefusal $reason): void
    {
        $response->assertRedirect(route('cart.show'));
        $response->assertSessionHasErrors(['checkout' => $reason->message()]);
    }

    public function test_the_checkout_page_gives_each_delivery_method_with_its_total_and_fingerprint(): void
    {
        $cart = $this->filledCart();
        $inactive = ShippingMethod::factory()->inactive()->create();

        $response = $this->visitorWith($cart)->get('/checkout');

        $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Checkout', false)
            ->where('cart.totalInclVatCents', 3025)
            ->has('shippingMethods', 2)
            ->where('shippingMethods.0.id', $this->pickup->id)
            ->where('shippingMethods.0.isPickup', true)
            ->where('shippingMethods.0.priceInclVatCents', 0)
            ->where('shippingMethods.0.totalInclVatCents', 3025)
            ->where('shippingMethods.0.fingerprint', $this->fingerprintFor($cart, $this->pickup))
            ->where('shippingMethods.1.id', $this->courier->id)
            ->where('shippingMethods.1.name', 'DPD')
            ->where('shippingMethods.1.priceInclVatCents', 545)
            ->where('shippingMethods.1.totalInclVatCents', 3570)
            ->where('shippingMethods.1.vatCents', 3570 - 2500 - 450)
            ->where('shippingMethods.1.fingerprint', $this->fingerprintFor($cart, $this->courier))
            ->where('seo.noindex', true)
        );

        $this->assertNotContains($inactive->id, collect($response->viewData('page')['props']['shippingMethods'])->pluck('id')->all());
    }

    public function test_the_checkout_page_says_where_to_post_and_links_the_rules_of_purchase_and_sale_and_the_privacy_policy(): void
    {
        $cart = $this->filledCart();
        Page::factory()->buyingAndSellingRules()->create();
        Page::factory()->privacyPolicy()->create();
        // The old terms page is not what checkout links to any more.
        Page::factory()->termsAndConditions()->create();

        $this->visitorWith($cart)->get('/checkout')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('submitUrl', '/checkout')
            ->where('rulesUrl', route('pages.show', ['slug' => 'pirkimo-pardavimo-taisyklės'], false))
            ->where('privacyUrl', '/privatumo-politika')
            ->missing('termsUrl')
        );
    }

    public function test_the_links_are_left_out_while_the_shop_has_not_made_those_pages(): void
    {
        $cart = $this->filledCart();

        $this->visitorWith($cart)->get('/checkout')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rulesUrl', null)
            ->where('privacyUrl', null)
        );
    }

    public function test_the_checkout_page_sends_a_cart_that_cannot_be_ordered_back_to_the_cart(): void
    {
        $this->get('/checkout')->assertRedirect(route('cart.show'))->assertSessionHasErrors(['checkout' => CheckoutRefusal::EmptyCart->message()]);

        $this->router->update(['stock_quantity' => 1]);
        $this->visitorWith($this->filledCart())->get('/checkout')->assertRedirect(route('cart.show'))
            ->assertSessionHasErrors(['checkout' => CheckoutRefusal::LinesUnavailable->message()]);
    }

    public function test_an_order_is_placed_from_what_the_page_gave(): void
    {
        $cart = $this->filledCart();

        $page = $this->visitorWith($cart)->get('/checkout');
        $methods = AssertableInertia::fromTestResponse($page)->toArray()['props']['shippingMethods'];
        $courier = collect($methods)->firstWhere('id', $this->courier->id);

        $response = $this->visitorWith($cart)->post('/checkout', [
            ...$this->checkoutForm($cart, $this->courier),
            'fingerprint' => $courier['fingerprint'],
            'note' => 'Call before delivering.',
        ]);

        $order = Order::query()->sole();
        $response->assertRedirect(route('orders.show', ['order' => $order->uuid]));

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertMatchesRegularExpression('/^AS-[0-9A-HJKMNP-TV-Z]{8}$/', $order->number);
        $this->assertSame('lt', $order->locale);
        $this->assertSame(21, $order->vat_rate_percent);
        $this->assertSame(2500, $order->subtotal_excl_vat_cents);
        $this->assertSame(450, $order->shipping_excl_vat_cents);
        $this->assertSame(545, $order->shipping_incl_vat_cents);
        $this->assertSame(3570, $order->total_incl_vat_cents);
        $this->assertSame(3570 - 2500 - 450, $order->vat_cents);
        $this->assertSame('DPD', $order->shipping_label);
        $this->assertFalse($order->shipping_is_pickup);
        $this->assertSame('Gedimino pr. 1, Vilnius', $order->delivery_address);
        $this->assertSame('Jonas Jonaitis', $order->customer_name);
        $this->assertSame('jonas@example.test', $order->customer_email);
        $this->assertSame('Call before delivering.', $order->customer_note);
        $this->assertNotNull($order->terms_accepted_at);
        $this->assertTrue($order->shippingMethod->is($this->courier));
        $this->assertEqualsWithDelta(now()->addMinutes(15)->getTimestamp(), $order->payment_expires_at->getTimestamp(), 5);

        $lines = $order->lines()->orderBy('product_id')->get();
        $this->assertSame(['Maršrutizatorius', 'Kabelis'], $lines->pluck('product_name')->all());
        $this->assertSame([2, 1], $lines->pluck('quantity')->all());
        $this->assertSame([1210, 605], $lines->pluck('unit_price_incl_vat_cents')->all());
        $this->assertSame([2420, 605], $lines->pluck('line_total_incl_vat_cents')->all());

        $this->assertSame(8, $this->router->fresh()->stock_quantity);
        $this->assertSame(4, $this->cable->fresh()->stock_quantity);
        $this->assertSame(0, $cart->items()->count());
        $this->assertModelExists($cart);

        $payment = $order->payments()->sole();
        $this->assertSame('fake', $payment->provider);
        $this->assertNotNull($payment->provider_reference);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(3570, $payment->amount_cents);
        $this->assertSame(route('orders.show', ['order' => $order->uuid]), $payment->redirect_url);
    }

    public function test_the_provider_is_asked_for_the_orders_own_total_number_and_window(): void
    {
        $cart = $this->filledCart();

        $this->visitorWith($cart)->post('/checkout', $this->checkoutForm($cart, $this->courier));

        $order = Order::query()->sole();
        $request = $this->gateway()->lastRequest;

        $this->assertSame($order->number, $request->orderNumber);
        $this->assertSame(3570, $request->amountCents);
        $this->assertSame('EUR', $request->currency);
        $this->assertSame('jonas@example.test', $request->customerEmail);
        $this->assertSame('lt', $request->locale);
        $this->assertSame(15, $request->windowMinutes);
        $this->assertSame(route('payments.callback', ['provider' => 'fake']), $request->callbackUrl);
        $this->assertSame(route('orders.show', ['order' => $order->uuid]), $request->returnUrl);
    }

    public function test_nothing_the_browser_sends_about_money_or_status_is_used(): void
    {
        $cart = $this->filledCart();

        $this->visitorWith($cart)->post('/checkout', [
            ...$this->checkoutForm($cart, $this->courier),
            'total_incl_vat_cents' => 1,
            'subtotal_excl_vat_cents' => 1,
            'shipping_excl_vat_cents' => 0,
            'price' => 1,
            'status' => 'paid',
            'paid_at' => now()->toDateTimeString(),
            'uuid' => 'chosen-by-the-browser',
            'number' => 'AS-CHOSEN00',
            'lines' => [['product_id' => $this->router->id, 'quantity' => 1, 'unit_price_incl_vat_cents' => 1]],
        ]);

        $order = Order::query()->sole();

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertNull($order->paid_at);
        $this->assertSame(3570, $order->total_incl_vat_cents);
        $this->assertTrue(Str::isUuid($order->uuid));
        $this->assertNotSame('AS-CHOSEN00', $order->number);
        $this->assertSame(2, $order->lines()->count());
    }

    public function test_pickup_needs_no_address_and_drops_one_that_is_sent(): void
    {
        $cart = $this->filledCart();

        $this->visitorWith($cart)
            ->post('/checkout', $this->checkoutForm($cart, $this->pickup, ['delivery_address' => 'Somewhere else 1']))
            ->assertSessionHasNoErrors();

        $order = Order::query()->sole();
        $this->assertTrue($order->shipping_is_pickup);
        $this->assertNull($order->delivery_address);
        $this->assertSame(0, $order->shipping_excl_vat_cents);
        $this->assertSame(3025, $order->total_incl_vat_cents);
        $this->assertStringContainsString('Rudmenos g. 5-7', $order->shipping_details);
    }

    public function test_an_order_is_kept_in_the_language_it_was_placed_in(): void
    {
        $cart = $this->filledCart();

        // A real /en URL cannot be requested in tests (see HomeTest).
        app()->setLocale('en');

        $this->visitorWith($cart)->post('/checkout', $this->checkoutForm($cart, $this->pickup));

        $order = Order::query()->sole();
        $this->assertSame('en', $order->locale);
        $this->assertSame('Pickup at the store', $order->shipping_label);
        $this->assertEqualsCanonicalizing(['Router', 'Cable'], $order->lines()->pluck('product_name')->all());
        $this->assertSame('en', $this->gateway()->lastRequest->locale);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidForms(): array
    {
        return [
            'no name' => [['name' => ''], 'name'],
            'no email' => [['email' => ''], 'email'],
            'bad email' => [['email' => 'not-an-email'], 'email'],
            'no phone' => [['phone' => ''], 'phone'],
            'phone with letters' => [['phone' => 'call me'], 'phone'],
            'no delivery method' => [['shipping_method_id' => null], 'shipping_method_id'],
            'unknown delivery method' => [['shipping_method_id' => 999_999], 'shipping_method_id'],
            'terms not accepted' => [['accept_terms' => false], 'accept_terms'],
            'terms missing' => [['accept_terms' => null], 'accept_terms'],
            'no fingerprint' => [['fingerprint' => null], 'fingerprint'],
            'short fingerprint' => [['fingerprint' => 'abc'], 'fingerprint'],
            'note too long' => [['note' => str_repeat('x', 501)], 'note'],
            'name too long' => [['name' => str_repeat('x', 151)], 'name'],
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    #[DataProvider('invalidForms')]
    public function test_an_invalid_form_is_refused_with_the_field_to_fix(array $changes, string $field): void
    {
        $cart = $this->filledCart();

        $this->visitorWith($cart)
            ->post('/checkout', $this->checkoutForm($cart, $this->pickup, $changes))
            ->assertSessionHasErrors($field);

        $this->assertNothingChanged($cart);
    }

    public function test_a_courier_needs_an_address(): void
    {
        $cart = $this->filledCart();

        $this->visitorWith($cart)
            ->post('/checkout', $this->checkoutForm($cart, $this->courier, ['delivery_address' => '']))
            ->assertSessionHasErrors('delivery_address');

        $this->assertNothingChanged($cart);
    }

    public function test_a_method_that_was_switched_off_cannot_be_chosen(): void
    {
        $cart = $this->filledCart();
        $form = $this->checkoutForm($cart, $this->courier);
        $this->courier->update(['is_active' => false]);

        $this->visitorWith($cart)->post('/checkout', $form)->assertSessionHasErrors('shipping_method_id');

        $this->assertNothingChanged($cart);
    }

    public function test_an_empty_cart_is_refused(): void
    {
        $cart = Cart::factory()->create();

        $this->assertRefused(
            $this->visitorWith($cart)->post('/checkout', $this->checkoutForm($cart, $this->pickup)),
            CheckoutRefusal::EmptyCart,
        );
        $this->assertNothingChanged($cart, 0);
    }

    public function test_a_visitor_with_no_cart_is_refused(): void
    {
        $cart = $this->filledCart();

        $this->assertRefused($this->post('/checkout', $this->checkoutForm($cart, $this->pickup)), CheckoutRefusal::EmptyCart);
        $this->assertNothingChanged($cart);
    }

    public function test_a_line_that_lost_stock_is_refused_and_nothing_is_adjusted(): void
    {
        $cart = $this->filledCart();
        $form = $this->checkoutForm($cart, $this->pickup);
        $this->router->update(['stock_quantity' => 1]);

        $this->assertRefused($this->visitorWith($cart)->post('/checkout', $form), CheckoutRefusal::LinesUnavailable);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(1, $this->router->fresh()->stock_quantity);
        $this->assertSame(2, $cart->items()->where('product_id', $this->router->id)->value('quantity'));
    }

    public function test_a_product_that_was_switched_off_or_deleted_is_refused(): void
    {
        foreach ([fn () => $this->cable->update(['is_active' => false]), fn () => $this->cable->delete()] as $change) {
            $cart = $this->filledCart();
            $form = $this->checkoutForm($cart, $this->pickup);
            $change();

            $this->assertRefused($this->visitorWith($cart)->post('/checkout', $form), CheckoutRefusal::LinesUnavailable);
            $this->assertSame(0, Order::query()->count());

            $this->cable->restore();
            $this->cable->update(['is_active' => true]);
        }
    }

    public function test_a_price_that_changed_since_the_page_was_shown_is_refused(): void
    {
        $cart = $this->filledCart();
        $form = $this->checkoutForm($cart, $this->pickup);
        $this->router->update(['price_excl_vat_cents' => 1100]);

        $this->assertRefused($this->visitorWith($cart)->post('/checkout', $form), CheckoutRefusal::StaleCart);
        $this->assertNothingChanged($cart);
    }

    public function test_a_quantity_changed_in_another_tab_is_refused(): void
    {
        $cart = $this->filledCart();
        $form = $this->checkoutForm($cart, $this->pickup);
        $cart->items()->where('product_id', $this->cable->id)->update(['quantity' => 2]);

        $this->assertRefused($this->visitorWith($cart)->post('/checkout', $form), CheckoutRefusal::StaleCart);
        $this->assertNothingChanged($cart);
    }

    public function test_a_delivery_price_that_changed_is_refused(): void
    {
        $cart = $this->filledCart();
        $form = $this->checkoutForm($cart, $this->courier);
        $this->courier->update(['price_excl_vat_cents' => 600]);

        $this->assertRefused($this->visitorWith($cart)->post('/checkout', $form), CheckoutRefusal::StaleCart);
        $this->assertNothingChanged($cart);
    }

    public function test_a_vat_rate_that_changed_is_refused(): void
    {
        $cart = $this->filledCart();
        $form = $this->checkoutForm($cart, $this->pickup);
        config(['shop.vat_rate' => 9]);

        $this->assertRefused($this->visitorWith($cart)->post('/checkout', $form), CheckoutRefusal::StaleCart);
        $this->assertNothingChanged($cart);
    }

    public function test_a_fingerprint_for_another_delivery_method_or_a_made_up_one_is_refused(): void
    {
        $cart = $this->filledCart();

        $this->assertRefused(
            $this->visitorWith($cart)->post('/checkout', $this->checkoutForm($cart, $this->courier, ['fingerprint' => $this->fingerprintFor($cart, $this->pickup)])),
            CheckoutRefusal::StaleCart,
        );
        $this->assertRefused(
            $this->visitorWith($cart)->post('/checkout', $this->checkoutForm($cart, $this->courier, ['fingerprint' => str_repeat('a', 64)])),
            CheckoutRefusal::StaleCart,
        );
        $this->assertNothingChanged($cart);
    }

    public function test_the_last_units_going_to_someone_else_mid_checkout_rolls_everything_back(): void
    {
        $cart = $this->filledCart();
        $form = $this->checkoutForm($cart, $this->pickup);

        $line = new OrderLine(['product_id' => $this->router->id, 'quantity' => 2]);
        $this->app->instance(OrderStock::class, new class($line) extends OrderStock
        {
            public function __construct(private readonly OrderLine $orderLine) {}

            public function take(Order $order): void
            {
                throw new InsufficientStock($this->orderLine);
            }
        });

        $this->assertRefused($this->visitorWith($cart)->post('/checkout', $form), CheckoutRefusal::StockShort);
        $this->assertNothingChanged($cart);
    }

    public function test_when_the_provider_cannot_start_the_payment_nothing_is_kept(): void
    {
        $cart = $this->filledCart();
        $form = $this->checkoutForm($cart, $this->pickup);
        $this->gateway()->failNextInitiation();

        $response = $this->from('/checkout')->visitorWith($cart)->post('/checkout', $form);

        $response->assertRedirect('/checkout');
        $response->assertSessionHasErrors(['checkout' => CheckoutRefusal::PaymentUnavailable->message()]);
        $this->assertNothingChanged($cart);

        // And the visitor can simply try again.
        $this->visitorWith($cart)->post('/checkout', $form)->assertSessionHasNoErrors();
        $this->assertSame(1, Order::query()->count());
    }

    public function test_the_last_unit_goes_to_one_of_two_buyers(): void
    {
        $this->router->update(['stock_quantity' => 1]);
        $first = $this->cartWith([[$this->router, 1]]);
        $second = $this->cartWith([[$this->router, 1]]);
        $firstForm = $this->checkoutForm($first, $this->pickup);
        $secondForm = $this->checkoutForm($second, $this->pickup);

        $this->visitorWith($first)->post('/checkout', $firstForm)->assertSessionHasNoErrors();
        $this->assertRefused($this->visitorWith($second)->post('/checkout', $secondForm), CheckoutRefusal::LinesUnavailable);

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(0, $this->router->fresh()->stock_quantity);
        $this->assertSame(1, $second->items()->count());
    }

    public function test_submitting_twice_places_one_order(): void
    {
        $cart = $this->filledCart();
        $form = $this->checkoutForm($cart, $this->pickup);

        $this->visitorWith($cart)->post('/checkout', $form)->assertSessionHasNoErrors();
        $this->assertRefused($this->visitorWith($cart)->post('/checkout', $form), CheckoutRefusal::EmptyCart);

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(8, $this->router->fresh()->stock_quantity);
        $this->assertSame(4, $this->cable->fresh()->stock_quantity);
    }

    public function test_coming_back_to_checkout_from_the_payment_page_leads_to_the_order_that_waits(): void
    {
        $cart = $this->filledCart();
        $this->visitorWith($cart)->post('/checkout', $this->checkoutForm($cart, $this->pickup))->assertSessionHasNoErrors();
        $order = Order::query()->sole();

        // The browser's Back button: the cart is empty now, but the order can still be paid.
        $this->visitorWith($cart)->get('/checkout')->assertRedirect(route('orders.show', ['order' => $order->uuid]));
    }

    public function test_it_leads_nowhere_once_that_order_is_paid_or_its_window_closed(): void
    {
        $cart = $this->filledCart();
        $this->visitorWith($cart)->post('/checkout', $this->checkoutForm($cart, $this->pickup));
        $order = Order::query()->sole();

        $order->update(['payment_expires_at' => now()->subMinute()]);
        $this->visitorWith($cart)->get('/checkout')->assertRedirect(route('cart.show'));

        $order->update(['payment_expires_at' => now()->addMinutes(5)]);
        $order->transitionTo(OrderStatus::Paid);
        $this->visitorWith($cart)->get('/checkout')->assertRedirect(route('cart.show'))
            ->assertSessionHasErrors(['checkout' => CheckoutRefusal::EmptyCart->message()]);
    }

    public function test_someone_elses_order_is_never_offered(): void
    {
        $cart = $this->filledCart();
        $this->visitorWith($cart)->post('/checkout', $this->checkoutForm($cart, $this->pickup));
        $this->flushSession();

        // A different visitor with an empty cart, who never placed that order.
        $this->visitorWith(Cart::factory()->create())->get('/checkout')->assertRedirect(route('cart.show'));
    }

    public function test_checkout_is_not_there_until_it_is_switched_on(): void
    {
        $cart = $this->filledCart();
        $form = $this->checkoutForm($cart, $this->pickup);

        $settings = app(ShopSettings::class);
        $settings->checkoutEnabled = false;
        $settings->save();

        $this->visitorWith($cart)->get('/checkout')->assertNotFound();
        $this->visitorWith($cart)->post('/checkout', $form)->assertNotFound();
        $this->assertNothingChanged($cart);
    }

    public function test_checkout_is_not_there_while_the_shop_is_off(): void
    {
        $cart = $this->filledCart();

        $settings = app(ShopSettings::class);
        $settings->enabled = false;
        $settings->save();

        $this->visitorWith($cart)->get('/checkout')->assertNotFound();
        $this->visitorWith($cart)->post('/checkout', $this->checkoutForm($cart, $this->pickup))->assertNotFound();
    }

    public function test_cart_edits_do_not_use_up_the_checkout_allowance(): void
    {
        $cart = $this->filledCart();
        $line = $cart->items()->where('product_id', $this->cable->id)->sole();

        foreach (range(1, 15) as $edit) {
            $this->visitorWith($cart)->patch(route('cart.items.update', $line), ['quantity' => 1 + ($edit % 2)])->assertSessionHasNoErrors();
        }

        $this->visitorWith($cart)->post('/checkout', $this->checkoutForm($cart, $this->pickup, ['name' => '']))->assertSessionHasErrors('name');
    }

    public function test_checkout_attempts_are_throttled(): void
    {
        $cart = $this->filledCart();
        $form = $this->checkoutForm($cart, $this->pickup, ['name' => '']);

        foreach (range(1, 10) as $attempt) {
            $this->visitorWith($cart)->post('/checkout', $form)->assertSessionHasErrors('name');
        }

        $this->visitorWith($cart)->post('/checkout', $form)->assertStatus(429);
    }
}
