@php
    $money = fn (int $cents): string => \Illuminate\Support\Number::currency($cents / 100, $order->currency, 'en');
@endphp
@component('mail::message')
# New paid order {{ $order->number }}

Paid and waiting to be looked at.

**Customer:** {{ $order->customer_name }}<br>
**Email:** {{ $order->customer_email }}<br>
**Phone:** {{ $order->customer_phone }}

@component('mail::table')
| Product | SKU | Qty | Total incl. VAT |
|:--|:--|:-:|--:|
@foreach ($order->lines as $line)
| {{ $line->product_name }} | {{ $line->sku }} | {{ $line->quantity }} | {{ $money($line->line_total_incl_vat_cents) }} |
@endforeach
@endcomponent

**Delivery:** {{ $order->shipping_label }} ({{ $money($order->shipping_incl_vat_cents) }} incl. VAT)<br>
@if ($order->delivery_address)
**Address:** {{ $order->delivery_address }}<br>
@endif
**Total:** {{ $money($order->total_incl_vat_cents) }} (VAT {{ $order->vat_rate_percent }}%: {{ $money($order->vat_cents) }})

@if ($order->customer_note)
**Customer's note:**

{{ $order->customer_note }}
@endif

@component('mail::button', ['url' => url('/admin')])
Open the admin panel
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
