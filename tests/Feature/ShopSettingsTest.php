<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Settings\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shop_is_disabled_until_an_admin_enables_it(): void
    {
        $this->assertFalse(app(ShopSettings::class)->enabled);
    }

    public function test_online_checkout_is_off_until_an_admin_switches_it_on(): void
    {
        $this->assertFalse(app(ShopSettings::class)->checkoutEnabled);

        $settings = app(ShopSettings::class);
        $settings->checkoutEnabled = true;
        $settings->save();

        $this->assertTrue(app(ShopSettings::class)->checkoutEnabled);
    }

    public function test_enabling_the_shop_is_persisted(): void
    {
        $settings = app(ShopSettings::class);
        $settings->enabled = true;
        $settings->save();

        $this->assertSame('true', $this->storedPayload());

        $settings->enabled = false;
        $settings->save();

        $this->assertSame('false', $this->storedPayload());
    }

    private function storedPayload(): string
    {
        return (string) DB::table('settings')
            ->where('group', 'shop')
            ->where('name', 'enabled')
            ->value('payload');
    }
}
