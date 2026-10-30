@component('mail::message')
# {{ __('mail.customer_account_deleted.greeting', ['name' => $fullName]) }}

{{ __('mail.customer_account_deleted.text', ['app' => config('app.name')]) }}

{{ __('mail.customer_account_deleted.retention', ['years' => $years]) }}

{{ __('mail.customer_account_deleted.again') }}

{{ __('mail.customer_account_deleted.not_you', ['email' => app(\App\Settings\GeneralSettings::class)->email]) }}

{{ __('mail.thanks') }}<br>
{{ config('app.name') }}
@endcomponent
