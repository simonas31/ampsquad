<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_has_translated_name_and_a_slug_per_locale(): void
    {
        $category = ProductCategory::factory()->create([
            'name' => ['lt' => 'Tinklo įranga', 'en' => 'Tinklo įranga'],
        ])->fresh();

        $this->assertSame('Tinklo įranga', $category->getTranslation('name', 'lt'));
        $this->assertSame('tinklo-iranga', $category->getTranslation('slug', 'lt'));
        $this->assertSame('tinklo-iranga', $category->getTranslation('slug', 'en'));
    }

    public function test_categories_form_a_tree(): void
    {
        $root = ProductCategory::factory()->create();
        $child = ProductCategory::factory()->childOf($root)->create();

        $this->assertTrue($child->parent->is($root));
        $this->assertSame([$child->id], $root->children->pluck('id')->all());
    }

    public function test_same_name_under_different_parents_gets_a_distinct_slug(): void
    {
        $name = ['lt' => 'IP kameros', 'en' => 'IP kameros'];

        $parent = ProductCategory::factory()->create(['name' => $name]);
        $child = ProductCategory::factory()->childOf($parent)->create(['name' => $name]);

        $this->assertSame('ip-kameros', $parent->getTranslation('slug', 'lt'));
        $this->assertSame('ip-kameros-2', $child->getTranslation('slug', 'lt'));
    }

    public function test_database_enforces_unique_slugs_per_locale(): void
    {
        $first = ProductCategory::factory()->create();

        $this->expectException(QueryException::class);

        ProductCategory::factory()->create(['slug' => $first->getTranslations('slug')]);
    }

    public function test_path_labels_spell_out_the_whole_path_of_every_category(): void
    {
        $top = ProductCategory::factory()->create(['name' => ['lt' => 'Tinklo įranga', 'en' => 'Tinklo įranga']]);
        $mid = ProductCategory::factory()->childOf($top)->create(['name' => ['lt' => 'Komutatoriai', 'en' => 'Komutatoriai']]);
        $leaf = ProductCategory::factory()->childOf($mid)->create(['name' => ['lt' => 'PoE', 'en' => 'PoE']]);

        $this->assertSame([
            $top->id => 'Tinklo įranga',
            $mid->id => 'Tinklo įranga › Komutatoriai',
            $leaf->id => 'Tinklo įranga › Komutatoriai › PoE',
        ], ProductCategory::pathLabels());
    }

    public function test_ids_with_descendants_covers_the_whole_subtree_and_nothing_else(): void
    {
        $top = ProductCategory::factory()->create();
        $child = ProductCategory::factory()->childOf($top)->create();
        $grandchild = ProductCategory::factory()->childOf($child)->create();
        $sibling = ProductCategory::factory()->childOf($top)->create();
        $unrelated = ProductCategory::factory()->create();

        $this->assertEqualsCanonicalizing(
            [$top->id, $child->id, $grandchild->id, $sibling->id],
            ProductCategory::idsWithDescendants($top->id),
        );
        $this->assertEqualsCanonicalizing([$child->id, $grandchild->id], ProductCategory::idsWithDescendants($child->id));
        $this->assertSame([$unrelated->id], ProductCategory::idsWithDescendants($unrelated->id));
    }

    public function test_deleting_a_category_keeps_its_products_and_children(): void
    {
        $parent = ProductCategory::factory()->create();
        $child = ProductCategory::factory()->childOf($parent)->create();
        $product = Product::factory()->create(['product_category_id' => $parent->id]);

        $parent->delete();

        $this->assertNull($product->fresh()->product_category_id);
        $this->assertNull($child->fresh()->parent_id);
    }
}
