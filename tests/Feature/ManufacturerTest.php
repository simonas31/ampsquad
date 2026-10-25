<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManufacturerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manufacturer_names_are_unique(): void
    {
        Manufacturer::factory()->create(['name' => 'Hikvision']);

        $this->expectException(QueryException::class);

        Manufacturer::factory()->create(['name' => 'Hikvision']);
    }

    public function test_manufacturer_has_many_products(): void
    {
        $manufacturer = Manufacturer::factory()->create();
        $product = Product::factory()->create(['manufacturer_id' => $manufacturer->id]);

        $this->assertTrue($product->manufacturer->is($manufacturer));
        $this->assertSame([$product->id], $manufacturer->products->pluck('id')->all());
    }

    public function test_deleting_a_manufacturer_keeps_its_products(): void
    {
        $manufacturer = Manufacturer::factory()->create();
        $product = Product::factory()->create(['manufacturer_id' => $manufacturer->id]);

        $manufacturer->delete();

        $this->assertNull($product->fresh()->manufacturer_id);
    }
}
