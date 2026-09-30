@component('mail::message')
# New contact request

**Name:** {{ $contactRequest->name }}

**Email:** {{ $contactRequest->email }}

@if ($contactRequest->phone)
**Phone:** {{ $contactRequest->phone }}
@endif

**Message:**

{{ $contactRequest->message }}

@php $attachments = $contactRequest->getMedia('attachments'); @endphp
@if ($attachments->isNotEmpty())
**Attachments ({{ $attachments->count() }}):** {{ $attachments->pluck('file_name')->implode(', ') }}
@endif

@component('mail::button', ['url' => url('/admin/contact-requests/' . $contactRequest->id)])
View in admin
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
