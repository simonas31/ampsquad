@component('mail::message')
# {{ __('mail.payment_problem.title', ['number' => $order->number]) }}

{{ $explanation }}

**{{ __('mail.payment_problem.order') }}:** {{ $order->number }} ({{ $order->status->getLabel() }})<br>
**{{ __('mail.payment_problem.customer') }}:** {{ $order->customer_name }}, {{ $order->customer_email }}, {{ $order->customer_phone }}<br>
**{{ __('mail.payment_problem.order_total') }}:** {{ \Illuminate\Support\Number::currency($order->total_incl_vat_cents / 100, $order->currency, app()->getLocale()) }}

@component('mail::button', ['url' => $adminUrl])
{{ __('mail.payment_problem.open') }}
@endcomponent

{{ __('mail.thanks') }}<br>
{{ config('app.name') }}
@endcomponent
