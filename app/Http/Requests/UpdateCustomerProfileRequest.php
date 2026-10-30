<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * What a customer can change about themselves without proving who they are
 * again. The email address is not here: it has its own form, which asks for
 * the password.
 */
class UpdateCustomerProfileRequest extends FormRequest
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
            'full_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'min:5', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'locale' => ['required', 'string', Rule::in(array_keys(LaravelLocalization::getSupportedLocales()))],
            'marketing_consent' => ['boolean'],
        ];
    }
}
