<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Models\Category;
use App\Models\Page;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HasSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_is_generated_from_title_per_locale_on_create(): void
    {
        $page = Page::query()->create([
            'key' => 'faq',
            'title' => ['lt' => 'Dažnai užduodami klausimai', 'en' => 'Frequently Asked Questions'],
        ]);

        $this->assertSame('daznai-uzduodami-klausimai', $page->getTranslation('slug', 'lt'));
        $this->assertSame('frequently-asked-questions', $page->getTranslation('slug', 'en'));
    }

    public function test_slug_is_generated_from_name_for_models_that_use_name(): void
    {
        $category = Category::query()->create(['name' => ['lt' => 'Baigti', 'en' => 'Completed']]);
        $tag = Tag::query()->create(['name' => ['lt' => 'Saulės energija', 'en' => 'Solar']]);

        $this->assertSame('completed', $category->getTranslation('slug', 'en'));
        $this->assertSame('saules-energija', $tag->getTranslation('slug', 'lt'));
    }

    public function test_slug_cannot_be_mass_assigned(): void
    {
        $tag = new Tag;
        $tag->fill(['name' => ['en' => 'Solar'], 'slug' => ['en' => 'hacked']]);

        $this->assertNull($tag->getTranslation('slug', 'en', false) ?: null);

        $tag->save();

        $this->assertSame('solar', $tag->getTranslation('slug', 'en'));
    }

    public function test_unlocked_record_slug_follows_title_when_title_changes(): void
    {
        $project = Project::factory()->draft()->create();
        $project->setTranslation('title', 'en', 'Brand New Title');
        $project->save();

        $this->assertSame('brand-new-title', $project->fresh()->getTranslation('slug', 'en'));
    }

    public function test_slug_is_kept_when_title_is_unchanged(): void
    {
        $project = Project::factory()->create();
        $slug = $project->getTranslation('slug', 'en');

        $project->update(['order' => 99]);

        $this->assertSame($slug, $project->fresh()->getTranslation('slug', 'en'));
    }

    public function test_untouched_locale_keeps_its_slug_when_another_locale_changes(): void
    {
        $tag = Tag::query()->create(['name' => ['lt' => 'Klausimai', 'en' => 'Questions']]);

        $tag->setTranslation('name', 'en', 'Help');
        $tag->save();

        $tag = $tag->fresh();

        $this->assertSame('help', $tag->getTranslation('slug', 'en'));
        $this->assertSame('klausimai', $tag->getTranslation('slug', 'lt'));
    }

    public function test_slug_assigned_in_code_is_respected(): void
    {
        $page = Page::factory()->about()->create();

        $this->assertSame('about', $page->getTranslation('slug', 'en'));
        $this->assertSame('apie-mus', $page->getTranslation('slug', 'lt'));
    }

    public function test_colliding_slug_gets_a_numeric_suffix(): void
    {
        $first = Tag::query()->create(['name' => ['en' => 'Solar']]);
        $second = Tag::query()->create(['name' => ['en' => 'Solar']]);
        $third = Tag::query()->create(['name' => ['en' => 'Solar!']]);

        $this->assertSame('solar', $first->getTranslation('slug', 'en'));
        $this->assertSame('solar-2', $second->getTranslation('slug', 'en'));
        $this->assertSame('solar-3', $third->getTranslation('slug', 'en'));
    }

    public function test_record_does_not_collide_with_itself_on_resave(): void
    {
        $tag = Tag::query()->create(['name' => ['en' => 'Solar']]);

        $tag->setTranslation('name', 'en', 'Solar');
        $tag->save();

        $this->assertSame('solar', $tag->fresh()->getTranslation('slug', 'en'));
    }

    public function test_slug_collision_check_includes_soft_deleted_records(): void
    {
        $project = Project::factory()->create();
        $title = $project->getTranslation('title', 'en');
        $project->delete();

        $other = Project::factory()->draft()->create();
        $other->setTranslation('title', 'en', $title);
        $other->save();

        $this->assertSame($project->getTranslation('slug', 'en').'-2', $other->fresh()->getTranslation('slug', 'en'));
    }

    public function test_slug_is_not_generated_for_blank_source(): void
    {
        $page = Page::query()->create([
            'key' => 'faq',
            'title' => ['lt' => 'Klausimai', 'en' => '!!!'],
        ]);

        $this->assertSame('klausimai', $page->getTranslation('slug', 'lt'));
        $this->assertNull($page->getTranslation('slug', 'en', false) ?: null);
    }

    public function test_admin_page_form_generates_slug_without_a_slug_field(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreatePage::class)
            ->assertFormFieldDoesNotExist('slug')
            ->fillForm(['key' => 'faq', 'title' => 'DUK'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('duk', Page::query()->where('key', 'faq')->firstOrFail()->getTranslation('slug', app()->getLocale()));
    }
}
