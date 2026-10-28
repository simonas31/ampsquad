<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ShippingMethod;
use App\Services\Checkout\CheckoutData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What the visitor types at checkout. There is deliberately no price, total,
 * status or order id in here: whatever else is sent is ignored. The
 * fingerprint is not a price either; it is the signature of what the
 * checkout page showed, which the server compares with what is true now.
 */
class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'min:5', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'shipping_method_id' => ['required', 'integer', Rule::exists(ShippingMethod::class, 'id')->where('is_active', true)],
            'delivery_address' => [
                Rule::requiredIf(fn (): bool => $this->shippingMethod()?->needsDeliveryAddress() ?? false),
                'nullable',
                'string',
                'max:500',
            ],
            'note' => ['nullable', 'string', 'max:500'],
            'accept_terms' => ['accepted'],
            'fingerprint' => ['required', 'string', 'size:64'],
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
            deliveryAddress: $validated['delivery_address'] ?? null,
            customerNote: $validated['note'] ?? null,
            fingerprint: $validated['fingerprint'],
            locale: app()->getLocale(),
        );
    }

    private function shippingMethod(): ?ShippingMethod
    {
        return ShippingMethod::query()->find($this->integer('shipping_method_id'));
    }
}
