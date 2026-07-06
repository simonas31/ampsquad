@component('mail::message')
# New contact request

**Name:** {{ $contactRequest->name }}

**Email:** {{ $contactRequest->email }}

@if ($contactRequest->phone)
**Phone:** {{ $contactRequest->phone }}
@endif

**Message:**

{{ $contactRequest->message }}

@component('mail::button', ['url' => url('/admin/contact-requests/' . $contactRequest->id)])
View in admin
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
