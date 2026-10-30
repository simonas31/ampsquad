<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\ShippingMethod;
use App\Services\Checkout\CheckoutData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * What the visitor types at checkout. There is deliberately no price, total,
 * status or order id in here: whatever else is sent is ignored. The
 * fingerprint is not a price either; it is the signature of what the
 * checkout page showed, which the server compares with what is true now.
 *
 * A guest types the delivery address as text. A customer who is signed in
 * with a verified address may instead choose one of their saved addresses,
 * by id, or type a new one in parts, which can be saved to their account
 * once the order is placed. A saved address is never read from the form: it
 * is looked up among the customer's own and written out by the server. Anyone
 * else's choice of these is ignored as if it had not been sent.
 */
class StoreCheckoutRequest extends FormRequest
{
    public const string CHOICE_SAVED = 'saved';

    public const string CHOICE_NEW = 'new';

    public const string CHOICE_TEXT = 'text';

    private ?ShippingMethod $chosenMethod = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $needsAddress = $this->needsAddress();
        $choice = $this->addressChoice();

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'min:5', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'shipping_method_id' => ['required', 'integer', Rule::exists(ShippingMethod::class, 'id')->where('is_active', true)],
            'delivery_address' => [
                Rule::requiredIf(fn (): bool => $needsAddress && $choice === self::CHOICE_TEXT),
                'nullable',
                'string',
                'max:500',
            ],
            ...$this->customerAddressRules($needsAddress, $choice),
            'note' => ['nullable', 'string', 'max:500'],
            'accept_terms' => ['accepted'],
            'fingerprint' => ['required', 'string', 'size:64'],
        ];
    }

    /**
     * The rules for the ways only a signed-in customer can give an address.
     * A guest has none, so what they send of these is not looked at.
     *
     * @return array<string, array<mixed>>
     */
    private function customerAddressRules(bool $needsAddress, string $choice): array
    {
        $customer = $this->checkoutCustomer();

        if ($customer === null) {
            return [];
        }

        return [
            'address_choice' => ['nullable', Rule::in([self::CHOICE_SAVED, self::CHOICE_NEW, self::CHOICE_TEXT])],
            'address_id' => [
                Rule::requiredIf(fn (): bool => $needsAddress && $choice === self::CHOICE_SAVED),
                'nullable',
                'integer',
                Rule::exists(CustomerAddress::class, 'id')->where('customer_id', $customer->getKey()),
            ],
            'new_street' => [Rule::requiredIf(fn (): bool => $needsAddress && $choice === self::CHOICE_NEW), 'nullable', 'string', 'max:160'],
            'new_city' => [Rule::requiredIf(fn (): bool => $needsAddress && $choice === self::CHOICE_NEW), 'nullable', 'string', 'max:80'],
            'new_postal_code' => [Rule::requiredIf(fn (): bool => $needsAddress && $choice === self::CHOICE_NEW), 'nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-\s]+$/'],
            'save_address' => ['boolean'],
        ];
    }

    public function toData(): CheckoutData
    {
        $validated = $this->validated();

        return new CheckoutData(
            customerName: $validated['name'],
            customerEmail: $validated['email'],
            customerPhone: $validated['phone'],
            shippingMethodId: (int) $validated['shipping_method_id'],
            deliveryAddress: $this->deliveryAddress($validated),
            customerNote: $validated['note'] ?? null,
            fingerprint: $validated['fingerprint'],
            locale: app()->getLocale(),
            customerId: $this->checkoutCustomer()?->getKey(),
        );
    }

    /**
     * The signed-in customer, if their address is verified. One who has not
     * verified it is a guest here, as everywhere that matters: the order is
     * not linked to their account and their saved details are not offered.
     */
    public function checkoutCustomer(): ?Customer
    {
        $customer = $this->user('customer');

        return $customer?->hasVerifiedEmail() ? $customer : null;
    }

    /**
     * Whether the customer asked for the address they typed in parts to be
     * kept, and there is one to keep: they are signed in, the delivery needs
     * an address, and it is the new one they chose.
     */
    public function savesNewAddress(): bool
    {
        return $this->checkoutCustomer() !== null
            && $this->needsAddress()
            && $this->addressChoice() === self::CHOICE_NEW
            && $this->boolean('save_address');
    }

    /**
     * The new address as the address book keeps it: delivered to the person
     * who orders, at the phone they gave.
     *
     * @return array{label: null, recipient_name: string, phone: string, street: string, city: string, postal_code: string, country: string}
     */
    public function newAddressData(): array
    {
        return [
            'label' => null,
            'recipient_name' => Str::limit((string) $this->validated('name'), 120, ''),
            'phone' => (string) $this->validated('phone'),
            'street' => (string) $this->validated('new_street'),
            'city' => (string) $this->validated('new_city'),
            'postal_code' => (string) $this->validated('new_postal_code'),
            'country' => 'LT',
        ];
    }

    /**
     * How the delivery address is given: as a saved address, a new one in
     * parts, or as text. A guest, and a customer who sent nothing, give text.
     */
    private function addressChoice(): string
    {
        if ($this->checkoutCustomer() === null) {
            return self::CHOICE_TEXT;
        }

        $choice = $this->input('address_choice');

        return in_array($choice, [self::CHOICE_SAVED, self::CHOICE_NEW], true) ? $choice : self::CHOICE_TEXT;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function deliveryAddress(array $validated): ?string
    {
        if (! $this->needsAddress()) {
            return null;
        }

        return match ($this->addressChoice()) {
            self::CHOICE_SAVED => $this->checkoutCustomer()->addresses()->find($validated['address_id'])?->formatted(app()->getLocale()),
            self::CHOICE_NEW => (new CustomerAddress($this->newAddressData()))->formatted(app()->getLocale()),
            default => $validated['delivery_address'] ?? null,
        };
    }

    private function needsAddress(): bool
    {
        return $this->shippingMethod()?->needsDeliveryAddress() ?? false;
    }

    private function shippingMethod(): ?ShippingMethod
    {
        return $this->chosenMethod ??= ShippingMethod::query()->find($this->integer('shipping_method_id'));
    }
}
