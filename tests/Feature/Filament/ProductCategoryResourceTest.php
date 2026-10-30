<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\ProductCategories\Pages\CreateProductCategory;
use App\Filament\Resources\ProductCategories\Pages\EditProductCategory;
use App\Filament\Resources\ProductCategories\Pages\ListProductCategories;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Policies\ProductCategoryPolicy;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The shop's category tree as an admin edits it: finding categories,
 * creating and moving them, and the rules that keep the tree and the
 * catalog import intact.
 */
class ProductCategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    private function category(string $name, ?ProductCategory $parent = null, ?string $english = null): ProductCategory
    {
        return ProductCategory::factory()
            ->when($parent !== null, fn ($factory) => $factory->childOf($parent))
            ->create(['name' => ['lt' => $name, 'en' => $english ?? $name]]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validForm(array $overrides = []): array
    {
        return array_merge([
            'parent_id' => null,
            'name' => ['lt' => 'Domofonai', 'en' => 'Intercoms'],
        ], $overrides);
    }

    // -- access and list ---------------------------------------------------

    public function test_guests_cannot_reach_the_category_admin(): void
    {
        auth()->logout();

        $this->get('/admin/product-categories')->assertRedirect();
    }

    public function test_the_list_shows_every_category_alphabetically(): void
    {
        $zebra = $this->category('Zebra');
        $apple = $this->category('Ąžuolas');
        $mango = $this->category('Mango');

        Livewire::test(ListProductCategories::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$apple, $mango, $zebra], inOrder: true);
    }

    public function test_the_list_shows_the_parent_and_how_much_each_category_holds(): void
    {
        $network = $this->category('Tinklo įranga');
        $switches = $this->category('Komutatoriai', $network);
        Product::factory()->count(2)->create(['product_category_id' => $switches->id]);
        Product::factory()->create(['product_category_id' => $switches->id])->delete();

        Livewire::test(ListProductCategories::class)
            ->assertTableColumnStateSet('parent.name', 'Tinklo įranga', $switches)
            ->assertTableColumnStateSet('products_count', 3, $switches)
            ->assertTableColumnStateSet('children_count', 0, $switches)
            ->assertTableColumnStateSet('products_count', 0, $network)
            ->assertTableColumnStateSet('children_count', 1, $network);
    }

    public function test_search_ignores_case_and_accents_and_does_not_match_the_language_keys(): void
    {
        $cables = $this->category('Kabeliai ir laidai');
        $network = $this->category('Tinklo įranga');

        Livewire::test(ListProductCategories::class)
            ->searchTable('KABELIAI')
            ->assertCanSeeTableRecords([$cables])
            ->assertCanNotSeeTableRecords([$network])
            ->searchTable('iranga')
            ->assertCanSeeTableRecords([$network])
            ->assertCanNotSeeTableRecords([$cables])
            ->searchTable('lt')
            ->assertCanNotSeeTableRecords([$cables, $network]);
    }

    public function test_the_list_can_be_narrowed_to_the_children_of_one_category(): void
    {
        $network = $this->category('Tinklo įranga');
        $switches = $this->category('Komutatoriai', $network);
        $deeper = $this->category('PoE komutatoriai', $switches);
        $cables = $this->category('Kabeliai');

        Livewire::test(ListProductCategories::class)
            ->filterTable('parent', $network->id)
            ->assertCanSeeTableRecords([$switches])
            ->assertCanNotSeeTableRecords([$network, $deeper, $cables]);
    }

    public function test_the_list_can_show_only_the_top_level(): void
    {
        $network = $this->category('Tinklo įranga');
        $switches = $this->category('Komutatoriai', $network);

        Livewire::test(ListProductCategories::class)
            ->filterTable('top_level', true)
            ->assertCanSeeTableRecords([$network])
            ->assertCanNotSeeTableRecords([$switches]);
    }

    public function test_the_list_can_show_categories_with_or_without_products(): void
    {
        $filled = $this->category('Pilna');
        $empty = $this->category('Tuščia');
        Product::factory()->create(['product_category_id' => $filled->id]);

        Livewire::test(ListProductCategories::class)
            ->filterTable('has_products', true)
            ->assertCanSeeTableRecords([$filled])
            ->assertCanNotSeeTableRecords([$empty])
            ->filterTable('has_products', false)
            ->assertCanSeeTableRecords([$empty])
            ->assertCanNotSeeTableRecords([$filled]);
    }

    public function test_a_category_holding_only_a_deleted_product_still_counts_as_holding_one(): void
    {
        $category = $this->category('Su ištrinta preke');
        Product::factory()->create(['product_category_id' => $category->id])->delete();

        Livewire::test(ListProductCategories::class)
            ->filterTable('has_products', true)
            ->assertCanSeeTableRecords([$category]);
    }

    // -- create ------------------------------------------------------------

    public function test_a_top_level_category_can_be_created_in_both_languages(): void
    {
        Livewire::test(CreateProductCategory::class)
            ->fillForm($this->validForm())
            ->call('create')
            ->assertHasNoFormErrors();

        $category = ProductCategory::query()->sole();

        $this->assertNull($category->parent_id);
        $this->assertSame('Domofonai', $category->getTranslation('name', 'lt'));
        $this->assertSame('Intercoms', $category->getTranslation('name', 'en'));
        $this->assertSame('domofonai', $category->getTranslation('slug', 'lt'));
        $this->assertSame('intercoms', $category->getTranslation('slug', 'en'));
    }

    public function test_a_sub_category_can_be_created_under_a_parent(): void
    {
        $network = $this->category('Tinklo įranga');

        Livewire::test(CreateProductCategory::class)
            ->fillForm($this->validForm(['parent_id' => $network->id, 'name' => ['lt' => 'Komutatoriai', 'en' => 'Switches']]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(ProductCategory::query()->where('name->lt', 'Komutatoriai')->sole()->parent->is($network));
    }

    public function test_the_parent_choices_spell_out_each_categorys_whole_path(): void
    {
        $network = $this->category('Tinklo įranga');
        $this->category('Komutatoriai', $network);

        Livewire::test(CreateProductCategory::class)
            ->assertFormFieldExists('parent_id', fn (Select $field): bool => in_array('Tinklo įranga › Komutatoriai', $field->getOptions(), true));
    }

    public function test_both_languages_are_required(): void
    {
        Livewire::test(CreateProductCategory::class)
            ->fillForm($this->validForm(['name' => ['lt' => 'Domofonai', 'en' => '']]))
            ->call('create')
            ->assertHasFormErrors(['name.en' => 'required']);

        Livewire::test(CreateProductCategory::class)
            ->fillForm($this->validForm(['name' => ['lt' => '', 'en' => 'Intercoms']]))
            ->call('create')
            ->assertHasFormErrors(['name.lt' => 'required']);

        $this->assertSame(0, ProductCategory::query()->count());
    }

    public function test_siblings_may_not_share_a_lithuanian_name(): void
    {
        $network = $this->category('Tinklo įranga');
        $this->category('Komutatoriai', $network);

        Livewire::test(CreateProductCategory::class)
            ->fillForm($this->validForm(['parent_id' => $network->id, 'name' => ['lt' => 'KOMUTATORIAI', 'en' => 'Other']]))
            ->call('create')
            ->assertHasFormErrors(['name.lt']);

        $this->assertSame(2, ProductCategory::query()->count());
    }

    public function test_the_same_name_is_fine_under_another_parent_or_at_the_top_level(): void
    {
        $network = $this->category('Tinklo įranga');
        $security = $this->category('Apsauga');
        $this->category('Kameros', $network);

        Livewire::test(CreateProductCategory::class)
            ->fillForm($this->validForm(['parent_id' => $security->id, 'name' => ['lt' => 'Kameros', 'en' => 'Cameras']]))
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateProductCategory::class)
            ->fillForm($this->validForm(['parent_id' => null, 'name' => ['lt' => 'Kameros', 'en' => 'Top cameras']]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(5, ProductCategory::query()->count());
    }

    public function test_siblings_may_share_an_english_name(): void
    {
        $this->category('Kabeliai', english: 'Cables');

        Livewire::test(CreateProductCategory::class)
            ->fillForm($this->validForm(['name' => ['lt' => 'Laidai', 'en' => 'Cables']]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['cables', 'cables-2'], ProductCategory::query()->orderBy('id')->get()->map->getTranslation('slug', 'en')->all());
    }

    // -- edit and move -----------------------------------------------------

    public function test_the_edit_form_shows_both_languages_and_the_slugs(): void
    {
        $category = $this->category('Domofonai', english: 'Intercoms');

        Livewire::test(EditProductCategory::class, ['record' => $category->id])
            ->assertFormSet([
                'parent_id' => null,
                'name' => ['lt' => 'Domofonai', 'en' => 'Intercoms'],
                'slug' => ['lt' => 'domofonai', 'en' => 'intercoms'],
            ]);
    }

    public function test_a_category_can_be_renamed_and_its_slug_follows(): void
    {
        $category = $this->category('Domofonai', english: 'Intercoms');

        Livewire::test(EditProductCategory::class, ['record' => $category->id])
            ->fillForm(['name' => ['lt' => 'Durų telefonai', 'en' => 'Door phones']])
            ->call('save')
            ->assertHasNoFormErrors();

        $category->refresh();

        $this->assertSame('Durų telefonai', $category->getTranslation('name', 'lt'));
        $this->assertSame('duru-telefonai', $category->getTranslation('slug', 'lt'));
        $this->assertSame('door-phones', $category->getTranslation('slug', 'en'));
    }

    public function test_saving_a_category_without_renaming_it_keeps_its_own_name_valid(): void
    {
        $network = $this->category('Tinklo įranga');
        $switches = $this->category('Komutatoriai', $network);

        Livewire::test(EditProductCategory::class, ['record' => $switches->id])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_a_category_can_be_moved_to_another_parent_with_its_products_and_sub_categories(): void
    {
        $network = $this->category('Tinklo įranga');
        $security = $this->category('Apsauga');
        $cameras = $this->category('Kameros', $network);
        $outdoor = $this->category('Lauko kameros', $cameras);
        $product = Product::factory()->create(['product_category_id' => $cameras->id]);

        Livewire::test(EditProductCategory::class, ['record' => $cameras->id])
            ->fillForm(['parent_id' => $security->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($cameras->fresh()->parent->is($security));
        $this->assertTrue($outdoor->fresh()->parent->is($cameras));
        $this->assertSame($cameras->id, $product->fresh()->product_category_id);
    }

    public function test_a_moved_category_can_become_top_level(): void
    {
        $network = $this->category('Tinklo įranga');
        $switches = $this->category('Komutatoriai', $network);

        Livewire::test(EditProductCategory::class, ['record' => $switches->id])
            ->fillForm(['parent_id' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($switches->fresh()->parent_id);
    }

    public function test_a_move_may_not_collide_with_a_sibling_of_the_same_name(): void
    {
        $network = $this->category('Tinklo įranga');
        $security = $this->category('Apsauga');
        $this->category('Kameros', $network);
        $cameras = $this->category('Kameros', $security);

        Livewire::test(EditProductCategory::class, ['record' => $cameras->id])
            ->fillForm(['parent_id' => $network->id])
            ->call('save')
            ->assertHasFormErrors(['name.lt']);

        $this->assertTrue($cameras->fresh()->parent->is($security));
    }

    public function test_a_category_cannot_be_offered_itself_or_its_descendants_as_parent(): void
    {
        $top = $this->category('Tinklo įranga');
        $middle = $this->category('Komutatoriai', $top);
        $leaf = $this->category('PoE komutatoriai', $middle);
        $other = $this->category('Kabeliai');

        Livewire::test(EditProductCategory::class, ['record' => $middle->id])
            ->assertFormFieldExists('parent_id', function (Select $field) use ($top, $middle, $leaf, $other): bool {
                $options = $field->getOptions();

                return array_key_exists($top->id, $options)
                    && array_key_exists($other->id, $options)
                    && ! array_key_exists($middle->id, $options)
                    && ! array_key_exists($leaf->id, $options);
            });
    }

    public function test_a_category_cannot_be_moved_under_itself_or_its_descendants(): void
    {
        $top = $this->category('Tinklo įranga');
        $middle = $this->category('Komutatoriai', $top);
        $leaf = $this->category('PoE komutatoriai', $middle);

        foreach ([$top->id => $top, $leaf->id => $top] as $parentId => $record) {
            Livewire::test(EditProductCategory::class, ['record' => $record->id])
                ->fillForm(['parent_id' => $parentId])
                ->call('save')
                ->assertHasFormErrors(['parent_id']);
        }

        $this->assertNull($top->fresh()->parent_id);
        $this->assertTrue($leaf->fresh()->parent->is($middle));
    }

    // -- delete ------------------------------------------------------------

    public function test_an_empty_category_can_be_deleted_from_its_edit_page(): void
    {
        $category = $this->category('Tuščia');

        Livewire::test(EditProductCategory::class, ['record' => $category->id])
            ->callAction(DeleteAction::class)
            ->assertNotified();

        $this->assertModelMissing($category);
    }

    public function test_an_empty_category_can_be_deleted_from_the_list(): void
    {
        $category = $this->category('Tuščia');

        Livewire::test(ListProductCategories::class)
            ->callAction(TestAction::make('delete')->table($category))
            ->assertNotified();

        $this->assertModelMissing($category);
    }

    public function test_a_category_with_products_cannot_be_deleted(): void
    {
        $category = $this->category('Su prekėmis');
        $product = Product::factory()->create(['product_category_id' => $category->id]);

        Livewire::test(EditProductCategory::class, ['record' => $category->id])
            ->assertActionDisabled(DeleteAction::class);

        Livewire::test(ListProductCategories::class)
            ->assertActionDisabled(TestAction::make('delete')->table($category));

        $this->assertModelExists($category);
        $this->assertSame($category->id, $product->fresh()->product_category_id);
    }

    public function test_a_category_with_only_a_deleted_product_cannot_be_deleted(): void
    {
        $category = $this->category('Su ištrinta preke');
        Product::factory()->create(['product_category_id' => $category->id])->delete();

        Livewire::test(EditProductCategory::class, ['record' => $category->id])
            ->assertActionDisabled(DeleteAction::class);

        $this->assertModelExists($category);
    }

    public function test_a_category_with_sub_categories_cannot_be_deleted(): void
    {
        $network = $this->category('Tinklo įranga');
        $switches = $this->category('Komutatoriai', $network);

        Livewire::test(EditProductCategory::class, ['record' => $network->id])
            ->assertActionDisabled(DeleteAction::class);

        $this->assertModelExists($network);
        $this->assertTrue($switches->fresh()->parent->is($network));
    }

    public function test_the_delete_refusal_comes_with_its_reason(): void
    {
        $category = $this->category('Su prekėmis');
        Product::factory()->create(['product_category_id' => $category->id]);

        $response = (new ProductCategoryPolicy)->delete($this->admin, $category);

        $this->assertTrue($response->denied());
        $this->assertSame(__('admin.product_categories.delete_blocked'), $response->message());
        $this->assertTrue((new ProductCategoryPolicy)->delete($this->admin, $this->category('Tuščia'))->allowed());
    }

    public function test_a_list_of_categories_does_not_ask_the_database_once_per_row(): void
    {
        $this->category('Pirma');
        $queries = $this->listQueryCount();

        foreach (range(1, 15) as $number) {
            $this->category("Kategorija {$number}");
        }

        $this->assertSame($queries, $this->listQueryCount());
    }

    private function listQueryCount(): int
    {
        Livewire::test(ListProductCategories::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(ListProductCategories::class);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    // -- languages ---------------------------------------------------------

    public function test_every_admin_string_for_product_categories_exists_in_both_languages(): void
    {
        $english = array_keys(trans('admin.product_categories', [], 'en'));
        $lithuanian = array_keys(trans('admin.product_categories', [], 'lt'));

        $this->assertNotEmpty($english);
        $this->assertEqualsCanonicalizing($english, $lithuanian);

        foreach (['en', 'lt'] as $locale) {
            app()->setLocale($locale);

            $this->assertNotSame('admin.product_categories.delete_blocked', __('admin.product_categories.delete_blocked'));
        }
    }
}
