<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Only a product and a quantity are read. There is deliberately no price
 * field: whatever else the client sends is ignored, and the price always
 * comes from the product row.
 */
class StoreCartItemRequest extends FormRequest
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
            'product_id' => [
                'required',
                'integer',
                Rule::exists(Product::class, 'id')->withoutTrashed()->where('is_active', true),
            ],
            'quantity' => ['required', 'integer', 'min:'.CartItem::MIN_QUANTITY, 'max:'.CartItem::MAX_QUANTITY],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => __('shop.product_unavailable'),
            'quantity.max' => __('shop.max_quantity', ['max' => CartItem::MAX_QUANTITY]),
        ];
    }

    public function product(): Product
    {
        return Product::query()->active()->findOrFail($this->integer('product_id'));
    }

    public function quantity(): int
    {
        return $this->integer('quantity');
    }
}
