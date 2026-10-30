@component('mail::message')
# {{ __('mail.customer_confirm_new_email.greeting', ['name' => $customer->full_name]) }}

{{ __('mail.customer_confirm_new_email.intro', ['app' => config('app.name')]) }}

@component('mail::button', ['url' => $confirmUrl])
{{ __('mail.customer_confirm_new_email.button') }}
@endcomponent

{{ __('mail.customer_confirm_new_email.expires', ['hours' => $hours]) }}

{{ __('mail.customer_confirm_new_email.ignore') }}

{{ __('mail.thanks') }}<br>
{{ config('app.name') }}
@endcomponent
