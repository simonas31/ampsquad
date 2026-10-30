@component('mail::message')
# {{ __('mail.customer_email_changed.greeting', ['name' => $customer->full_name]) }}

{{ __('mail.customer_email_changed.text', ['email' => $newEmail]) }}

{{ __('mail.customer_email_changed.not_you', ['email' => app(\App\Settings\GeneralSettings::class)->email]) }}

{{ __('mail.thanks') }}<br>
{{ config('app.name') }}
@endcomponent
