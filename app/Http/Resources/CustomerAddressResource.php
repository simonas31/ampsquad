<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of a customer's addresses as the account pages show it, flagged if it is
 * the signed-in customer's default. The URLs are relative, like the cart's,
 * because the page sends requests to them.
 *
 * @mixin CustomerAddress
 */
class CustomerAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'recipientName' => $this->recipient_name,
            'phone' => $this->phone,
            'street' => $this->street,
            'city' => $this->city,
            'postalCode' => $this->postal_code,
            'country' => $this->country,
            'countryName' => __('account.countries.'.$this->country),
            'isDefault' => $request->user('customer')?->default_shipping_address_id === $this->id,
            'editUrl' => route('account.addresses.edit', ['customerAddress' => $this->id], absolute: false),
            'updateUrl' => route('account.addresses.update', ['customerAddress' => $this->id], absolute: false),
            'deleteUrl' => route('account.addresses.destroy', ['customerAddress' => $this->id], absolute: false),
            'defaultUrl' => route('account.addresses.default', ['customerAddress' => $this->id], absolute: false),
        ];
    }
}
