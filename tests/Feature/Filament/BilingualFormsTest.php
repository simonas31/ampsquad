<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Tags\Pages\CreateTag;
use App\Filament\Resources\Tags\Pages\EditTag;
use App\Filament\Resources\Videos\Pages\CreateVideo;
use App\Filament\Resources\Videos\Pages\EditVideo;
use App\Filament\Schemas\BilingualFields;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Create and edit forms show Lithuanian and English side by side, so a
 * translation never needs a locale switch. Page and Product stand in for the
 * rules every translatable resource shares.
 */
class BilingualFormsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_the_forms_offer_both_languages_in_the_order_lithuanian_first(): void
    {
        $this->assertSame(['lt', 'en'], BilingualFields::locales());

        Livewire::test(CreatePage::class)
            ->assertFormFieldExists('title.lt')
            ->assertFormFieldExists('title.en');
    }

    public function test_a_record_can_be_created_in_both_languages_at_once(): void
    {
        Livewire::test(CreatePage::class)
            ->fillForm(['key' => 'faq', 'title' => ['lt' => 'Klausimai', 'en' => 'Questions']])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::query()->where('key', 'faq')->sole();

        $this->assertSame('Klausimai', $page->getTranslation('title', 'lt'));
        $this->assertSame('Questions', $page->getTranslation('title', 'en'));
        $this->assertSame('klausimai', $page->getTranslation('slug', 'lt'));
        $this->assertSame('questions', $page->getTranslation('slug', 'en'));
    }

    public function test_english_is_optional_and_an_empty_english_field_stores_nothing(): void
    {
        Livewire::test(CreatePage::class)
            ->fillForm(['key' => 'faq', 'title' => ['lt' => 'Klausimai', 'en' => '']])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::query()->where('key', 'faq')->sole();

        $this->assertSame(['lt' => 'Klausimai'], json_decode($page->getRawOriginal('title'), true));
        $this->assertSame(['lt' => 'klausimai'], json_decode($page->getRawOriginal('slug'), true));
    }

    public function test_lithuanian_is_required_even_when_english_is_filled(): void
    {
        Livewire::test(CreatePage::class)
            ->fillForm(['key' => 'faq', 'title' => ['lt' => '', 'en' => 'Questions']])
            ->call('create')
            ->assertHasFormErrors(['title.lt' => 'required']);

        $this->assertSame(0, Page::query()->count());
    }

    public function test_an_english_description_needs_an_english_name(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => ['lt' => 'Kabelis', 'en' => ''],
                'description' => ['lt' => '', 'en' => 'A cable'],
                'price_excl_vat_cents' => '5.00',
                'stock_quantity' => 1,
            ])
            ->call('create')
            ->assertHasFormErrors(['name.en' => 'required_with']);

        $this->assertSame(0, Product::query()->count());
    }

    public function test_the_edit_form_loads_both_languages_and_shows_a_missing_one_empty(): void
    {
        $both = Page::query()->create(['key' => 'klausimai', 'title' => ['lt' => 'Klausimai', 'en' => 'Questions']]);
        $lithuanianOnly = Page::query()->create(['key' => 'duk', 'title' => ['lt' => 'DUK']]);

        Livewire::test(EditPage::class, ['record' => $both->id])
            ->assertFormSet([
                'title' => ['lt' => 'Klausimai', 'en' => 'Questions'],
                'slug' => ['lt' => 'klausimai', 'en' => 'questions'],
            ]);

        Livewire::test(EditPage::class, ['record' => $lithuanianOnly->id])
            ->assertFormSet([
                'title' => ['lt' => 'DUK', 'en' => null],
                'slug' => ['lt' => 'duk', 'en' => null],
            ]);
    }

    public function test_english_can_be_added_to_a_lithuanian_only_record_without_touching_its_live_url(): void
    {
        $page = Page::query()->create(['key' => 'duk', 'title' => ['lt' => 'DUK']]);

        Livewire::test(EditPage::class, ['record' => $page->id])
            ->fillForm(['title' => ['lt' => 'DUK pakeistas', 'en' => 'FAQ']])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();

        $this->assertSame('DUK pakeistas', $page->getTranslation('title', 'lt'));
        $this->assertSame('FAQ', $page->getTranslation('title', 'en'));
        $this->assertSame('duk', $page->getTranslation('slug', 'lt'));
        $this->assertSame('faq', $page->getTranslation('slug', 'en'));
    }

    public function test_a_language_a_record_already_has_cannot_be_emptied(): void
    {
        $page = Page::query()->create(['key' => 'faq', 'title' => ['lt' => 'Klausimai', 'en' => 'Questions']]);

        Livewire::test(EditPage::class, ['record' => $page->id])
            ->fillForm(['title' => ['lt' => 'Klausimai', 'en' => '']])
            ->call('save')
            ->assertHasFormErrors(['title.en' => 'required']);

        $this->assertSame('Questions', $page->fresh()->getTranslation('title', 'en'));
    }

    public function test_an_optional_translation_can_be_cleared_without_leaving_a_null_behind(): void
    {
        $product = Product::factory()->create([
            'name' => ['lt' => 'Kabelis', 'en' => 'Cable'],
            'description' => ['lt' => 'Aprašymas', 'en' => 'Description'],
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['description' => ['lt' => 'Aprašymas', 'en' => '']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['lt' => 'Aprašymas'], json_decode($product->fresh()->getRawOriginal('description'), true));
    }

    public function test_the_slug_of_one_language_can_be_regenerated_alone(): void
    {
        $page = Page::query()->create(['key' => 'faq', 'title' => ['lt' => 'Klausimai', 'en' => 'Questions']]);
        $page->setTranslation('title', 'lt', 'Pagalba')->setTranslation('title', 'en', 'Help')->save();

        Livewire::test(EditPage::class, ['record' => $page->id])
            ->callAction('regenerateSlug', ['locales' => ['en']])
            ->assertFormSet(['slug' => ['lt' => 'klausimai', 'en' => 'help']]);

        $page->refresh();

        $this->assertSame('klausimai', $page->getTranslation('slug', 'lt'));
        $this->assertSame('help', $page->getTranslation('slug', 'en'));
        $this->assertDatabaseHas('slug_redirects', ['old_slug' => 'questions', 'locale' => 'en']);
        $this->assertDatabaseMissing('slug_redirects', ['old_slug' => 'klausimai']);
    }

    public function test_a_category_is_saved_in_both_languages(): void
    {
        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => ['lt' => 'Baigti', 'en' => 'Completed'],
                'description' => ['lt' => 'Baigti darbai', 'en' => 'Finished work'],
                'order' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::query()->sole();

        $this->assertSame('Completed', $category->getTranslation('name', 'en'));
        $this->assertSame('Finished work', $category->getTranslation('description', 'en'));

        Livewire::test(EditCategory::class, ['record' => $category->id])
            ->assertFormSet(['name' => ['lt' => 'Baigti', 'en' => 'Completed']])
            ->fillForm(['name' => ['lt' => 'Baigti darbai', 'en' => 'Completed work']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Completed work', $category->fresh()->getTranslation('name', 'en'));
    }

    public function test_a_tag_is_saved_in_both_languages(): void
    {
        Livewire::test(CreateTag::class)
            ->fillForm(['name' => ['lt' => 'Saulės energija', 'en' => 'Solar']])
            ->call('create')
            ->assertHasNoFormErrors();

        $tag = Tag::query()->sole();

        $this->assertSame('Solar', $tag->getTranslation('name', 'en'));

        Livewire::test(EditTag::class, ['record' => $tag->id])
            ->assertFormSet(['name' => ['lt' => 'Saulės energija', 'en' => 'Solar']]);
    }

    public function test_a_project_is_saved_in_both_languages(): void
    {
        $existing = Project::factory()->create();

        Livewire::test(CreateProject::class)
            ->fillForm([
                'category_id' => $existing->category_id,
                'title' => ['lt' => 'Saulės jėgainė', 'en' => 'Solar plant'],
                'excerpt' => ['lt' => 'Trumpai', 'en' => 'In short'],
                'location' => ['lt' => 'Vilnius', 'en' => 'Vilnius city'],
                'status' => 'draft',
                'order' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::query()->where('id', '!=', $existing->id)->sole();

        $this->assertSame('Solar plant', $project->getTranslation('title', 'en'));
        $this->assertSame('In short', $project->getTranslation('excerpt', 'en'));
        $this->assertSame('Vilnius city', $project->getTranslation('location', 'en'));

        Livewire::test(EditProject::class, ['record' => $project->id])
            ->assertFormSet(['title' => ['lt' => 'Saulės jėgainė', 'en' => 'Solar plant']]);
    }

    public function test_a_video_title_is_optional_in_both_languages(): void
    {
        Livewire::test(CreateVideo::class)
            ->fillForm([
                'title' => ['lt' => 'Montavimas', 'en' => 'Installation'],
                'type' => 'instagram_embed',
                'embed_url' => 'https://www.instagram.com/p/abc123/',
                'order' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $video = Video::query()->sole();

        $this->assertSame('Installation', $video->getTranslation('title', 'en'));

        Livewire::test(EditVideo::class, ['record' => $video->id])
            ->assertFormSet(['title' => ['lt' => 'Montavimas', 'en' => 'Installation']]);
    }
}
