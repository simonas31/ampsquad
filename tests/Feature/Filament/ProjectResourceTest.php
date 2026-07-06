<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\ContentType;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_table_only_shows_project_type_content(): void
    {
        $this->actingAs(User::factory()->create());

        $projects = Project::factory()->count(2)->create();
        $articles = Project::factory()->article()->count(2)->create();

        Livewire::test(ListProjects::class)
            ->assertCanSeeTableRecords($projects)
            ->assertCanNotSeeTableRecords($articles);
    }

    public function test_articles_table_only_shows_article_type_content(): void
    {
        $this->actingAs(User::factory()->create());

        $projects = Project::factory()->count(2)->create();
        $articles = Project::factory()->article()->count(2)->create();

        Livewire::test(ListArticles::class)
            ->assertCanSeeTableRecords($articles)
            ->assertCanNotSeeTableRecords($projects);
    }

    public function test_creating_a_project_persists_the_fixed_content_type(): void
    {
        $this->actingAs(User::factory()->create());

        $category = Category::factory()->forProjects()->create();

        Livewire::test(CreateProject::class)
            ->fillForm([
                'category_id' => $category->id,
                'title' => 'Naujas projektas',
                'slug' => 'naujas-projektas',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::query()->ofType(ContentType::Project)->firstOrFail();

        $this->assertSame('Naujas projektas', $project->title);
        $this->assertSame(ContentType::Project, $project->content_type);
    }
}
