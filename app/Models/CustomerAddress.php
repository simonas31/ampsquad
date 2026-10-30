<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CustomerAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A place a customer has asked us to deliver to. Checkout offers it and
 * copies it into the order as text (see formatted()); the order never points
 * back at it, so changing or deleting it later changes no order.
 */
#[Fillable(['label', 'recipient_name', 'phone', 'street', 'city', 'postal_code', 'country'])]
class CustomerAddress extends Model
{
    /** @use HasFactory<CustomerAddressFactory> */
    use HasFactory;

    /**
     * Most addresses one customer may keep. Nobody needs more, and without a
     * cap a script could fill the table.
     */
    public const int MAX_PER_CUSTOMER = 10;

    /**
     * The countries the shop delivers to, as codes.
     *
     * @var list<string>
     */
    public const array COUNTRIES = ['LT'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'country' => 'LT',
    ];

    /**
     * The address as one line of text, as an order keeps it in its
     * delivery_address: who gets the parcel, how to reach them, and where.
     * The country is named in $locale (the language the customer orders in).
     */
    public function formatted(?string $locale = null): string
    {
        return implode(', ', [
            $this->recipient_name,
            $this->phone,
            $this->street,
            trim($this->postal_code.' '.$this->city),
            __('account.countries.'.$this->country, [], $locale),
        ]);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
