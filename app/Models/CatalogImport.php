<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CatalogImportStatus;
use Database\Factories\CatalogImportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One run of the product catalog import: the uploaded file, how far it got,
 * and what it found wrong with individual rows.
 */
class CatalogImport extends Model
{
    /** @use HasFactory<CatalogImportFactory> */
    use HasFactory;

    use Prunable;

    /**
     * How long a run, and the file it was made from, are kept.
     */
    public const int RETENTION_DAYS = 90;

    /**
     * Row errors kept per run. A file that is wrong everywhere would
     * otherwise store thousands of near-identical messages.
     */
    public const int MAX_STORED_ERRORS = 200;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => CatalogImportStatus::Pending->value,
        'total_count' => 0,
        'created_count' => 0,
        'updated_count' => 0,
        'unchanged_count' => 0,
        'skipped_count' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'original_filename',
        'file_path',
        'status',
        'total_count',
        'created_count',
        'updated_count',
        'unchanged_count',
        'skipped_count',
        'errors',
        'failure_message',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CatalogImportStatus::class,
            'total_count' => 'integer',
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'unchanged_count' => 'integer',
            'skipped_count' => 'integer',
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * Runs per model, not in bulk, so the stored file goes with its row.
     */
    protected function pruning(): void
    {
        Storage::disk('local')->delete($this->file_path);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
