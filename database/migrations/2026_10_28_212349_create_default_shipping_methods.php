<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The delivery price of the couriers, without VAT: 3.00 EUR with the 21%
     * VAT the shop charges (SHOP_VAT_RATE), as nearly as whole cents allow.
     * A starting point: the real prices are set in the admin panel, under
     * Shop → Shipping methods.
     */
    private const int COURIER_PRICE_EXCL_VAT_CENTS = 248;

    private const string COURIER_DESCRIPTION_LT = 'Pristatome per 2-3 darbo dienas nuo apmokėjimo gavimo, jei užsakymo metu nenurodyta kitaip.';

    private const string COURIER_DESCRIPTION_EN = 'Delivered within 2-3 business days of receipt of payment, unless otherwise specified during the order.';

    /**
     * Run the migrations.
     *
     * Data only (the table is made by an earlier migration). Tests start from
     * an empty table, so they build their own methods; this is covered by
     * ShippingMethodDefaultsTest, which calls createDefaults() itself.
     */
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $this->createDefaults();
    }

    /**
     * Creates the pickup point and the couriers, unless the shop already has
     * shipping methods of its own: running this after an admin set theirs up
     * must not add to them.
     */
    public function createDefaults(): void
    {
        if (DB::table('shipping_methods')->exists()) {
            return;
        }

        $now = now();

        DB::table('shipping_methods')->insert(array_map(
            fn (array $method): array => [
                'name' => json_encode($method['name'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'description' => json_encode($method['description'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'price_excl_vat_cents' => $method['price'],
                'is_pickup' => $method['pickup'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $this->defaults(),
        ));
    }

    /**
     * Reverse the migrations: removes the methods this created, by name.
     * Orders keep their own copy of the method they used, so none are lost.
     */
    public function down(): void
    {
        foreach ($this->defaults() as $method) {
            DB::table('shipping_methods')->where('name->lt', $method['name']['lt'])->delete();
        }
    }

    /**
     * @return list<array{name: array{lt: string, en: string}, description: array{lt: string, en: string}, price: int, pickup: bool}>
     */
    private function defaults(): array
    {
        $courier = [
            'description' => ['lt' => self::COURIER_DESCRIPTION_LT, 'en' => self::COURIER_DESCRIPTION_EN],
            'price' => self::COURIER_PRICE_EXCL_VAT_CENTS,
            'pickup' => false,
        ];

        return [
            [
                'name' => ['lt' => 'Atsiėmimas parduotuvėje', 'en' => 'Pickup at the store'],
                'description' => [
                    'lt' => 'Rudmenos g. 5-7, Kaunas. Atsiėmimo laiką suderiname iš anksto.',
                    'en' => 'Rudmenos g. 5-7, Kaunas. We agree the pickup time with you beforehand.',
                ],
                'price' => 0,
                'pickup' => true,
            ],
            ['name' => ['lt' => 'LP Express', 'en' => 'LP Express'], ...$courier],
            ['name' => ['lt' => 'DPD', 'en' => 'DPD'], ...$courier],
            ['name' => ['lt' => 'Omniva', 'en' => 'Omniva'], ...$courier],
            ['name' => ['lt' => 'Lietuvos paštas', 'en' => 'Lithuania Post'], ...$courier],
        ];
    }
};
