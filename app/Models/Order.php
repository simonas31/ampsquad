<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\ShippingMethod;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * What a guest bought, as it was sold. Everything an order shows is copied
 * onto it when it is placed (the lines carry the products' names and prices,
 * the order its VAT rate, shipping and customer), so nothing that changes
 * afterwards in the shop can change an order.
 *
 * An order holds its stock from the moment it exists, and only moves along
 * OrderStatus::allowedTransitions(), through transitionTo(). Orders are never
 * deleted.
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * How long an order waits for its payment. The payment provider is told
     * the same, so it stops taking the payment when the order does.
     */
    public const int PAYMENT_WINDOW_MINUTES = 15;

    /**
     * How long after that window an unpaid order is still kept before its
     * stock is given back, so a payment confirmed just as the window closed
     * is not lost.
     */
    public const int EXPIRY_GRACE_MINUTES = 5;

    public const string NUMBER_PREFIX = 'AS-';

    /**
     * Digits and capitals without the letters that are mistaken for others
     * (I, L, O, U), so a number read out over the phone survives.
     */
    private const string NUMBER_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const int NUMBER_LENGTH = 8;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => OrderStatus::PendingPayment->value,
        'currency' => Product::CURRENCY,
        'shipping_excl_vat_cents' => 0,
        'shipping_incl_vat_cents' => 0,
    ];

    /**
     * Not the status or the timestamps it stamps: those only change through
     * transitionTo().
     *
     * @var list<string>
     */
    protected $fillable = [
        'locale',
        'currency',
        'vat_rate_percent',
        'subtotal_excl_vat_cents',
        'shipping_excl_vat_cents',
        'shipping_incl_vat_cents',
        'vat_cents',
        'total_incl_vat_cents',
        'shipping_method',
        'shipping_label',
        'delivery_address',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_note',
        'internal_notes',
        'terms_accepted_at',
        'payment_expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'shipping_method' => ShippingMethod::class,
            'vat_rate_percent' => 'integer',
            'subtotal_excl_vat_cents' => 'integer',
            'shipping_excl_vat_cents' => 'integer',
            'shipping_incl_vat_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_incl_vat_cents' => 'integer',
            'terms_accepted_at' => 'datetime',
            'payment_expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            $order->uuid ??= (string) Str::uuid();
            $order->number ??= self::newNumber();
            $order->payment_expires_at ??= now()->addMinutes(self::PAYMENT_WINDOW_MINUTES);
        });
    }

    /**
     * A number nobody else has, such as AS-7K3M9QXD. It is random, so it says
     * nothing about how many orders there are or in which order they came.
     * The unique index is what guarantees it; the check only makes a clash
     * (about one in a trillion) not worth a failed order.
     */
    public static function newNumber(): string
    {
        do {
            $number = self::NUMBER_PREFIX;

            for ($position = 0; $position < self::NUMBER_LENGTH; $position++) {
                $number .= self::NUMBER_ALPHABET[random_int(0, strlen(self::NUMBER_ALPHABET) - 1)];
            }
        } while (self::query()->where('number', $number)->exists());

        return $number;
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentEvents(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    /**
     * Moves the order to $to if it is allowed to go there from where it is,
     * and still is in that place in the database. Returns whether this call
     * made the move.
     *
     * The move is one UPDATE that only matches the status the order had, so
     * of two requests racing to move the same order (a callback and its
     * repeat, the sweeper and a late payment) exactly one gets true. That one
     * is the only one that may do what the move means: give stock back, send
     * the mail. Either way the order is reloaded, so it shows where it is.
     */
    public function transitionTo(OrderStatus $to): bool
    {
        $from = $this->status;

        if (! $from->canTransitionTo($to)) {
            return false;
        }

        $changes = ['status' => $to->value];

        if ($to->timestampColumn() !== null) {
            $changes[$to->timestampColumn()] = now();
        }

        $moved = self::query()
            ->whereKey($this->getKey())
            ->where('status', $from->value)
            ->update($changes) === 1;

        $this->refresh();

        return $moved;
    }
}
