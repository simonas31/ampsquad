<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;

/**
 * What happens to a customer's account itself: it is made here, and, as the
 * account area grows, changed and closed here.
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
}
