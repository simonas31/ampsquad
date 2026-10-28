<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

/**
 * Shipping methods for a database that has none: the same set, at the same
 * prices, as the migration that creates them on a real one (which does not
 * run in tests). The real ones are managed in the admin panel.
 */
class ShippingMethodSeeder extends Seeder
{
    public function run(): void
    {
        if (ShippingMethod::query()->exists()) {
            return;
        }

        ShippingMethod::factory()->pickup()->create();

        foreach (['LP Express', 'DPD', 'Omniva'] as $name) {
            ShippingMethod::factory()->named($name)->priced(248)->create([
                'description' => [
                    'lt' => 'Pristatome į nurodytą adresą arba paštomatą per 2-3 darbo dienas nuo apmokėjimo.',
                    'en' => 'Delivered to the address or parcel locker you give, within 2-3 business days of payment.',
                ],
            ]);
        }

        ShippingMethod::factory()
            ->create([
                'name' => ['lt' => 'Lietuvos paštas', 'en' => 'Lithuania Post'],
                'price_excl_vat_cents' => 248,
            ]);
    }
}
