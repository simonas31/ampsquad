<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CatalogImport;
use App\Models\Product;
use App\Services\Catalog\CatalogImporter;
use App\Services\Catalog\CatalogRowParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * A realistic few thousand products for trying out pagination, search,
 * filters and the cart, made the way real ones arrive: a catalog file run
 * through the importer. The data is the same on every run.
 *
 * It also demonstrates the rule that an import never changes what the shop
 * manages. After the first import it plays the admin (restocks some products,
 * sells others out, switches some off), then imports a second file in which
 * every product has a different stock and many have new names and prices, and
 * checks that only the catalog fields moved.
 */
class CatalogDemoSeeder extends Seeder
{
    private const int PRODUCT_COUNT = 5000;

    private const int NEW_IN_SECOND_FILE = 50;

    private const int SEED = 20261025;

    /**
     * @var list<array{path: list<string>, noun: string, minCents: int, maxCents: int, features: list<string>}>
     */
    private const array LEAVES = [
        ['path' => ['Tinklo įranga', 'Komutatoriai'], 'noun' => 'komutatorius', 'minCents' => 1500, 'maxCents' => 45000, 'features' => ['8 prievadų valdomas', '16 prievadų PoE+', '24 prievadų Gigabit', '5 prievadų nevaldomas']],
        ['path' => ['Tinklo įranga', 'Maršrutizatoriai'], 'noun' => 'maršrutizatorius', 'minCents' => 2500, 'maxCents' => 38000, 'features' => ['Wi-Fi 6 dviejų juostų', 'Gigabit WAN', 'VPN palaikymas', 'LTE modemas']],
        ['path' => ['Tinklo įranga', 'Wi-Fi prieigos taškai'], 'noun' => 'prieigos taškas', 'minCents' => 3000, 'maxCents' => 30000, 'features' => ['montuojamas lubose', 'PoE maitinimas', 'Wi-Fi 6E', 'lauko']],
        ['path' => ['Vaizdo stebėjimas', 'IP kameros', 'Lauko kameros'], 'noun' => 'lauko IP kamera', 'minCents' => 4500, 'maxCents' => 52000, 'features' => ['4 MP', '8 MP su naktiniu matymu', 'su mikrofonu', 'PTZ']],
        ['path' => ['Vaizdo stebėjimas', 'IP kameros', 'Patalpų kameros'], 'noun' => 'patalpų IP kamera', 'minCents' => 2500, 'maxCents' => 24000, 'features' => ['2 MP', '4 MP su judesio sekimu', 'Wi-Fi', 'kupolinė']],
        ['path' => ['Vaizdo stebėjimas', 'Vaizdo registratoriai'], 'noun' => 'vaizdo registratorius', 'minCents' => 6000, 'maxCents' => 85000, 'features' => ['8 kanalų', '16 kanalų', '32 kanalų su PoE', '4 kanalų']],
        ['path' => ['Kabeliai ir jungtys', 'Tinklo kabeliai'], 'noun' => 'tinklo kabelis', 'minCents' => 300, 'maxCents' => 22000, 'features' => ['Cat6 UTP', 'Cat6A FTP', 'lauko Cat5e', 'Cat7 S/FTP']],
        ['path' => ['Kabeliai ir jungtys', 'Optiniai kabeliai'], 'noun' => 'optinis kabelis', 'minCents' => 800, 'maxCents' => 40000, 'features' => ['vienmodis', 'daugiamodis OM3', 'lauko', 'su LC jungtimis']],
        ['path' => ['Energija', 'Nepertraukiamo maitinimo šaltiniai'], 'noun' => 'nepertraukiamo maitinimo šaltinis', 'minCents' => 5500, 'maxCents' => 120000, 'features' => ['650 VA', '1500 VA', '3000 VA stovo', 'su LCD ekranu']],
        ['path' => ['Energija', 'Saulės elektrinės', 'Inverteriai'], 'noun' => 'inverteris', 'minCents' => 35000, 'maxCents' => 320000, 'features' => ['5 kW hibridinis', '10 kW trifazis', '3 kW tinklo', '8 kW su baterijos valdymu']],
        ['path' => ['Energija', 'Saulės elektrinės', 'Saulės moduliai'], 'noun' => 'saulės modulis', 'minCents' => 9000, 'maxCents' => 28000, 'features' => ['410 W', '450 W juodas rėmas', '550 W dvipusis', '200 W lankstus']],
        ['path' => ['Elektromobilių įkrovimas', 'Įkrovimo stotelės'], 'noun' => 'įkrovimo stotelė', 'minCents' => 45000, 'maxCents' => 180000, 'features' => ['7,4 kW', '11 kW su Wi-Fi', '22 kW su apskaita', 'sieninė su kabeliu']],
        ['path' => ['Elektromobilių įkrovimas', 'Įkrovimo kabeliai'], 'noun' => 'įkrovimo kabelis', 'minCents' => 8000, 'maxCents' => 45000, 'features' => ['Type 2, 5 m', 'Type 2, 7,5 m', 'trifazis 11 kW', 'spiralinis']],
        ['path' => ['Montavimo medžiagos', 'Spintos ir stovai'], 'noun' => 'telekomunikacijų spinta', 'minCents' => 6000, 'maxCents' => 140000, 'features' => ['9U sieninė', '12U sieninė', '42U grindinė', '19" lentyna']],
        ['path' => ['Montavimo medžiagos', 'Kronšteinai'], 'noun' => 'kronšteinas', 'minCents' => 400, 'maxCents' => 6500, 'features' => ['kamerai', 'lauko', 'sieninis', 'kampinis']],
        ['path' => ['Apsauga', 'Domofonai'], 'noun' => 'domofonas', 'minCents' => 4500, 'maxCents' => 65000, 'features' => ['su vaizdu', 'IP', 'dviejų abonentų', 'su kortelių skaitytuvu']],
    ];

