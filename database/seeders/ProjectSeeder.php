<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ContentType;
use App\Models\Category;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $projectCategories = Category::query()
            ->where('applies_to', ContentType::Project)
            ->orderBy('order')
            ->get();
        $completed = $projectCategories->first();
        $ongoing = $projectCategories->skip(1)->first();

        $articleCategory = Category::query()
            ->where('applies_to', ContentType::Article)
            ->first();

        $tagIds = Tag::query()->pluck('id');

        Project::factory()
            ->count(4)
            ->sequence(['is_featured' => true], ['is_featured' => true], [], [])
            ->create(['category_id' => $completed->id])
            ->each(fn (Project $project) => $project->tags()->attach($tagIds->random(random_int(1, 3))));

        Project::factory()
            ->count(2)
            ->sequence(['is_featured' => true], [])
            ->ongoing()
            ->create(['category_id' => $ongoing->id])
            ->each(fn (Project $project) => $project->tags()->attach($tagIds->random(random_int(1, 3))));

        Project::factory()
            ->article()
            ->count(4)
            ->create(['category_id' => $articleCategory->id]);
    }
}
