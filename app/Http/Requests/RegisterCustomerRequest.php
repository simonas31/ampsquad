<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * What a visitor types to register. The language of the account is not asked
 * for: it is the language of the page.
 */
class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An address is the same address in capitals, so it is kept in one form.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique(Customer::class, 'email')],
            'phone' => ['nullable', 'string', 'min:5', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'password' => ['required', 'confirmed', Customer::passwordRule()],
            'marketing_consent' => ['boolean'],
            // Hidden field real visitors never fill in, as on the contact form.
            'company' => ['prohibited'],
        ];
    }
}
