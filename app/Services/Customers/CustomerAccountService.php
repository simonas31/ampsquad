<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Mail\CustomerAccountDeletedMail;
use App\Mail\CustomerConfirmNewEmailMail;
use App\Mail\CustomerEmailChangedMail;
use App\Mail\CustomerPasswordChangedMail;
use App\Models\Customer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * What happens to a customer's account itself: it is made here, and changed
 * and closed here.
 */
class CustomerAccountService
{
    /**
     * Makes the account and sends the mail that verifies its address. The
     * customer's emails are in $locale from now on: the language of the page
     * they registered on. They have not agreed to marketing unless they say so.
     *
     * @param  array{full_name: string, email: string, password: string, phone?: ?string, marketing_consent?: bool}  $data
     */
    public function register(array $data, string $locale): Customer
    {
        $customer = new Customer([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'locale' => $locale,
        ]);

        if ($data['marketing_consent'] ?? false) {
            $customer->marketing_consent_at = now();
        }

        $customer->save();
        $customer->sendEmailVerificationNotification();

        return $customer;
    }

    /**
     * Saves what the customer changed about themselves. Marketing consent is
     * a moment, not a flag: it is stamped when it is given, kept as it was
     * while it stays given, and cleared when it is withdrawn.
     *
     * @param  array{full_name: string, phone?: ?string, locale: string, marketing_consent?: bool}  $data
     */
    public function updateProfile(Customer $customer, array $data): void
    {
        $customer->fill([
            'full_name' => $data['full_name'],
            'phone' => $data['phone'] ?? null,
            'locale' => $data['locale'],
        ]);

        if (! ($data['marketing_consent'] ?? false)) {
            $customer->marketing_consent_at = null;
        } elseif ($customer->marketing_consent_at === null) {
            $customer->marketing_consent_at = now();
        }

        $customer->save();
    }

    /**
     * Closes the account (the caller has checked who is asking). It is only
     * soft deleted: the customer can no longer sign in, but their details are
     * kept for Customer::RETENTION_YEARS and then removed by the scheduler
     * (see Customer::prunable()), and their orders stay as they are. The
     * address is moved aside so that it can be used for a new account at
     * once, and the password is replaced with one nobody knows. The customer
     * is told by mail.
     */
    public function deleteAccount(Customer $customer): void
    {
        $email = (string) $customer->email;

        DB::transaction(function () use ($customer, $email): void {
            Password::broker('customers')->deleteToken($customer);

            $customer->forceFill([
                'retained_email' => $email,
                'email' => null,
                'pending_email' => null,
                'password' => Str::random(64),
                'remember_token' => Str::random(60),
            ])->save();

            $customer->delete();
        });

        Mail::to($email)->locale($customer->locale)->queue(new CustomerAccountDeletedMail($customer->full_name));
    }

    /**
     * Sets a new password (the caller has checked who is asking) and tells
     * the customer by mail. The "remember me" token changes with it, so a
     * browser that was kept signed in by its cookie is signed out; browsers
     * signed in by their session are signed out by AuthenticateCustomerSession.
     */
    public function changePassword(Customer $customer, string $password): void
    {
        $customer->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();

        Mail::to($customer->email)->locale($customer->locale)->queue(new CustomerPasswordChangedMail($customer));
    }

    /**
     * Notes the address the customer wants to move to and mails it a link.
     * The account keeps its address until the link is followed, and asking
     * again replaces the address waiting (the first link stops working).
     */
    public function requestEmailChange(Customer $customer, string $email): void
    {
        $customer->forceFill(['pending_email' => $email])->save();

        Mail::to($email)->locale($customer->locale)->queue(new CustomerConfirmNewEmailMail($customer));
    }

    /**
     * Moves the account to the address that was waiting, which the customer
     * has just shown they can read mail at, and tells the old address.
     * Returns false and changes nothing when no address is waiting or
     * someone else has taken it meanwhile.
     */
    public function confirmEmailChange(Customer $customer): bool
    {
        $previous = DB::transaction(function () use ($customer): ?string {
            $locked = Customer::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            $new = $locked->pending_email;

            if ($new === null || Customer::query()->where('email', $new)->whereKeyNot($locked->getKey())->exists()) {
                return null;
            }

            $previous = $locked->email;

            try {
                $locked->forceFill([
                    'email' => $new,
                    'pending_email' => null,
                    'email_verified_at' => now(),
                ])->save();
            } catch (UniqueConstraintViolationException) {
                return null;
            }

            return $previous;
        });

        if ($previous === null) {
            return false;
        }

        $customer->refresh();

        Mail::to($previous)->locale($customer->locale)->queue(new CustomerEmailChangedMail($customer));

        return true;
    }
}
