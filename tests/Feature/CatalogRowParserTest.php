<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Catalog\CatalogRowParser;
use App\Services\Catalog\InvalidCatalogRowException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogRowParserTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'KRDS1663ZJ',
            'name' => 'Hikvision kronšteinas DS-1663ZJ',
            'category' => ['Vaizdo stebėjimo sistemos', 'IP kameros'],
            'excerpt' => 'Kronšteinas kamerai',
            'description' => "Pirma eilutė\nAntra eilutė",
            'price' => '20.00',
            'stock' => 2,
            'image_url' => 'http://bkgrupe.lt/media/a.jpg',
            'manufacturer' => 'Hikvision',
            'ean' => '6954273658830',
        ], $overrides);
    }

    /**
     * @return list<string>
     */
    private function messagesFor(mixed $row): array
    {
        try {
            (new CatalogRowParser)->parse($row);
        } catch (InvalidCatalogRowException $exception) {
            return $exception->messages;
        }

        $this->fail('The row was accepted.');
    }

    public function test_a_complete_row_is_parsed(): void
    {
        $row = (new CatalogRowParser)->parse($this->row());

        $this->assertSame('KRDS1663ZJ', $row->sku);
        $this->assertSame('Hikvision kronšteinas DS-1663ZJ', $row->name);
        $this->assertSame(['Vaizdo stebėjimo sistemos', 'IP kameros'], $row->categoryPath);
        $this->assertSame('Kronšteinas kamerai', $row->excerpt);
        $this->assertSame("Pirma eilutė\nAntra eilutė", $row->description);
        $this->assertSame(2000, $row->priceCents);
        $this->assertSame(2, $row->stock);
        $this->assertSame('http://bkgrupe.lt/media/a.jpg', $row->imageUrl);
        $this->assertSame('Hikvision', $row->manufacturer);
        $this->assertSame('6954273658830', $row->ean);
    }

    public function test_only_sku_name_and_price_are_required(): void
    {
        $row = (new CatalogRowParser)->parse([
            'sku' => 'A1', 'name' => 'Minimal', 'price' => '0.99',
        ]);

        $this->assertNull($row->stock);
        $this->assertSame([], $row->categoryPath);
        $this->assertNull($row->excerpt);
        $this->assertNull($row->description);
        $this->assertNull($row->imageUrl);
        $this->assertNull($row->manufacturer);
        $this->assertNull($row->ean);
        $this->assertSame(99, $row->priceCents);
    }

    public function test_explicit_nulls_and_blank_strings_count_as_absent(): void
    {
        $row = (new CatalogRowParser)->parse($this->row([
            'category' => null, 'excerpt' => null, 'description' => '   ', 'image_url' => '',
            'manufacturer' => null, 'ean' => '', 'stock' => null,
        ]));

        $this->assertNull($row->stock);

        $this->assertSame([], $row->categoryPath);
        $this->assertNull($row->excerpt);
        $this->assertNull($row->description);
        $this->assertNull($row->imageUrl);
        $this->assertNull($row->manufacturer);
        $this->assertNull($row->ean);
    }

    public function test_surrounding_whitespace_is_trimmed(): void
    {
        $row = (new CatalogRowParser)->parse($this->row([
            'sku' => '  A1 ', 'name' => "  Name\n", 'category' => [' Top ', 'Leaf  '], 'manufacturer' => ' Acme ',
        ]));

        $this->assertSame('A1', $row->sku);
        $this->assertSame('Name', $row->name);
        $this->assertSame(['Top', 'Leaf'], $row->categoryPath);
        $this->assertSame('Acme', $row->manufacturer);
    }

    public function test_unknown_keys_are_ignored(): void
    {
        $row = (new CatalogRowParser)->parse($this->row(['weight' => 0.48, 'colour' => 'black']));

        $this->assertSame('KRDS1663ZJ', $row->sku);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function requiredFields(): array
    {
        return [
            'sku' => ['sku'],
            'name' => ['name'],
            'price' => ['price'],
        ];
    }

    #[DataProvider('requiredFields')]
    public function test_required_fields_must_be_present(string $field): void
    {
        $row = $this->row();
        unset($row[$field]);

        $this->assertNotSame([], $this->messagesFor($row));
    }

    public function test_whitespace_only_sku_and_name_count_as_missing(): void
    {
        $this->assertNotSame([], $this->messagesFor($this->row(['sku' => '   '])));
        $this->assertNotSame([], $this->messagesFor($this->row(['name' => "\n"])));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function validPrices(): array
    {
        return [
            'two decimals' => ['19.99', 1999],
            'one decimal' => ['19.5', 1950],
            'whole' => ['19', 1900],
            'smallest' => ['0.01', 1],
            'largest' => ['100000.00', 10_000_000],
            'leading zero' => ['0.50', 50],
        ];
    }

    #[DataProvider('validPrices')]
    public function test_price_is_converted_to_cents_exactly(string $price, int $cents): void
    {
        $this->assertSame($cents, (new CatalogRowParser)->parse($this->row(['price' => $price]))->priceCents);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidPrices(): array
    {
        return [
            'comma decimal' => ['19,99'],
            'text' => ['cheap'],
            'float' => [19.99],
            'integer' => [20],
            'zero' => ['0.00'],
            'above the limit' => ['100000.01'],
            'sub-cent' => ['1.999'],
            'negative' => ['-5.00'],
            'currency symbol' => ['€5.00'],
            'null' => [null],
        ];
    }

    #[DataProvider('invalidPrices')]
    public function test_malformed_or_out_of_range_prices_are_rejected(mixed $price): void
    {
        $this->assertNotSame([], $this->messagesFor($this->row(['price' => $price])));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidStock(): array
    {
        return [
            'negative' => [-1],
            'numeric string' => ['5'],
            'fraction' => [1.5],
            'too large' => [100_000_001],
        ];
    }

    #[DataProvider('invalidStock')]
    public function test_stock_must_be_a_non_negative_whole_number(mixed $stock): void
    {
        $this->assertNotSame([], $this->messagesFor($this->row(['stock' => $stock])));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidOptionalFields(): array
    {
        return [
            'short EAN' => [['ean' => '1234567']],
            'long EAN' => [['ean' => '123456789012345']],
            'EAN with letters' => [['ean' => '12345678AB']],
            'EAN as number' => [['ean' => 6954273658830]],
            'ftp image' => [['image_url' => 'ftp://example.com/a.jpg']],
            'image not a URL' => [['image_url' => 'a.jpg']],
            'category as string' => [['category' => 'Top/Leaf']],
            'category with empty segment' => [['category' => ['Top', '']]],
            'category with object' => [['category' => ['a' => 'Top']]],
            'category too deep' => [['category' => ['1', '2', '3', '4', '5', '6', '7', '8', '9']]],
            'SKU too long' => [['sku' => str_repeat('a', 65)]],
            'name too long' => [['name' => str_repeat('a', 256)]],
            'excerpt too long' => [['excerpt' => str_repeat('a', 256)]],
            'description too long' => [['description' => str_repeat('a', 5001)]],
            'manufacturer too long' => [['manufacturer' => str_repeat('a', 256)]],
            'image URL too long' => [['image_url' => 'http://example.com/'.str_repeat('a', 500)]],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidOptionalFields')]
    public function test_invalid_field_values_are_rejected(array $overrides): void
    {
        $this->assertNotSame([], $this->messagesFor($this->row($overrides)));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function markup(): array
    {
        return [
            'tag in the name' => ['name', '<b>Bold</b> cable'],
            'line break in the excerpt' => ['excerpt', 'First line<br>second line'],
            'self-closing break' => ['excerpt', 'First line<br/>second line'],
            'list in the description' => ['description', '<ul><li>Type: cable</li></ul>'],
            'wrapping div with attributes' => ['description', '<div data-content-type="html">Cable</div>'],
            'link' => ['description', 'See <a href="https://example.com">here</a>'],
            'table cell' => ['description', 'Voltage<td>AC 220V</td>'],
            'upper-case tag' => ['description', '<P>Cable</P>'],
            'named reference' => ['description', 'Fish &amp; chips'],
            'numeric reference' => ['description', 'Range -10 °C to &#43;55 °C'],
            'hex reference' => ['name', 'Plus &#x2B; sign'],
            'non-breaking space reference' => ['excerpt', 'Cable&nbsp;5 m'],
        ];
    }

    #[DataProvider('markup')]
    public function test_text_must_be_plain(string $field, string $text): void
    {
        $this->assertSame(
            [__('admin.catalog_import.errors.plain_text')],
            $this->messagesFor($this->row([$field => $text])),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function plainTextThatLooksLikeMarkup(): array
    {
        return [
            'less-than sign before a number' => ['Operating voltage <5 V'],
            'comparison' => ['3 < 5 and 7 > 2'],
            'ampersand' => ['Tom & Jerry'],
            'ampersand with digits' => ['Hi-PoE&24VAC;'],
            'plus sign' => ['Range -10 °C...+55 °C'],
            'unknown tag-like word' => ['Use <your-cable> here'],
            'quotes' => ['Cable "Cat6" 5\' long'],
        ];
    }

    #[DataProvider('plainTextThatLooksLikeMarkup')]
    public function test_plain_text_that_only_resembles_markup_is_accepted(string $text): void
    {
        $row = (new CatalogRowParser)->parse($this->row(['description' => $text]));

        $this->assertSame($text, $row->description);
    }

    public function test_every_problem_in_a_row_is_reported_at_once(): void
    {
        $messages = $this->messagesFor($this->row(['sku' => '', 'price' => 'free', 'stock' => -1]));

        $this->assertGreaterThanOrEqual(3, count($messages));
    }

    public function test_price_problems_use_the_catalog_wording(): void
    {
        $this->assertSame(
            [__('admin.catalog_import.errors.price_format')],
            $this->messagesFor($this->row(['price' => '19,99'])),
        );
        $this->assertSame(
            [__('admin.catalog_import.errors.price_range', ['max' => '100000.00'])],
            $this->messagesFor($this->row(['price' => '0.00'])),
        );
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function notObjects(): array
    {
        return [
            'string' => ['KRDS1663ZJ'],
            'null' => [null],
            'number' => [5],
            'list' => [['a', 'b']],
        ];
    }

    #[DataProvider('notObjects')]
    public function test_a_row_must_be_a_json_object(mixed $row): void
    {
        $this->assertSame([__('admin.catalog_import.errors.row_not_an_object')], $this->messagesFor($row));
    }
}
