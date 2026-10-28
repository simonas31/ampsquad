<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A payment started with a payment provider for an order. It knows nothing
 * of any one provider: the gateway for the provider maps what it sends onto
 * these columns. Whether the money really came is decided by the status the
 * provider's own confirmation sets, never by the customer coming back.
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => PaymentStatus::Pending->value,
        'currency' => Product::CURRENCY,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'provider_reference',
        'provider_payment_token',
        'status',
        'amount_cents',
        'paid_amount_cents',
        'currency',
        'channel',
        'bank',
        'redirect_url',
        'paid_at',
        'refunded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'paid_amount_cents' => 'integer',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }
}