    /**
     * @var list<string>
     */
    private const array BRANDS = [
        'Cudy', 'TP-Link', 'Ubiquiti', 'Hikvision', 'Dahua', 'Mikrotik', 'Schneider Electric', 'ABB', 'APC', 'Eaton',
        'Huawei', 'SMA', 'Fronius', 'Victron', 'Wallbox', 'Easee', 'Zaptec', 'Legrand', 'Rittal', 'Siemens',
        'Hager', 'Axis', 'Netgear', 'D-Link', 'Reolink',
    ];

    private Randomizer $random;

    public function run(CatalogImporter $importer): void
    {
        $this->random = new Randomizer(new Mt19937(self::SEED));

        $rows = $this->catalogRows();

        $first = $this->import($importer, $rows);
        $this->command?->info("Imported {$first->created_count} demo products.");

        $this->playTheAdmin();
        $shopOwned = $this->shopOwnedState();

        $first = $this->import($importer, $this->secondFile($rows));
        $this->command?->info(sprintf(
            'Re-imported a changed file: %d created, %d updated, %d unchanged.',
            $first->created_count,
            $first->updated_count,
            $first->unchanged_count,
        ));

        $this->assertShopOwnedStateIsKept($shopOwned);
        $this->command?->info('Stock and active state set in the shop survived the re-import.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalogRows(): array
    {
        $rows = [];

        for ($number = 1; $number <= self::PRODUCT_COUNT; $number++) {
            $rows[] = $this->catalogRow($number);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogRow(int $number): array
    {
        $leaf = self::LEAVES[$this->random->getInt(0, count(self::LEAVES) - 1)];
        $brand = self::BRANDS[$this->random->getInt(0, count(self::BRANDS) - 1)];
        $feature = $leaf['features'][$this->random->getInt(0, count($leaf['features']) - 1)];
        $model = $this->random->getBytesFromString('ABCDEFGHJKLMNPQRSTUVWXYZ', 2).$this->random->getInt(100, 9999);
        $cents = intdiv($this->random->getInt($leaf['minCents'], $leaf['maxCents']), 100) * 100 + [0, 49, 90, 99][$this->random->getInt(0, 3)];

        return [
            'sku' => sprintf('%s-%05d', strtoupper(substr(Str::ascii($brand), 0, 3)), $number),
            'name' => "{$brand} {$model} {$leaf['noun']}",
            'category' => $leaf['path'],
            'excerpt' => Str::ucfirst("{$leaf['noun']}, {$feature}."),
            'description' => implode("\n", [
                Str::ucfirst("{$leaf['noun']} {$brand} {$model}, {$feature}."),
                'Gamintojas: '.$brand,
                'Garantija: '.[12, 24, 36][$this->random->getInt(0, 2)].' mėn.',
            ]),
            'price' => sprintf('%d.%02d', intdiv($cents, 100), $cents % 100),
            'stock' => $this->random->getInt(1, 8) === 1 ? 0 : $this->random->getInt(1, 250),
            'image_url' => $this->random->getInt(1, 10) <= 7 ? "https://picsum.photos/seed/{$number}/800/600" : null,
            'manufacturer' => $brand,
            'ean' => $this->random->getInt(1, 5) === 1 ? null : '590'.str_pad((string) $number, 10, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * The same catalog a while later: every stock differs, every tenth
     * product is renamed and repriced, and a few products are new.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function secondFile(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $rows[$index]['stock'] = 999;

            if ($index % 10 === 0) {
                $rows[$index]['name'] .= ' (v2)';
                $cents = intdiv(CatalogRowParser::priceToCents($row['price']) * 11, 10);
                $rows[$index]['price'] = sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
            }
        }

        for ($number = self::PRODUCT_COUNT + 1; $number <= self::PRODUCT_COUNT + self::NEW_IN_SECOND_FILE; $number++) {
            $rows[] = [...$this->catalogRow($number), 'stock' => 20];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function import(CatalogImporter $importer, array $rows): CatalogImport
    {
        $path = 'imports/'.Str::uuid().'.json';

        Storage::disk('local')->put($path, json_encode(['schema_version' => 1, 'products' => $rows], JSON_THROW_ON_ERROR));

        $import = CatalogImport::query()->create([
            'original_filename' => 'demo-catalog.json',
            'file_path' => $path,
        ]);

        $importer->import($import);

        return $import->refresh();
    }

    /**
     * What an admin does over time: restocks some products, sells out
     * others, and switches some off.
     */
    private function playTheAdmin(): void
    {
        $products = Product::query()->whereNotNull('sku')->orderBy('id')->get(['id']);

        foreach ($products as $index => $product) {
            $changes = match (true) {
                $index % 20 === 3 => ['stock_quantity' => 7],
                $index % 25 === 0 => ['stock_quantity' => 0],
                $index % 12 === 5 => ['is_active' => false],
                default => [],
            };

            if ($changes !== []) {
                Product::query()->whereKey($product->id)->update($changes);
            }
        }
    }

    /**
     * @return array<string, array{int, bool}>
     */
    private function shopOwnedState(): array
    {
        return Product::query()
            ->whereNotNull('sku')
            ->get(['sku', 'stock_quantity', 'is_active'])
            ->mapWithKeys(fn (Product $product): array => [$product->sku => [$product->stock_quantity, $product->is_active]])
            ->all();
    }

    /**
     * @param  array<string, array{int, bool}>  $before
     */
    private function assertShopOwnedStateIsKept(array $before): void
    {
        $after = $this->shopOwnedState();

        foreach ($before as $sku => $state) {
            if ($after[$sku] !== $state) {
                throw new RuntimeException("The re-import changed the stock or active state of {$sku}.");
            }
        }
    }
}
