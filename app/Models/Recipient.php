<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recipient extends Model
{
    /** @use HasFactory<\Database\Factories\RecipientFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'is_active',
        'notification_types',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'notification_types' => AsEnumCollection::of(NotificationType::class),
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Explicitly passes the enum's raw string value, not the enum instance
     * — MySQL's JSON_CONTAINS type-mismatches fail silently (0 rows, no
     * error) rather than throwing, which would otherwise surface as
     * "recipient exists but never got notified" with nothing to debug.
     */
    public function scopeSubscribedTo(Builder $query, NotificationType $type): Builder
    {
        return $query->whereJsonContains('notification_types', $type->value);
    }
}
