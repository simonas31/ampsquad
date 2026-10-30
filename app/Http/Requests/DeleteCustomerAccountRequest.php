<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Closing an account asks for the password and for a tick that says the
 * customer knows what it means, as it cannot be undone from the shop.
 */
class DeleteCustomerAccountRequest extends FormRequest
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
            'current_password' => ['required', 'string', 'current_password:customer'],
            'confirm' => ['accepted'],
        ];
    }
}
