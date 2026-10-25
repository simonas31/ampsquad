<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CatalogImportStatus;
use App\Models\CatalogImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogImport>
 */
class CatalogImportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = fake()->slug(2).'.json';

        return [
            'user_id' => null,
            'original_filename' => $filename,
            'file_path' => 'imports/'.$filename,
            'status' => CatalogImportStatus::Pending,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CatalogImportStatus::Completed,
            'total_count' => 10,
            'created_count' => 6,
            'updated_count' => 2,
            'unchanged_count' => 1,
            'skipped_count' => 1,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }

    public function failed(string $message = 'The file is not valid JSON.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CatalogImportStatus::Failed,
            'failure_message' => $message,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }
}
