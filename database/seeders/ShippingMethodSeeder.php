<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

/**
 * Demo shipping methods and made-up prices so checkout has something to
 * offer locally; the real ones, with the real prices, are managed in the
 * admin panel.
 */
class ShippingMethodSeeder extends Seeder
{
    public function run(): void
    {
        ShippingMethod::factory()->pickup()->create();

        foreach ([['LP Express', 399], ['DPD', 450], ['Omniva', 349]] as [$name, $priceExclVatCents]) {
            ShippingMethod::factory()->named($name)->priced($priceExclVatCents)->create([
                'description' => [
                    'lt' => 'Pristatome į nurodytą adresą arba paštomatą per 2-3 darbo dienas nuo apmokėjimo.',
                    'en' => 'Delivered to the address or parcel locker you give, within 2-3 business days of payment.',
                ],
            ]);
        }

        ShippingMethod::factory()
            ->create([
                'name' => ['lt' => 'Lietuvos paštas', 'en' => 'Lithuania Post'],
                'price_excl_vat_cents' => 299,
            ]);
    }
}
