@php
    $money = fn (int $cents): string => \Illuminate\Support\Number::currency($cents / 100, $order->currency, app()->getLocale());
@endphp
@component('mail::message')
# {{ __('mail.order_confirmation.greeting', ['name' => $order->customer_name]) }}

{{ __('mail.order_confirmation.intro', ['total' => $money($order->total_incl_vat_cents), 'number' => $order->number]) }}

@component('mail::table')
| {{ __('mail.order_confirmation.product') }} | {{ __('mail.order_confirmation.quantity') }} | {{ __('mail.order_confirmation.price') }} |
|:--|:-:|--:|
@foreach ($order->lines as $line)
| {{ $line->product_name }} | {{ $line->quantity }} | {{ $money($line->line_total_incl_vat_cents) }} |
@endforeach
| {{ __('mail.order_confirmation.shipping', ['method' => $order->shipping_label]) }} | | {{ $money($order->shipping_incl_vat_cents) }} |
@endcomponent

**{{ __('mail.order_confirmation.subtotal') }}:** {{ $money($order->subtotal_excl_vat_cents + $order->shipping_excl_vat_cents) }}<br>
**{{ __('mail.order_confirmation.vat', ['rate' => $order->vat_rate_percent]) }}:** {{ $money($order->vat_cents) }}<br>
**{{ __('mail.order_confirmation.total') }}:** {{ $money($order->total_incl_vat_cents) }}

## {{ __('mail.order_confirmation.delivery') }}

{{ $order->shipping_label }}@if ($order->shipping_details)
: {{ $order->shipping_details }}
@endif

@if ($order->delivery_address)
{{ __('mail.order_confirmation.address', ['address' => $order->delivery_address]) }}
@endif

@component('mail::button', ['url' => $order->publicUrl()])
{{ __('mail.order_confirmation.view_order') }}
@endcomponent

{{ __('mail.order_confirmation.questions', ['email' => app(\App\Settings\GeneralSettings::class)->email]) }}

{{ __('mail.order_confirmation.thanks') }}<br>
{{ config('app.name') }}
@endcomponent
