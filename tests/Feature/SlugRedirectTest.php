<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Filament\Actions\RegenerateSlugAction;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Page;
use App\Models\Project;
use App\Models\SlugRedirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Tests\TestCase;

class SlugRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function pageWithRenamedTitle(): Page
    {
        $page = Page::query()->create([
            'key' => 'faq',
            'title' => ['lt' => 'Klausimai', 'en' => 'Questions'],
        ]);

        $page->setTranslation('title', 'lt', 'Pagalba');
        $page->save();

        return $page;
    }

    public function test_page_slug_stays_put_when_title_changes(): void
    {
        $page = $this->pageWithRenamedTitle();

        $this->assertSame('klausimai', $page->fresh()->getTranslation('slug', 'lt'));
        $this->assertSame(0, SlugRedirect::query()->count());
    }

    public function test_published_project_slug_stays_put_when_title_changes(): void
    {
        $project = Project::factory()->create();
        $slug = $project->getTranslation('slug', 'en');

        $project->setTranslation('title', 'en', 'Brand New Title');
        $project->save();

        $this->assertSame($slug, $project->fresh()->getTranslation('slug', 'en'));
    }

    public function test_draft_project_slug_follows_title_and_leaves_no_redirect(): void
    {
        $project = Project::factory()->draft()->create();

        $project->setTranslation('title', 'en', 'Working Title');
        $project->save();
        $project->setTranslation('title', 'en', 'Final Name');
        $project->save();

        $this->assertSame('final-name', $project->fresh()->getTranslation('slug', 'en'));
        $this->assertSame(0, SlugRedirect::query()->count());
    }

    public function test_slug_follows_title_in_the_save_that_publishes_a_draft_then_locks(): void
    {
        $project = Project::factory()->draft()->create();

        $project->setTranslation('title', 'en', 'Final Name');
        $project->status = ProjectStatus::Published;
        $project->save();

        $this->assertSame('final-name', $project->fresh()->getTranslation('slug', 'en'));

        $project->setTranslation('title', 'en', 'Another Name');
        $project->save();

        $this->assertSame('final-name', $project->fresh()->getTranslation('slug', 'en'));
    }

    public function test_regenerating_a_live_slug_keeps_the_old_one_as_a_redirect(): void
    {
        $page = $this->pageWithRenamedTitle();

        $this->assertTrue($page->regenerateSlug('lt'));
        $this->assertFalse($page->regenerateSlug('lt'));

        $this->assertSame('pagalba', $page->fresh()->getTranslation('slug', 'lt'));
        $this->assertSame('questions', $page->fresh()->getTranslation('slug', 'en'));
        $this->assertDatabaseHas('slug_redirects', [
            'redirectable_type' => Page::class,
            'redirectable_id' => $page->id,
            'locale' => 'lt',
            'old_slug' => 'klausimai',
        ]);
        $this->assertSame(1, SlugRedirect::query()->count());
    }

    public function test_regenerating_a_draft_slug_leaves_no_redirect(): void
    {
        $project = Project::factory()->draft()->create();
        $project->setTranslation('title', 'en', 'Reworked');
        $project->saveQuietly();

        $this->assertTrue($project->regenerateSlug('en'));
        $this->assertSame(0, SlugRedirect::query()->count());
    }

    public function test_a_slug_coming_back_into_use_stops_redirecting(): void
    {
        $page = $this->pageWithRenamedTitle();
        $page->regenerateSlug('lt');

        $page->setTranslation('title', 'lt', 'Klausimai');
        $page->save();
        $page->regenerateSlug('lt');

        $this->assertSame('klausimai', $page->fresh()->getTranslation('slug', 'lt'));
        $this->assertDatabaseMissing('slug_redirects', ['old_slug' => 'klausimai']);
        $this->assertDatabaseHas('slug_redirects', ['old_slug' => 'pagalba']);
    }

    public function test_a_retired_slug_cannot_be_taken_by_another_record(): void
    {
        $this->pageWithRenamedTitle()->regenerateSlug('lt');

        $other = Page::query()->create([
            'key' => 'other',
            'title' => ['lt' => 'Klausimai', 'en' => 'Other'],
        ]);

        $this->assertSame('klausimai-2', $other->getTranslation('slug', 'lt'));
    }

    public function test_redirects_are_removed_with_the_record(): void
    {
        $page = $this->pageWithRenamedTitle();
        $page->regenerateSlug('lt');

        $page->delete();

        $this->assertSame(0, SlugRedirect::query()->count());
    }

    public function test_old_page_url_redirects_permanently_to_the_current_one(): void
    {
        $this->pageWithRenamedTitle()->regenerateSlug('lt');

        $this->get('/klausimai')->assertStatus(301)->assertRedirect(url('/pagalba'));
        $this->get('/pagalba')->assertOk();
        $this->get('/nera-tokio')->assertNotFound();
    }

    public function test_old_project_url_redirects_permanently_to_the_current_one(): void
    {
        $project = Project::factory()->create();
        $oldSlug = $project->getTranslation('slug', 'lt');
        $project->setTranslation('title', 'lt', 'Visiškai naujas pavadinimas');
        $project->save();
        $project->regenerateSlug('lt');

        $this->get('/projects/'.$oldSlug)
            ->assertStatus(301)
            ->assertRedirect(url('/projects/visiskai-naujas-pavadinimas'));
        $this->get('/projects/visiskai-naujas-pavadinimas')->assertOk();
    }

    public function test_old_url_of_an_unpublished_project_does_not_redirect(): void
    {
        $project = Project::factory()->create();
        $oldSlug = $project->getTranslation('slug', 'lt');
        $project->setTranslation('title', 'lt', 'Visiškai naujas pavadinimas');
        $project->save();
        $project->regenerateSlug('lt');
        $project->update(['status' => ProjectStatus::Draft]);

        $this->get('/projects/'.$oldSlug)->assertNotFound();
    }

    public function test_page_slug_cannot_collide_with_a_literal_route(): void
    {
        foreach (['Contact', 'Projects', 'Kainu skaiciuokle', 'Price Calculator', 'Admin', 'EN'] as $index => $title) {
            $page = Page::query()->create([
                'key' => 'reserved-'.$index,
                'title' => ['lt' => $title, 'en' => $title],
            ]);

            $this->assertSame(Str::slug($title).'-2', $page->getTranslation('slug', 'en'), $title);
        }
    }

    public function test_project_slug_is_not_subject_to_page_route_reservations(): void
    {
        $project = Project::factory()->draft()->create();
        $project->setTranslation('title', 'en', 'Contact');
        $project->save();

        $this->assertSame('contact', $project->fresh()->getTranslation('slug', 'en'));
    }

    public function test_project_page_advertises_each_locales_own_slug(): void
    {
        $project = Project::factory()->create([
            'slug' => ['lt' => 'lietuviskas-slug', 'en' => 'english-slug'],
        ]);

        $this->get('/projects/lietuviskas-slug')->assertInertia(function (AssertableInertia $page) {
            $props = $page->toArray()['props'];
            $alternates = collect($props['seo']['alternates'])->pluck('url', 'locale');
            $switcher = collect($props['locale']['available'])->pluck('url', 'code');

            $this->assertSame(url('/projects/lietuviskas-slug'), $props['seo']['canonical']);
            $this->assertSame(url('/projects/lietuviskas-slug'), $alternates['lt']);
            $this->assertSame(url('/en/projects/english-slug'), $alternates['en']);
            $this->assertSame(url('/projects/lietuviskas-slug'), $alternates['x-default']);
            $this->assertSame(url('/en/projects/english-slug'), $switcher['en']);
        });

        $this->assertNotNull($project);
    }

    public function test_locale_without_a_slug_is_left_out_of_alternates_and_switcher_goes_home(): void
    {
        Page::query()->create(['key' => 'faq', 'title' => ['lt' => 'Klausimai']]);

        $this->get('/klausimai')->assertInertia(function (AssertableInertia $page) {
            $props = $page->toArray()['props'];

            $this->assertSame(['lt', 'x-default'], collect($props['seo']['alternates'])->pluck('locale')->all());
            $this->assertSame(
                LaravelLocalization::getLocalizedURL('en', url('/'), [], true),
                collect($props['locale']['available'])->pluck('url', 'code')['en'],
            );
        });
    }

    public function test_admin_edit_page_shows_the_slug_read_only_and_create_page_does_not(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Page::factory()->create(['key' => 'faq']);

        Livewire::test(CreatePage::class)->assertFormFieldDoesNotExist('slug');
        Livewire::test(EditPage::class, ['record' => $page->id])
            ->assertFormFieldExists('slug', fn ($field) => $field->isDisabled());
    }

    public function test_admin_regenerate_action_updates_the_slug_and_records_a_redirect(): void
    {
        $this->actingAs(User::factory()->create());
        $page = $this->pageWithRenamedTitle();

        Livewire::test(EditPage::class, ['record' => $page->id])
            ->set('activeLocale', 'lt')
            ->callAction(RegenerateSlugAction::class)
            ->assertNotified(__('admin.slug.regenerated'));

        $this->assertSame('pagalba', $page->fresh()->getTranslation('slug', 'lt'));
        $this->assertDatabaseHas('slug_redirects', ['old_slug' => 'klausimai', 'locale' => 'lt']);
    }

    public function test_admin_regenerate_action_reports_when_the_slug_already_matches(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Page::query()->create(['key' => 'faq', 'title' => ['lt' => 'Klausimai', 'en' => 'Questions']]);

        Livewire::test(EditPage::class, ['record' => $page->id])
            ->set('activeLocale', 'lt')
            ->callAction(RegenerateSlugAction::class)
            ->assertNotified(__('admin.slug.unchanged'));

        $this->assertSame(0, SlugRedirect::query()->count());
    }

    public function test_sitemap_has_lastmod_and_skips_locales_without_a_slug(): void
    {
        $path = public_path('sitemap.xml');
        $backup = is_file($path) ? file_get_contents($path) : null;

        try {
            Page::query()->create(['key' => 'faq', 'title' => ['lt' => 'Klausimai']]);

            Artisan::call('app:generate-sitemap');
            $xml = file_get_contents(public_path('sitemap.xml'));

            $this->assertStringContainsString('/klausimai</loc>', $xml);
            $this->assertStringContainsString('<lastmod>', $xml);
            $this->assertStringNotContainsString('/en/klausimai', $xml);
        } finally {
            if ($backup === null) {
                @unlink($path);
            } else {
                file_put_contents($path, $backup);
            }
        }
    }
}
