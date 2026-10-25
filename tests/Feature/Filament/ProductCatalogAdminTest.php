<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\CatalogImportStatus;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Jobs\ImportCatalogJob;
use App\Models\CatalogImport;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCatalogAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    private function catalogUpload(array $products, string $name = 'catalog.json'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            json_encode(['schema_version' => 1, 'products' => $products], JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogProduct(string $sku = 'A1'): array
    {
        return ['sku' => $sku, 'name' => 'Product '.$sku, 'price' => '9.90', 'stock' => 4];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validForm(array $overrides = []): array
    {
        return array_merge(['name' => 'Cable', 'price_cents' => '5.00', 'stock_quantity' => 0], $overrides);
    }

    public function test_the_list_shows_catalog_details_and_the_suppliers_image(): void
    {
        $top = ProductCategory::factory()->create(['name' => ['lt' => 'Tinklo įranga', 'en' => 'Tinklo įranga']]);
        $leaf = ProductCategory::factory()->childOf($top)->create(['name' => ['lt' => 'Komutatoriai', 'en' => 'Komutatoriai']]);
        Product::factory()->outOfStock()->create([
            'sku' => 'SWITCHCUDYFS1026PS1',
            'image_url' => 'http://bkgrupe.lt/media/switch.jpg',
            'product_category_id' => $leaf->id,
            'manufacturer_id' => Manufacturer::factory()->create(['name' => 'Cudy'])->id,
        ]);

        Livewire::test(ListProducts::class)
            ->assertSee('SWITCHCUDYFS1026PS1')
            ->assertSee('Komutatoriai')
            ->assertSee('Tinklo įranga')
            ->assertSee('Cudy')
            ->assertSeeHtml('http://bkgrupe.lt/media/switch.jpg');
    }

    public function test_the_list_can_be_searched_by_sku_and_ean(): void
    {
        $match = Product::factory()->create(['sku' => 'KRDS1663ZJ', 'ean' => '6954273658830']);
        $other = Product::factory()->create(['sku' => 'CISINT-E', 'ean' => '12345678']);

        Livewire::test(ListProducts::class)
            ->searchTable('KRDS1663')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('12345678')
            ->assertCanSeeTableRecords([$other])
            ->assertCanNotSeeTableRecords([$match]);
    }

    public function test_the_category_filter_includes_sub_categories(): void
    {
        $network = ProductCategory::factory()->create();
        $switches = ProductCategory::factory()->childOf($network)->create();
        $cables = ProductCategory::factory()->create();

        $inSub = Product::factory()->create(['product_category_id' => $switches->id]);
        $inRoot = Product::factory()->create(['product_category_id' => $network->id]);
        $elsewhere = Product::factory()->create(['product_category_id' => $cables->id]);
        $uncategorised = Product::factory()->create();

        Livewire::test(ListProducts::class)
            ->filterTable('category', $network->id)
            ->assertCanSeeTableRecords([$inSub, $inRoot])
            ->assertCanNotSeeTableRecords([$elsewhere, $uncategorised])
            ->filterTable('category', $switches->id)
            ->assertCanSeeTableRecords([$inSub])
            ->assertCanNotSeeTableRecords([$inRoot, $elsewhere]);
    }

    public function test_the_list_can_be_filtered_by_manufacturer(): void
    {
        $hikvision = Manufacturer::factory()->create();
        $match = Product::factory()->create(['manufacturer_id' => $hikvision->id]);
        $other = Product::factory()->create(['manufacturer_id' => Manufacturer::factory()->create()->id]);

        Livewire::test(ListProducts::class)
            ->filterTable('manufacturer', $hikvision->id)
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_the_list_can_be_filtered_by_stock(): void
    {
        $inStock = Product::factory()->inStock()->create();
        $outOfStock = Product::factory()->outOfStock()->create();

        Livewire::test(ListProducts::class)
            ->filterTable('stock', 'in_stock')
            ->assertCanSeeTableRecords([$inStock])
            ->assertCanNotSeeTableRecords([$outOfStock])
            ->filterTable('stock', 'out_of_stock')
            ->assertCanSeeTableRecords([$outOfStock])
            ->assertCanNotSeeTableRecords([$inStock]);
    }

    public function test_the_list_can_show_only_catalog_products(): void
    {
        $catalog = Product::factory()->create(['sku' => 'A1']);
        $manual = Product::factory()->create();

        Livewire::test(ListProducts::class)
            ->filterTable('has_sku', true)
            ->assertCanSeeTableRecords([$catalog])
            ->assertCanNotSeeTableRecords([$manual])
            ->filterTable('has_sku', false)
            ->assertCanSeeTableRecords([$manual])
            ->assertCanNotSeeTableRecords([$catalog]);
    }

    public function test_a_product_can_be_created_with_catalog_details(): void
    {
        $category = ProductCategory::factory()->create();
        $manufacturer = Manufacturer::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->validForm([
                'sku' => 'KRDS1663ZJ',
                'ean' => '6954273658830',
                'stock_quantity' => 7,
                'image_url' => 'http://bkgrupe.lt/media/a.jpg',
                'product_category_id' => $category->id,
                'manufacturer_id' => $manufacturer->id,
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->firstOrFail();

        $this->assertSame('KRDS1663ZJ', $product->sku);
        $this->assertSame('6954273658830', $product->ean);
        $this->assertSame(7, $product->stock_quantity);
        $this->assertSame('http://bkgrupe.lt/media/a.jpg', $product->image_url);
        $this->assertSame($category->id, $product->product_category_id);
        $this->assertSame($manufacturer->id, $product->manufacturer_id);
    }

    public function test_emptied_catalog_fields_are_stored_as_null_so_products_without_a_sku_do_not_collide(): void
    {
        foreach (['Cable', 'Plug'] as $name) {
            Livewire::test(CreateProduct::class)
                ->fillForm($this->validForm(['name' => $name, 'sku' => '', 'ean' => '', 'image_url' => '']))
                ->call('create')
                ->assertHasNoFormErrors();
        }

        $this->assertSame(2, Product::query()->whereNull('sku')->whereNull('ean')->whereNull('image_url')->count());
    }

    public function test_catalog_fields_are_validated(): void
    {
        Product::factory()->create(['sku' => 'TAKEN']);

        Livewire::test(CreateProduct::class)
            ->fillForm($this->validForm([
                'sku' => 'TAKEN', 'ean' => '123', 'stock_quantity' => -1, 'image_url' => 'not a url',
            ]))
            ->call('create')
            ->assertHasFormErrors(['sku', 'ean', 'stock_quantity', 'image_url']);
    }

    public function test_a_product_keeps_its_own_sku_when_it_is_saved_again(): void
    {
        $product = Product::factory()->create(['sku' => 'KRDS1663ZJ']);

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['stock_quantity' => 5])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('KRDS1663ZJ', $product->fresh()->sku);
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_the_import_action_stores_the_upload_and_queues_the_import(): void
    {
        Queue::fake();

        Livewire::test(ListProducts::class)
            ->callAction('importCatalog', ['file' => $this->catalogUpload([$this->catalogProduct()], 'spring-prices.json')])
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.catalog_import.queued_title'));

        $import = CatalogImport::query()->sole();

        $this->assertSame(CatalogImportStatus::Pending, $import->status);
        $this->assertSame('spring-prices.json', $import->original_filename);
        $this->assertSame($this->admin->id, $import->user_id);
        Storage::disk('local')->assertExists($import->file_path);
        $this->assertSame(0, Product::query()->count());
        Queue::assertPushed(ImportCatalogJob::class, fn (ImportCatalogJob $job): bool => $job->import->is($import));
    }

    public function test_an_uploaded_catalog_is_imported_when_the_job_runs(): void
    {
        Livewire::test(ListProducts::class)
            ->callAction('importCatalog', ['file' => $this->catalogUpload([
                $this->catalogProduct('A1'),
                $this->catalogProduct('A2'),
                ['sku' => 'BAD', 'name' => 'Bad', 'price' => 'free', 'stock' => 1],
            ])])
            ->assertHasNoFormErrors();

        $import = CatalogImport::query()->sole();

        $this->assertSame(CatalogImportStatus::Completed, $import->status);
        $this->assertSame(2, $import->created_count);
        $this->assertSame(1, $import->skipped_count);
        $this->assertEqualsCanonicalizing(['A1', 'A2'], Product::query()->pluck('sku')->all());
    }

    public function test_the_import_action_needs_a_file(): void
    {
        Queue::fake();

        Livewire::test(ListProducts::class)
            ->callAction('importCatalog', [])
            ->assertHasFormErrors(['file' => 'required']);

        $this->assertSame(0, CatalogImport::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_the_import_action_rejects_files_that_are_not_json(): void
    {
        Queue::fake();

        Livewire::test(ListProducts::class)
            ->callAction('importCatalog', ['file' => UploadedFile::fake()->image('catalog.png')])
            ->assertHasFormErrors(['file']);

        $this->assertSame(0, CatalogImport::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_the_catalog_template_can_be_downloaded(): void
    {
        Livewire::test(ListProducts::class)
            ->callAction('downloadCatalogTemplate')
            ->assertFileDownloaded('catalog.template.json');
    }
}
