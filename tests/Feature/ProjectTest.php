<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_scope_excludes_drafts_and_future_dated_posts(): void
    {
        Project::factory()->create();
        Project::factory()->draft()->create();
        Project::factory()->create(['published_at' => now()->addWeek()]);

        $this->assertSame(1, Project::query()->published()->count());
    }

    public function test_featured_scope_filters_by_is_featured(): void
    {
        Project::factory()->featured()->count(2)->create();
        Project::factory()->count(3)->create();

        $this->assertSame(2, Project::query()->featured()->count());
    }

    public function test_ongoing_state_leaves_completed_at_null(): void
    {
        $project = Project::factory()->ongoing()->create();

        $this->assertNull($project->completed_at);
    }

    public function test_resolved_blocks_resolve_leaf_text_to_the_requested_locale(): void
    {
        $project = Project::factory()->create([
            'blocks' => [
                [
                    'type' => 'heading',
                    'data' => [
                        'text' => ['lt' => 'Antraštė', 'en' => 'Heading'],
                        'level' => 'h2',
                    ],
                ],
            ],
        ]);

        $this->assertSame('Antraštė', $project->resolvedBlocks('lt')[0]['data']['text']);
        $this->assertSame('Heading', $project->resolvedBlocks('en')[0]['data']['text']);
        // Non-locale-map values (e.g. "level") pass through unchanged.
        $this->assertSame('h2', $project->resolvedBlocks('lt')[0]['data']['level']);
    }

    public function test_slug_is_unique_per_locale_at_the_database_level(): void
    {
        Project::factory()->create(['slug' => ['lt' => 'unikalus', 'en' => 'unique-en']]);

        $this->expectException(QueryException::class);

        DB::table('projects')->insert([
            'category_id' => Project::factory()->create()->category_id,
            'title' => json_encode(['lt' => 'X', 'en' => 'Y']),
            'slug' => json_encode(['lt' => 'unikalus', 'en' => 'different']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
