@component('mail::message')
# {{ __('mail.contact_request.title') }}

**{{ __('mail.contact_request.name') }}:** {{ $contactRequest->name }}

**{{ __('mail.contact_request.email') }}:** {{ $contactRequest->email }}

@if ($contactRequest->phone)
**{{ __('mail.contact_request.phone') }}:** {{ $contactRequest->phone }}
@endif

**{{ __('mail.contact_request.message') }}:**

{{ $contactRequest->message }}

@php $attachments = $contactRequest->getMedia('attachments'); @endphp
@if ($attachments->isNotEmpty())
**{{ __('mail.contact_request.attachments', ['count' => $attachments->count()]) }}:** {{ $attachments->pluck('file_name')->implode(', ') }}
@endif

@component('mail::button', ['url' => url('/admin/contact-requests/' . $contactRequest->id)])
{{ __('mail.contact_request.view') }}
@endcomponent

{{ __('mail.thanks') }}<br>
{{ config('app.name') }}
@endcomponent
