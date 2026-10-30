@component('mail::message')
# {{ __('mail.customer_password_reset.greeting', ['name' => $customer->full_name]) }}

{{ __('mail.customer_password_reset.intro') }}

@component('mail::button', ['url' => $resetUrl])
{{ __('mail.customer_password_reset.button') }}
@endcomponent

{{ __('mail.customer_password_reset.expires', ['minutes' => $minutes]) }}

{{ __('mail.customer_password_reset.ignore') }}

{{ __('mail.thanks') }}<br>
{{ config('app.name') }}
@endcomponent
