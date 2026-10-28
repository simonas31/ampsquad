<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
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

    public function test_the_label_follows_the_current_language(): void
    {
        app()->setLocale('en');
        $this->assertSame('Awaiting payment', OrderStatus::PendingPayment->getLabel());

        app()->setLocale('lt');
        $this->assertSame('Laukiama apmokėjimo', OrderStatus::PendingPayment->getLabel());
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
