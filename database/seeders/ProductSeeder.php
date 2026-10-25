<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Demo products so the shop has something to show locally; real ones are
 * managed in the admin panel.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => ['lt' => 'Elektros instaliacijos patikra', 'en' => 'Electrical installation inspection'],
                'excerpt' => [
                    'lt' => 'Esamos elektros instaliacijos saugos ir būklės įvertinimas.',
                    'en' => 'A safety and condition check of your existing wiring.',
                ],
                'price_cents' => 8900,
                'stock_quantity' => 50,
            ],
            [
                'name' => ['lt' => 'Įkroviklio konsultacija', 'en' => 'EV charger consultation'],
                'excerpt' => [
                    'lt' => 'Pagalba parenkant ir planuojant elektromobilio įkroviklio montavimą.',
                    'en' => 'Help choosing and planning an EV charger installation.',
                ],
                'price_cents' => 4900,
                'stock_quantity' => 50,
            ],
            [
                'name' => ['lt' => 'Saulės elektrinės apžiūra', 'en' => 'Solar system check-up'],
                'excerpt' => [
                    'lt' => 'Veikiančios saulės elektrinės techninė apžiūra ir našumo patikra.',
                    'en' => 'A technical inspection and performance check of a working solar system.',
                ],
                'price_cents' => 12000,
                'stock_quantity' => 50,
            ],
        ];

        foreach ($products as $product) {
            Product::query()->create($product);
        }
    }
}
