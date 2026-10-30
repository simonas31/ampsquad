<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A customer's email and password. Five wrong tries for the same address
 * from the same place lock that pair for a minute; the answer to a wrong
 * try never says whether the address has an account.
 */
class LoginCustomerRequest extends FormRequest
{
    private const int MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    /**
     * Signs the customer in on the customer guard.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::guard('customer')->attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($this->throttleKey())]),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate($this->string('email')->lower().'|'.$this->ip());
    }
}
