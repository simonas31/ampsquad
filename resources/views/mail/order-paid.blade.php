@php
    $money = fn (int $cents): string => \Illuminate\Support\Number::currency($cents / 100, $order->currency, app()->getLocale());
@endphp
@component('mail::message')
# {{ __('mail.order_paid.title', ['number' => $order->number]) }}

{{ __('mail.order_paid.intro') }}

**{{ __('mail.order_paid.customer') }}:** {{ $order->customer_name }}<br>
**{{ __('mail.order_paid.email') }}:** {{ $order->customer_email }}<br>
**{{ __('mail.order_paid.phone') }}:** {{ $order->customer_phone }}

@component('mail::table')
| {{ __('mail.order_paid.product') }} | {{ __('mail.order_paid.sku') }} | {{ __('mail.order_paid.quantity') }} | {{ __('mail.order_paid.total_incl_vat') }} |
|:--|:--|:-:|--:|
@foreach ($order->lines as $line)
| {{ $line->product_name }} | {{ $line->sku }} | {{ $line->quantity }} | {{ $money($line->line_total_incl_vat_cents) }} |
@endforeach
@endcomponent

**{{ __('mail.order_paid.delivery') }}:** {{ __('mail.order_paid.delivery_line', ['method' => $order->shipping_label, 'price' => $money($order->shipping_incl_vat_cents)]) }}<br>
@if ($order->delivery_address)
**{{ __('mail.order_paid.address') }}:** {{ $order->delivery_address }}<br>
@endif
**{{ __('mail.order_paid.total') }}:** {{ __('mail.order_paid.total_line', ['total' => $money($order->total_incl_vat_cents), 'rate' => $order->vat_rate_percent, 'vat' => $money($order->vat_cents)]) }}

@if ($order->customer_note)
**{{ __('mail.order_paid.note') }}:**

{{ $order->customer_note }}
@endif

@component('mail::button', ['url' => $adminUrl])
{{ __('mail.order_paid.open') }}
@endcomponent

{{ __('mail.thanks') }}<br>
{{ config('app.name') }}
@endcomponent
