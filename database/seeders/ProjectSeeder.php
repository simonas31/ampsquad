<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::query()->orderBy('order')->get();
        $completed = $categories->first();
        $ongoing = $categories->skip(1)->first();

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
    }
}
