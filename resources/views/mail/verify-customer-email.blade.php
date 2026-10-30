@component('mail::message')
# {{ __('mail.customer_verify_email.greeting', ['name' => $customer->full_name]) }}

{{ __('mail.customer_verify_email.intro', ['app' => config('app.name')]) }}

@component('mail::button', ['url' => $verifyUrl])
{{ __('mail.customer_verify_email.button') }}
@endcomponent

{{ __('mail.customer_verify_email.expires', ['hours' => $hours]) }}

{{ __('mail.customer_verify_email.ignore') }}

{{ __('mail.thanks') }}<br>
{{ config('app.name') }}
@endcomponent
