<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\PaymentOutcome;
use App\Events\OrderPaid;
use App\Events\PaymentNeedsAPerson;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Recipients\Pages\CreateRecipient;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderPaidMail;
use App\Mail\PaymentNeedsAPersonMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Recipient;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\CheckoutScenario;
use Tests\TestCase;

class OrderNotificationsTest extends TestCase
{
    use CheckoutScenario;
    use RefreshDatabase;

    private function paidOrder(string $locale = 'lt'): Order
    {
        $product = Product::factory()->priced(1000)->create(['name' => ['lt' => 'Maršrutizatorius', 'en' => 'Router'], 'sku' => 'RT-100']);

        return Order::factory()
            ->inLocale($locale)
            ->shippedBy(ShippingMethod::factory()->named('DPD')->priced(450)->create())
            ->withLine($product, 2)
            ->paid()
            ->create([
                'customer_name' => 'Jonas Jonaitis',
                'customer_email' => 'jonas@example.test',
                'customer_phone' => '+37060000000',
                'delivery_address' => 'Gedimino pr. 1, Vilnius',
                'customer_note' => 'Please call first.',
            ]);
    }

    public function test_a_paid_order_sends_the_customer_a_confirmation_in_their_language_and_the_subscribers_a_heads_up(): void
    {
        $subscribed = Recipient::factory()->create(['email' => 'orders@shop.test', 'notification_types' => [NotificationType::OrderPaid]]);
        Recipient::factory()->create(['email' => 'contact-only@shop.test', 'notification_types' => [NotificationType::ContactFormSubmitted]]);
        Recipient::factory()->create(['email' => 'off@shop.test', 'is_active' => false, 'notification_types' => [NotificationType::OrderPaid]]);
        $order = $this->paidOrder('en');
        Mail::fake();

        OrderPaid::dispatch($order);

        Mail::assertQueued(OrderConfirmationMail::class, 1);
        Mail::assertQueued(OrderConfirmationMail::class, fn (OrderConfirmationMail $mail): bool => $mail->hasTo('jonas@example.test') && $mail->locale === 'en');
        Mail::assertQueued(OrderPaidMail::class, 1);
        Mail::assertQueued(OrderPaidMail::class, fn (OrderPaidMail $mail): bool => $mail->hasTo($subscribed->email));
        Mail::assertNotQueued(OrderPaidMail::class, fn (OrderPaidMail $mail): bool => $mail->hasTo('contact-only@shop.test') || $mail->hasTo('off@shop.test'));
    }

    public function test_a_payment_that_needs_a_person_goes_to_the_same_subscribers(): void
    {
        Recipient::factory()->create(['email' => 'orders@shop.test', 'notification_types' => [NotificationType::OrderPaid]]);
        Recipient::factory()->create(['email' => 'contact-only@shop.test', 'notification_types' => [NotificationType::ContactFormSubmitted]]);
        $order = $this->paidOrder();
        Mail::fake();

        PaymentNeedsAPerson::dispatch($order, PaymentOutcome::DoublePayment);

        Mail::assertQueued(PaymentNeedsAPersonMail::class, 1);
        Mail::assertQueued(PaymentNeedsAPersonMail::class, fn (PaymentNeedsAPersonMail $mail): bool => $mail->hasTo('orders@shop.test') && $mail->outcome === PaymentOutcome::DoublePayment);
        Mail::assertNotQueued(OrderConfirmationMail::class);
    }

    public function test_a_confirmed_payment_sends_each_mail_once_even_when_the_provider_repeats_itself(): void
    {
        $this->switchCheckoutOn();
        Recipient::factory()->create(['notification_types' => [NotificationType::OrderPaid]]);
        $product = Product::factory()->inStock()->create();
        $order = Order::factory()->withLine($product)->create();
        Payment::factory()->for($order)->create(['provider_reference' => 'FAKE0001']);
        Mail::fake();
        $message = $this->providerMessage($order);

        $this->post(route('payments.callback', ['provider' => 'fake']), $message)->assertOk();
        $this->post(route('payments.callback', ['provider' => 'fake']), $message)->assertOk();
        $this->post(route('payments.callback', ['provider' => 'fake']), $this->providerMessage($order, ['payment_token' => 'tok-2']))->assertOk();

        Mail::assertQueued(OrderConfirmationMail::class, 1);
        Mail::assertQueued(OrderPaidMail::class, 1);
        Mail::assertQueued(PaymentNeedsAPersonMail::class, 1);
    }

    public function test_the_confirmation_is_in_lithuanian_with_the_orders_own_figures(): void
    {
        $order = $this->paidOrder('lt');
        app()->setLocale('lt');

        $html = (new OrderConfirmationMail($order))->render();

        $this->assertStringContainsString('Dėkojame už užsakymą, Jonas Jonaitis.', $html);
        $this->assertStringContainsString($order->number, $html);
        $this->assertStringContainsString('Maršrutizatorius', $html);
        $this->assertStringContainsString('Pristatymas: DPD', $html);
        $this->assertStringContainsString('Kaina su PVM', $html);
        $this->assertStringContainsString('Gedimino pr. 1, Vilnius', $html);
        $this->assertStringContainsString($order->uuid, $html);
        $this->assertStringContainsString('PVM 21%', $html);
        $this->assertStringNotContainsString("\u{2014}", $html);
        $this->assertSame('Jūsų užsakymas '.$order->number.' patvirtintas', (new OrderConfirmationMail($order))->envelope()->subject);
    }

