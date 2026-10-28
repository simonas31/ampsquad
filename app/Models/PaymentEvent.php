<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PaymentEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message from a payment provider, kept once. Inserting the same
 * message twice fails on the unique (provider, event_key), which is how a
 * repeated message is told from a new one.
 */
class PaymentEvent extends Model
{
    /** @use HasFactory<PaymentEventFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'event_key',
        'order_id',
        'payment_id',
        'provider_status',
        'outcome',
        'payload',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
