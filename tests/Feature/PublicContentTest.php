<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PublicContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_index_only_lists_published_projects(): void
    {
        Project::factory()->count(2)->create();
        Project::factory()->draft()->create();
        Project::factory()->article()->create();

        $response = $this->get('/projects');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Projects/Index')
            ->has('projects.data', 2)
        );
    }

    public function test_projects_index_filters_by_category(): void
    {
        $categoryA = Category::factory()->forProjects()->create();
        $categoryB = Category::factory()->forProjects()->create();
        Project::factory()->count(2)->create(['category_id' => $categoryA->id]);
        Project::factory()->create(['category_id' => $categoryB->id]);

        $response = $this->get("/projects?category={$categoryA->id}");

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('projects.data', 2)
        );
    }

    public function test_project_show_renders_resolved_blocks_for_the_current_locale(): void
    {
        $project = Project::factory()->create([
            'blocks' => [
                ['type' => 'heading', 'data' => ['text' => ['lt' => 'Antraštė', 'en' => 'Heading'], 'level' => 'h2']],
            ],
        ]);

        $response = $this->get("/projects/{$project->slug}");

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Projects/Show')
            ->where('project.blocks.0.data.text', 'Antraštė')
        );
    }

    public function test_draft_project_is_not_publicly_reachable(): void
    {
        $project = Project::factory()->draft()->create();

        $this->get("/projects/{$project->slug}")->assertNotFound();
    }

    public function test_article_content_type_does_not_leak_into_projects_show(): void
    {
        $article = Project::factory()->article()->create();

        $this->get("/projects/{$article->slug}")->assertNotFound();
    }

    public function test_articles_index_only_lists_article_content_type(): void
    {
        Project::factory()->count(2)->create();
        Project::factory()->article()->count(3)->create();

        $response = $this->get('/articles');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Articles/Index')
            ->has('articles.data', 3)
        );
    }

    public function test_static_page_renders_by_locale_slug(): void
    {
        $page = Page::factory()->about()->create();

        $response = $this->get("/{$page->slug}");

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $assertable) => $assertable
            ->component('Pages/Show')
            ->where('page.key', 'about')
        );
    }

    public function test_unknown_static_page_slug_404s(): void
    {
        $this->get('/this-page-does-not-exist')->assertNotFound();
    }
}