    public function test_the_confirmation_is_in_english_and_links_to_the_order_in_english(): void
    {
        $order = $this->paidOrder('en');
        app()->setLocale('en');

        $html = (new OrderConfirmationMail($order))->render();

        $this->assertStringContainsString('Thank you for your order, Jonas Jonaitis.', $html);
        $this->assertStringContainsString('Router', $html);
        $this->assertStringContainsString('Delivery: DPD', $html);
        $this->assertStringContainsString('Price incl. VAT', $html);
        $this->assertStringContainsString('/en/orders/'.$order->uuid, $html);
        $this->assertSame('Your order '.$order->number.' is confirmed', (new OrderConfirmationMail($order))->envelope()->subject);
    }

    public function test_the_confirmation_says_the_pickup_details_and_leaves_out_an_address(): void
    {
        $product = Product::factory()->create();
        $order = Order::factory()->inLocale('en')->shippedBy(ShippingMethod::factory()->pickup()->create())->withLine($product)->paid()->create();
        app()->setLocale('en');

        $html = (new OrderConfirmationMail($order))->render();

        $this->assertStringContainsString('Pickup at the store', $html);
        $this->assertStringContainsString('Rudmenos g. 5-7', $html);
        $this->assertStringNotContainsString('Address:', $html);
    }

    public function test_the_heads_up_has_what_is_needed_to_start_on_the_order(): void
    {
        $order = $this->paidOrder();

        foreach (['lt' => 'Naujas apmokėtas užsakymas', 'en' => 'New paid order'] as $locale => $subject) {
            app()->setLocale($locale);
            $mail = new OrderPaidMail($order);
            $html = $mail->render();

            $this->assertStringContainsString("{$subject} {$order->number}", $mail->envelope()->subject);
            foreach (['Jonas Jonaitis', 'jonas@example.test', '+37060000000', 'Maršrutizatorius', 'RT-100', 'Gedimino pr. 1, Vilnius', 'Please call first.', 'DPD'] as $expected) {
                $this->assertStringContainsString($expected, $html, "{$locale}: {$expected}");
            }
            $this->assertStringContainsString(OrderResource::getUrl('view', ['record' => $order], panel: 'admin'), $html);
        }
    }

    public function test_each_problem_says_what_to_do_in_both_languages(): void
    {
        $order = $this->paidOrder();

        foreach (['lt', 'en'] as $locale) {
            app()->setLocale($locale);

            foreach ([PaymentOutcome::PaidAfterExpiry, PaymentOutcome::PaidAfterCancel, PaymentOutcome::DoublePayment, PaymentOutcome::AmountMismatch] as $outcome) {
                $mail = new PaymentNeedsAPersonMail($order, $outcome);
                $explanation = PaymentNeedsAPersonMail::explanationOf($outcome);

                $this->assertTrue($outcome->needsAPerson());
                $this->assertStringContainsString($order->number, $mail->envelope()->subject);
                $this->assertStringContainsString($explanation, strip_tags(html_entity_decode($mail->render())));
                $this->assertStringContainsString('OPAY', $explanation);
                $this->assertStringContainsString(OrderResource::getUrl('view', ['record' => $order], panel: 'admin'), $mail->render());
            }
        }

        app()->setLocale('lt');
        $this->assertStringContainsString('Grąžinkite', PaymentNeedsAPersonMail::explanationOf(PaymentOutcome::DoublePayment));
        app()->setLocale('en');
        $this->assertStringContainsString('Refund', PaymentNeedsAPersonMail::explanationOf(PaymentOutcome::DoublePayment));
    }

    public function test_an_outcome_with_no_explanation_of_its_own_gets_the_general_one(): void
    {
        foreach (['lt', 'en'] as $locale) {
            app()->setLocale($locale);

            $this->assertSame(__('mail.payment_problem.explanations.default'), PaymentNeedsAPersonMail::explanationOf(PaymentOutcome::Paid));
        }
    }

    public function test_only_the_outcomes_that_disagree_with_the_money_need_a_person(): void
    {
        $needing = collect(PaymentOutcome::cases())->filter(fn (PaymentOutcome $outcome): bool => $outcome->needsAPerson())->map->value->values()->all();

        $this->assertSame(['paid_after_expiry', 'paid_after_cancel', 'double_payment', 'amount_mismatch'], $needing);
    }

    public function test_the_admin_can_subscribe_a_recipient_to_paid_orders(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateRecipient::class)
            ->fillForm(['email' => 'orders@shop.test', 'is_active' => true, 'notification_types' => [NotificationType::OrderPaid->value]])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Recipient::query()->where('email', 'orders@shop.test')->sole()->notification_types->contains(NotificationType::OrderPaid));
        app()->setLocale('en');
        $this->assertSame('Paid order to review', NotificationType::OrderPaid->getLabel());
        app()->setLocale('lt');
        $this->assertSame('Apmokėtas užsakymas, kurį reikia peržiūrėti', NotificationType::OrderPaid->getLabel());
    }
}
