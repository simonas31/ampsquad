<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CustomerAddress;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An address as the customer types it, for a new one and for changing one.
 * The lengths are kept short enough that the whole address, written out as
 * one line, fits an order's delivery_address.
 */
class SaveCustomerAddressRequest extends FormRequest
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
            'label' => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'min:5', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'street' => ['required', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:80'],
            'postal_code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-\s]+$/'],
            'country' => ['required', 'string', Rule::in(CustomerAddress::COUNTRIES)],
            'make_default' => ['boolean'],
        ];
    }
}
