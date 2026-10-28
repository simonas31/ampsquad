@component('mail::message')
# Payment needs attention: order {{ $order->number }}

{{ $explanation }}

**Order:** {{ $order->number }} ({{ $order->status->getLabel() }})<br>
**Customer:** {{ $order->customer_name }}, {{ $order->customer_email }}, {{ $order->customer_phone }}<br>
**Order total:** {{ \Illuminate\Support\Number::currency($order->total_incl_vat_cents / 100, $order->currency, 'en') }}

@component('mail::button', ['url' => url('/admin')])
Open the admin panel
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
