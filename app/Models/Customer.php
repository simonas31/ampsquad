<?php

declare(strict_types=1);

namespace App\Models;

use App\Mail\CustomerPasswordResetMail;
use App\Mail\VerifyCustomerEmailMail;
use Database\Factories\CustomerFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

/**
 * Someone with an account in the shop. Not a User: those are the people who
 * work in the admin panel, and a customer is never let into it (this class
 * is deliberately no FilamentUser, and signs in on its own `customer` guard).
 *
 * An account that was never verified is removed after
 * UNVERIFIED_LIFETIME_DAYS. A deleted account is only soft deleted: it can no
 * longer be used, but its data is kept for RETENTION_YEARS and then removed
 * for good, which leaves its orders in place without an owner.
 */
#[Fillable(['full_name', 'email', 'password', 'phone', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class Customer extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use MassPrunable;
    use SoftDeletes;

    /**
     * How long a deleted account's data is kept.
     */
    public const int RETENTION_YEARS = 2;

    /**
     * How long an account may wait for its email to be verified.
     */
    public const int UNVERIFIED_LIFETIME_DAYS = 7;

    /**
     * How long the link in the verification mail works.
     */
    public const int VERIFICATION_LINK_HOURS = 24;

    public const int PASSWORD_MIN_LENGTH = 10;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'locale' => 'lt',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Accounts that were never verified, and deleted accounts whose data has
     * been kept long enough (run daily by the scheduler). Mass pruning
     * removes soft deleted rows for real. The query has to ask for the
     * trashed ones itself, or the deleted accounts would never be found.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->withTrashed()->where(fn (Builder $either) => $either
            ->where(fn (Builder $unverified) => $unverified
                ->whereNull('email_verified_at')
                ->whereNull('deleted_at')
                ->where('created_at', '<', now()->subDays(self::UNVERIFIED_LIFETIME_DAYS)))
            ->orWhere('deleted_at', '<', now()->subYears(self::RETENTION_YEARS)));
    }

    /**
     * What a customer's password must be, wherever they choose one. Length is
     * what counts; nothing is asked of its characters, and nothing is sent to
     * another service to check it.
     */
    public static function passwordRule(): Password
    {
        return Password::min(self::PASSWORD_MIN_LENGTH);
    }

    /**
     * The mail with the link that verifies the address, in the customer's
     * language. Replaces Laravel's notification, which is in English only.
     */
    public function sendEmailVerificationNotification(): void
    {
        Mail::to($this->email)->locale($this->locale)->queue(new VerifyCustomerEmailMail($this));
    }

    /**
     * The mail with the link that lets the customer choose a new password.
     * Replaces Laravel's notification, which is in English only.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        Mail::to($this->email)->locale($this->locale)->queue(new CustomerPasswordResetMail($this, $token));
    }

    /**
     * The language of the customer's emails.
     */
    public function preferredLocale(): string
    {
        return $this->locale;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
