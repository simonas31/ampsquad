@component('mail::message')
# {{ __('mail.customer_password_changed.greeting', ['name' => $customer->full_name]) }}

{{ __('mail.customer_password_changed.text') }}

{{ __('mail.customer_password_changed.not_you', ['email' => app(\App\Settings\GeneralSettings::class)->email]) }}

@component('mail::button', ['url' => $forgotPasswordUrl])
{{ __('mail.customer_password_changed.button') }}
@endcomponent

{{ __('mail.thanks') }}<br>
{{ config('app.name') }}
@endcomponent
