<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShippingMethod;
use Tests\TestCase;

class OrderEnumsTest extends TestCase
{
    private const array LOCALES = ['lt', 'en'];

    public function test_every_order_status_has_a_label_in_both_languages(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach (OrderStatus::cases() as $status) {
                $key = "admin.enums.order_status.{$status->value}";

                $this->assertNotSame($key, trans($key, [], $locale), "{$key} in {$locale}");
            }
        }
    }

    public function test_every_payment_status_has_a_label_in_both_languages(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach (PaymentStatus::cases() as $status) {
                $key = "admin.enums.payment_status.{$status->value}";

                $this->assertNotSame($key, trans($key, [], $locale), "{$key} in {$locale}");
            }
        }
    }

    public function test_every_shipping_method_has_a_label_in_both_languages_without_dashes(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach (ShippingMethod::cases() as $method) {
                $label = $method->labelIn($locale);

                $this->assertNotSame("shop.shipping.{$method->value}", $label, "{$method->value} in {$locale}");
                $this->assertStringNotContainsString("\u{2014}", $label);
                $this->assertStringNotContainsString("\u{2013}", $label);
            }
        }
    }

    public function test_the_label_follows_the_current_language(): void
    {
        app()->setLocale('en');
        $this->assertSame('Pickup at the store', ShippingMethod::Pickup->getLabel());
        $this->assertSame('Awaiting payment', OrderStatus::PendingPayment->getLabel());

        app()->setLocale('lt');
        $this->assertSame('Atsiėmimas parduotuvėje', ShippingMethod::Pickup->getLabel());
        $this->assertSame('Laukiama apmokėjimo', OrderStatus::PendingPayment->getLabel());
    }

    public function test_only_pickup_needs_no_delivery_address(): void
    {
        $withoutAddress = collect(ShippingMethod::cases())
            ->reject(fn (ShippingMethod $method): bool => $method->needsDeliveryAddress());

        $this->assertSame([ShippingMethod::Pickup], $withoutAddress->values()->all());
    }

    public function test_the_four_couriers_and_pickup_are_what_is_offered(): void
    {
        $this->assertSame(
            ['pickup', 'lp_express', 'dpd', 'omniva', 'lietuvos_pastas'],
            array_map(fn (ShippingMethod $method): string => $method->value, ShippingMethod::cases()),
        );
    }

    public function test_every_status_has_a_colour(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $this->assertNotEmpty($status->getColor());
        }

        foreach (PaymentStatus::cases() as $status) {
            $this->assertNotEmpty($status->getColor());
        }
    }
}
