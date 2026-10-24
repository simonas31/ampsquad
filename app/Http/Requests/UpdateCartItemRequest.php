<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CartItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Whose line this is needs no check here: the {cartItem} route binding only
 * ever resolves lines of the visitor's own cart. Zero is not a quantity;
 * removing a line is its own request.
 */
class UpdateCartItemRequest extends FormRequest
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
            'quantity' => ['required', 'integer', 'min:'.CartItem::MIN_QUANTITY, 'max:'.CartItem::MAX_QUANTITY],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.max' => __('shop.max_quantity', ['max' => CartItem::MAX_QUANTITY]),
        ];
    }

    public function quantity(): int
    {
        return $this->integer('quantity');
    }
}
