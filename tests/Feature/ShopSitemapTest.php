<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Settings\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ShopSitemapTest extends TestCase
{
    use RefreshDatabase;

    private function sitemap(bool $shopEnabled): string
    {
        $settings = app(ShopSettings::class);
        $settings->enabled = $shopEnabled;
        $settings->save();

        $path = public_path('sitemap.xml');
        $backup = is_file($path) ? file_get_contents($path) : null;

        try {
            Artisan::call('app:generate-sitemap');

            return (string) file_get_contents($path);
        } finally {
            if ($backup === null) {
                @unlink($path);
            } else {
                file_put_contents($path, $backup);
            }
        }
    }

    public function test_an_enabled_shop_lists_the_shop_and_its_active_products(): void
    {
        $product = Product::factory()->create(['slug' => ['lt' => 'įkroviklis', 'en' => 'charger']]);
        Product::factory()->inactive()->create(['slug' => ['lt' => 'paslėpta', 'en' => 'hidden']]);
        Product::factory()->create(['slug' => ['lt' => 'ištrinta', 'en' => 'deleted']])->delete();

        $xml = $this->sitemap(true);

        $this->assertStringContainsString('/shop</loc>', $xml);
        $this->assertStringContainsString('/shop/'.$product->getTranslation('slug', 'lt').'</loc>', $xml);
        $this->assertStringContainsString('href="'.url('/en/shop/charger').'"', $xml);
        $this->assertStringNotContainsString('hidden', $xml);
        $this->assertStringNotContainsString('deleted', $xml);
    }

    public function test_a_disabled_shop_is_left_out_of_the_sitemap(): void
    {
        Product::factory()->create(['slug' => ['lt' => 'įkroviklis', 'en' => 'charger']]);

        $xml = $this->sitemap(false);

        $this->assertStringNotContainsString('/shop', $xml);
    }
}
